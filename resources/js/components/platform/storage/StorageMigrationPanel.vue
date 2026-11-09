<script setup lang="ts">
import { router, usePoll } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowDownToLine,
    ArrowUpFromLine,
    CheckCircle2,
    CloudOff,
    HardDrive,
    HelpCircle,
    Loader2,
    Lock,
    RefreshCw,
    Server,
    Trash2,
    XCircle,
} from '@lucide/vue';
import { computed, onMounted, ref, watch } from 'vue';
import ConfirmDialog from '@/components/ds/ConfirmDialog.vue';
import DateText from '@/components/ds/DateText.vue';
import StatusBadge from '@/components/ds/StatusBadge.vue';
import {
    AlertDialog,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Progress } from '@/components/ui/progress';
import { Switch } from '@/components/ui/switch';
import { formatBytes, formatNumber } from '@/lib/format';
import { cn } from '@/lib/utils';
import almacenamiento from '@/routes/admin/almacenamiento';
import traslados from '@/routes/admin/almacenamiento/traslados';
import StorageMigrationFailures from './StorageMigrationFailures.vue';
import type {
    MigrationHistoryEntry,
    MigrationPlan,
    StorageMigration,
    StorageSummary,
} from './types';

/**
 * Dónde viven los archivos, respaldo local (pendientes de subir al NAS) y
 * traslado servidor ⇄ Synology con progreso en vivo. El avance se consulta
 * cada 2 s mientras hay un traslado en curso (recarga parcial de Inertia).
 */
const props = defineProps<{
    storage: StorageSummary;
    migration: StorageMigration | null;
    history: MigrationHistoryEntry[];
    plan: { toSynology: MigrationPlan; toLocal: MigrationPlan };
    /** El NAS está montado, conectado y pasó la última prueba. */
    synologyReady: boolean;
    synologyActive: boolean;
}>();

const TOUCH = 'pointer-coarse:h-11';

const DISK_ICON: Record<string, typeof Server> = {
    media_local: HardDrive,
    media_synology: Server,
    media_private: Lock,
};

const DISK_HINT: Record<string, string> = {
    media_local: 'Fotos, renders y videos del sitio guardados en el servidor.',
    media_synology: 'Archivos que ya viven en el NAS de la oficina.',
    media_private:
        'Documentos y fotos de clientes. Por seguridad siempre se quedan en el servidor.',
};

// ------------------------------------------------------------ Progreso

const active = computed(() => props.migration?.isActive ?? false);

const { start, stop } = usePoll(
    2000,
    { only: ['migration', 'storage', 'plan', 'history', 'providers'] },
    { autoStart: false },
);

watch(
    active,
    (isActive) => {
        if (isActive) {
            start();
        } else {
            stop();
        }
    },
    { immediate: false },
);

onMounted(() => {
    if (active.value) {
        start();
    }
});

const percent = computed(() => {
    const m = props.migration;
    if (!m) {
        return 0;
    }

    if (typeof m.percent === 'number') {
        return Math.min(100, Math.max(0, Math.round(m.percent)));
    }

    if (m.totalFiles === 0) {
        return m.isActive ? 0 : 100;
    }

    return Math.min(100, Math.round((m.processedFiles / m.totalFiles) * 100));
});

/** Desglose del avance (trasladados, omitidos, fallidos, pendientes). */
const breakdown = computed(() => {
    const c = props.migration?.counts;
    if (!c) {
        return [];
    }

    return [
        {
            key: 'done',
            label: 'Trasladados',
            value: c.done,
            dot: 'bg-success',
            text: '',
        },
        {
            key: 'skipped',
            label: 'Ya estaban en el destino',
            value: c.skipped,
            dot: 'bg-info',
            text: '',
        },
        {
            key: 'failed',
            label: 'Con error',
            value: c.failed,
            dot: 'bg-danger',
            text: c.failed > 0 ? 'text-danger-ink' : '',
        },
        {
            key: 'pending',
            label: 'Pendientes',
            value: c.pending,
            dot: 'bg-muted-foreground/40',
            text: '',
        },
    ];
});

/** Último error conocido: el que mande el backend o el del último fallido. */
const lastError = computed(() => {
    const m = props.migration;
    if (!m) {
        return null;
    }

    if (m.lastError) {
        return m.lastError;
    }

    for (let i = m.failures.length - 1; i >= 0; i--) {
        if (m.failures[i].error) {
            return m.failures[i].error;
        }
    }

    return null;
});

function shorten(text: string, max = 160): string {
    const line = text.split('\n')[0].trim();

    return line.length > max ? `${line.slice(0, max)}…` : line;
}

function formatEta(migration: StorageMigration): string {
    const seconds = migration.etaSeconds;

    if (migration.status === 'queued') {
        return 'En espera';
    }

    if (seconds === null) {
        return 'Calculando…';
    }

    if (seconds < 60) {
        return 'Menos de 1 min';
    }

    const minutes = Math.round(seconds / 60);
    if (minutes < 60) {
        return `Unos ${minutes} min`;
    }

    const hours = Math.floor(minutes / 60);
    if (hours >= 24) {
        const days = Math.floor(hours / 24);
        const restHours = hours % 24;

        return restHours > 0
            ? `Unos ${days} d ${restHours} h`
            : `Unos ${days} d`;
    }

    const rest = minutes % 60;

    return rest > 0 ? `Unas ${hours} h ${rest} min` : `Unas ${hours} h`;
}

const DIRECTION_LABEL = {
    to_synology: 'Servidor → Synology',
    to_local: 'Synology → Servidor',
} as const;

const STATUS_TONE = {
    queued: 'info',
    running: 'info',
    completed: 'success',
    completed_with_errors: 'warning',
    cancelled: 'neutral',
} as const;

// ------------------------------------------------------------ Acciones

type Direction = 'to_synology' | 'to_local';

const startOpen = ref(false);
const startDirection = ref<Direction>('to_synology');
const keepSource = ref(true);
const starting = ref(false);

function askStart(direction: Direction) {
    startDirection.value = direction;
    keepSource.value = true;
    startOpen.value = true;
}

function confirmStart() {
    starting.value = true;
    router.post(
        traslados.store().url,
        { direction: startDirection.value, keep_source: keepSource.value },
        {
            preserveScroll: true,
            onFinish: () => {
                starting.value = false;
                startOpen.value = false;
            },
        },
    );
}

function post(url: string): Promise<void> {
    return new Promise((resolve, reject) => {
        router.post(
            url,
            {},
            {
                preserveScroll: true,
                onSuccess: () => resolve(),
                onError: () =>
                    reject(new Error('No se pudo completar la acción.')),
            },
        );
    });
}

const cancelOpen = ref(false);
const purgeOpen = ref(false);
const busy = ref<string | null>(null);

function run(key: string, url: string) {
    busy.value = key;
    router.post(
        url,
        {},
        { preserveScroll: true, onFinish: () => (busy.value = null) },
    );
}

const startPlan = computed(() =>
    startDirection.value === 'to_synology'
        ? props.plan.toSynology
        : props.plan.toLocal,
);

const purgeLabel = computed(() =>
    props.migration?.sourceDisk === 'media_local'
        ? 'Liberar espacio en el servidor'
        : 'Borrar copias del Synology',
);
</script>

<template>
    <div class="flex flex-col gap-6">
        <!-- Dónde están los archivos -->
        <section
            class="rounded-xl border bg-card p-5"
            aria-labelledby="storage-where-title"
        >
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2
                        id="storage-where-title"
                        class="text-base font-semibold"
                    >
                        Dónde están tus archivos
                    </h2>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Cada archivo recuerda en qué disco vive, así que todos
                        siguen funcionando aunque estén repartidos.
                    </p>
                </div>
            </div>

            <div
                v-if="props.storage.remoteDown"
                class="mt-4 flex items-start gap-3 rounded-lg border border-warning/50 bg-warning-soft/60 p-3 text-sm text-warning-ink"
                role="status"
            >
                <CloudOff class="mt-0.5 size-4 shrink-0" />
                <div class="min-w-0">
                    <p class="font-medium">El Synology no responde</p>
                    <p class="mt-0.5 text-xs">
                        Las subidas nuevas se guardan en el servidor y se pasan
                        solas al NAS cuando vuelva.
                        <template v-if="props.storage.pendingSync > 0">
                            Hay
                            {{ formatNumber(props.storage.pendingSync) }}
                            {{
                                props.storage.pendingSync === 1
                                    ? 'archivo esperando.'
                                    : 'archivos esperando.'
                            }}
                        </template>
                    </p>
                </div>
            </div>

            <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <article
                    v-for="disk in props.storage.disks"
                    :key="disk.disk"
                    class="flex flex-col gap-2 rounded-lg border p-4"
                >
                    <div class="flex items-center gap-2">
                        <component
                            :is="DISK_ICON[disk.disk] ?? HardDrive"
                            class="size-4 text-muted-foreground"
                        />
                        <h3 class="text-sm font-medium">{{ disk.label }}</h3>
                    </div>
                    <p class="text-2xl font-semibold tabular-nums">
                        {{ formatNumber(disk.files) }}
                        <span class="text-sm font-normal text-muted-foreground"
                            >archivos</span
                        >
                    </p>
                    <p class="text-xs text-muted-foreground tabular-nums">
                        {{ formatBytes(disk.bytes) }} ·
                        {{ formatNumber(disk.variants) }} versiones optimizadas
                    </p>
                    <p class="text-xs text-muted-foreground">
                        {{ DISK_HINT[disk.disk] }}
                    </p>
                </article>

                <article
                    :class="
                        cn(
                            'flex flex-col gap-2 rounded-lg border p-4',
                            props.storage.pendingSync > 0 &&
                                'border-warning/50 bg-warning-soft/40',
                        )
                    "
                >
                    <div class="flex items-center gap-2">
                        <ArrowUpFromLine class="size-4 text-muted-foreground" />
                        <h3 class="text-sm font-medium">
                            Esperando subir al Synology
                        </h3>
                    </div>
                    <p class="text-2xl font-semibold tabular-nums">
                        {{ formatNumber(props.storage.pendingSync) }}
                        <span class="text-sm font-normal text-muted-foreground"
                            >archivos</span
                        >
                    </p>
                    <p class="text-xs text-muted-foreground">
                        {{
                            props.storage.pendingSync > 0
                                ? `${formatBytes(props.storage.pendingSyncBytes)} guardados en el servidor mientras el NAS no respondía. Se suben solos cada 10 minutos.`
                                : 'Nada pendiente: todo está donde debe.'
                        }}
                    </p>
                    <p
                        v-if="props.storage.pendingSyncError"
                        class="flex items-start gap-1.5 rounded-md bg-warning-soft px-2.5 py-1.5 text-xs break-words text-warning-ink"
                        :title="props.storage.pendingSyncError"
                    >
                        <AlertTriangle class="mt-px size-3.5 shrink-0" />
                        <span class="min-w-0"
                            >Último error:
                            {{ shorten(props.storage.pendingSyncError) }}</span
                        >
                    </p>
                    <Button
                        v-if="props.storage.pendingSync > 0"
                        size="sm"
                        variant="outline"
                        class="mt-auto h-auto min-h-9 max-w-full self-start py-2 text-left whitespace-normal pointer-coarse:min-h-11"
                        :disabled="busy === 'sync'"
                        @click="run('sync', almacenamiento.sincronizar().url)"
                    >
                        <Loader2
                            v-if="busy === 'sync'"
                            class="size-4 animate-spin"
                        />
                        <RefreshCw v-else class="size-4" />
                        Sincronizar ahora
                    </Button>
                </article>
            </div>
        </section>

        <div class="grid items-start gap-6 xl:grid-cols-3">
            <!-- Traslado -->
            <section
                class="flex flex-col gap-5 rounded-xl border bg-card p-5 xl:col-span-2"
                aria-labelledby="storage-move-title"
                aria-live="polite"
            >
                <div>
                    <h2 id="storage-move-title" class="text-base font-semibold">
                        Mover multimedia entre el servidor y el Synology
                    </h2>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Se copia archivo por archivo (con todas sus versiones),
                        se verifica la copia y solo entonces se cambia su
                        ubicación. El sitio sigue funcionando durante el
                        traslado.
                    </p>
                </div>

                <!-- En curso -->
                <div
                    v-if="props.migration && props.migration.isActive"
                    class="flex flex-col gap-4 rounded-lg border border-primary/40 p-4"
                >
                    <div class="flex flex-wrap items-center gap-2">
                        <Loader2 class="size-4 animate-spin text-primary" />
                        <span class="font-medium">{{
                            DIRECTION_LABEL[props.migration.direction]
                        }}</span>
                        <StatusBadge
                            tone="info"
                            :label="props.migration.statusLabel"
                        />
                    </div>

                    <div
                        v-if="props.migration.waitingForWorker"
                        class="flex items-start gap-3 rounded-lg border border-warning/50 bg-warning-soft/60 p-3 text-sm text-warning-ink"
                        role="alert"
                    >
                        <AlertTriangle class="mt-0.5 size-4 shrink-0" />
                        <div class="min-w-0">
                            <p class="font-medium">
                                El traslado no ha empezado
                            </p>
                            <p class="mt-0.5 text-xs">
                                Lleva más de un minuto en cola. Revisa que el
                                procesador de tareas del servidor (queue:work)
                                esté encendido; arrancará solo en cuanto lo
                                esté.
                            </p>
                        </div>
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <div
                            class="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-1"
                        >
                            <span
                                class="text-2xl font-semibold tabular-nums"
                                aria-hidden="true"
                                >{{ percent }}%</span
                            >
                            <span class="text-sm text-muted-foreground">
                                Tiempo restante:
                                <span class="font-medium text-foreground">{{
                                    formatEta(props.migration)
                                }}</span>
                            </span>
                        </div>
                        <Progress
                            :model-value="percent"
                            class="h-3"
                            :aria-label="`Avance del traslado: ${percent}%`"
                        />
                        <p
                            class="flex flex-wrap gap-x-4 gap-y-0.5 text-xs text-muted-foreground tabular-nums"
                        >
                            <span
                                ><span class="font-medium text-foreground">{{
                                    formatNumber(props.migration.processedFiles)
                                }}</span>
                                de
                                {{ formatNumber(props.migration.totalFiles) }}
                                archivos</span
                            >
                            <span
                                ><span class="font-medium text-foreground">{{
                                    formatBytes(props.migration.processedBytes)
                                }}</span>
                                de
                                {{ formatBytes(props.migration.totalBytes) }}
                                procesados</span
                            >
                            <span
                                >{{
                                    formatBytes(
                                        props.migration.transferredBytes,
                                    )
                                }}
                                copiados</span
                            >
                        </p>
                    </div>

                    <ul
                        class="grid grid-cols-2 gap-2 text-sm md:grid-cols-4"
                        aria-label="Desglose del traslado"
                    >
                        <li
                            v-for="item in breakdown"
                            :key="item.key"
                            class="flex flex-col gap-0.5 rounded-md border px-3 py-2"
                        >
                            <span
                                class="inline-flex items-center gap-1.5 text-xs text-muted-foreground"
                            >
                                <span
                                    :class="cn('size-2 rounded-full', item.dot)"
                                />
                                {{ item.label }}
                            </span>
                            <span
                                :class="
                                    cn('font-semibold tabular-nums', item.text)
                                "
                                >{{ formatNumber(item.value) }}</span
                            >
                        </li>
                    </ul>

                    <p
                        v-if="
                            lastError && props.migration.failures.length === 0
                        "
                        class="flex items-start gap-1.5 rounded-md bg-danger-soft px-3 py-2 text-xs break-words text-danger-ink"
                        :title="lastError"
                    >
                        <AlertTriangle class="mt-px size-3.5 shrink-0" />
                        <span class="min-w-0"
                            >Último error: {{ shorten(lastError) }}</span
                        >
                    </p>

                    <StorageMigrationFailures
                        v-if="props.migration.failures.length > 0"
                        :failures="props.migration.failures"
                        :total="props.migration.counts.failed"
                    />
                    <p
                        v-if="props.migration.counts.failed > 0"
                        class="text-xs text-muted-foreground"
                    >
                        Podrás reintentar los fallidos cuando termine el
                        traslado.
                    </p>

                    <div class="flex flex-wrap gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            :class="TOUCH"
                            @click="cancelOpen = true"
                        >
                            <XCircle class="size-4" />
                            Cancelar traslado
                        </Button>
                    </div>
                </div>

                <!-- Acciones -->
                <div v-else class="grid gap-3 md:grid-cols-2">
                    <article class="flex flex-col gap-3 rounded-lg border p-4">
                        <div class="flex items-center gap-2">
                            <ArrowUpFromLine class="size-4 text-primary" />
                            <h3 class="text-sm font-semibold">
                                Del servidor al Synology
                            </h3>
                        </div>
                        <p class="text-sm text-muted-foreground">
                            <span class="font-medium text-foreground"
                                >{{
                                    formatNumber(props.plan.toSynology.files)
                                }}
                                archivos</span
                            >
                            ({{ formatBytes(props.plan.toSynology.bytes) }})
                            están hoy en el servidor.
                        </p>
                        <Button
                            class="mt-auto h-auto min-h-9 max-w-full self-start py-2 text-left whitespace-normal pointer-coarse:min-h-11"
                            :disabled="
                                !props.synologyReady ||
                                props.plan.toSynology.files === 0
                            "
                            @click="askStart('to_synology')"
                        >
                            <ArrowUpFromLine class="size-4" />
                            Mover multimedia del servidor al Synology
                        </Button>
                        <p
                            v-if="!props.synologyReady"
                            class="text-xs text-muted-foreground"
                        >
                            Se habilita cuando el Synology esté conectado y
                            «Probar conexión» salga bien.
                        </p>
                    </article>

                    <article class="flex flex-col gap-3 rounded-lg border p-4">
                        <div class="flex items-center gap-2">
                            <ArrowDownToLine
                                class="size-4 text-muted-foreground"
                            />
                            <h3 class="text-sm font-semibold">
                                Del Synology al servidor
                            </h3>
                        </div>
                        <p class="text-sm text-muted-foreground">
                            Para emergencias (cambio de NAS, falla prolongada).
                            <span class="font-medium text-foreground"
                                >{{
                                    formatNumber(props.plan.toLocal.files)
                                }}
                                archivos</span
                            >
                            ({{ formatBytes(props.plan.toLocal.bytes) }}) están
                            en el Synology.
                        </p>
                        <Button
                            variant="outline"
                            class="mt-auto h-auto min-h-9 max-w-full self-start py-2 text-left whitespace-normal pointer-coarse:min-h-11"
                            :disabled="props.plan.toLocal.files === 0"
                            @click="askStart('to_local')"
                        >
                            <ArrowDownToLine class="size-4" />
                            Traer multimedia al servidor
                        </Button>
                    </article>
                </div>

                <!-- Resultado del último traslado -->
                <div
                    v-if="props.migration && !props.migration.isActive"
                    class="flex flex-col gap-3 rounded-lg border p-4"
                >
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="text-sm font-medium"
                            >Último traslado:</span
                        >
                        <span class="text-sm">{{
                            DIRECTION_LABEL[props.migration.direction]
                        }}</span>
                        <StatusBadge
                            :tone="STATUS_TONE[props.migration.status]"
                            :icon="
                                props.migration.status === 'completed'
                                    ? CheckCircle2
                                    : undefined
                            "
                            :label="props.migration.statusLabel"
                        />
                        <span class="text-xs text-muted-foreground">
                            <DateText
                                :value="
                                    props.migration.finishedAt ??
                                    props.migration.createdAt
                                "
                                format="datetime"
                            />
                        </span>
                    </div>

                    <ul
                        class="grid grid-cols-2 gap-2 text-sm md:grid-cols-4"
                        aria-label="Resultado del traslado"
                    >
                        <li
                            v-for="item in breakdown"
                            :key="item.key"
                            class="flex flex-col gap-0.5 rounded-md border px-3 py-2"
                        >
                            <span
                                class="inline-flex items-center gap-1.5 text-xs text-muted-foreground"
                            >
                                <span
                                    :class="cn('size-2 rounded-full', item.dot)"
                                />
                                {{ item.label }}
                            </span>
                            <span
                                :class="
                                    cn('font-semibold tabular-nums', item.text)
                                "
                                >{{ formatNumber(item.value) }}</span
                            >
                        </li>
                    </ul>
                    <p class="text-xs text-muted-foreground tabular-nums">
                        {{ formatBytes(props.migration.transferredBytes) }}
                        copiados de
                        {{ formatBytes(props.migration.totalBytes) }}.
                    </p>

                    <p
                        v-if="
                            lastError && props.migration.failures.length === 0
                        "
                        class="flex items-start gap-1.5 rounded-md bg-danger-soft px-3 py-2 text-xs break-words text-danger-ink"
                        :title="lastError"
                    >
                        <AlertTriangle class="mt-px size-3.5 shrink-0" />
                        <span class="min-w-0"
                            >Último error: {{ shorten(lastError) }}</span
                        >
                    </p>

                    <StorageMigrationFailures
                        v-if="props.migration.failures.length > 0"
                        :failures="props.migration.failures"
                        :total="props.migration.counts.failed"
                    />

                    <div class="flex flex-wrap gap-2">
                        <Button
                            v-if="
                                props.migration.counts.failed > 0 ||
                                props.migration.counts.pending > 0
                            "
                            size="sm"
                            :class="TOUCH"
                            :disabled="busy === 'retry'"
                            @click="
                                run(
                                    'retry',
                                    traslados.reintentar(props.migration.id)
                                        .url,
                                )
                            "
                        >
                            <Loader2
                                v-if="busy === 'retry'"
                                class="size-4 animate-spin"
                            />
                            <RefreshCw v-else class="size-4" />
                            {{
                                props.migration.counts.failed > 0
                                    ? 'Reintentar los fallidos'
                                    : 'Reanudar'
                            }}
                        </Button>
                        <Button
                            v-if="props.migration.keptSources > 0"
                            size="sm"
                            variant="outline"
                            :class="TOUCH"
                            @click="purgeOpen = true"
                        >
                            <Trash2 class="size-4" />
                            {{ purgeLabel }} ({{
                                formatNumber(props.migration.keptSources)
                            }})
                        </Button>
                    </div>
                    <p
                        v-if="props.migration.keptSources > 0"
                        class="text-xs text-muted-foreground"
                    >
                        Se conservó una copia de
                        {{ formatNumber(props.migration.keptSources) }}
                        archivos en el origen. Revisa que el sitio y la
                        biblioteca se vean bien y después libera el espacio.
                    </p>
                </div>

                <!-- Historial -->
                <details
                    v-if="props.history.length > 1"
                    class="rounded-lg border px-4 py-3 text-sm"
                >
                    <summary class="cursor-pointer font-medium">
                        Traslados anteriores
                    </summary>
                    <ul class="mt-3 flex flex-col gap-2">
                        <li
                            v-for="entry in props.history"
                            :key="entry.id"
                            class="flex flex-wrap items-center gap-x-3 gap-y-1"
                        >
                            <span>{{ DIRECTION_LABEL[entry.direction] }}</span>
                            <StatusBadge
                                size="sm"
                                :tone="STATUS_TONE[entry.status]"
                                :label="entry.statusLabel"
                            />
                            <span class="text-muted-foreground tabular-nums">
                                {{ formatNumber(entry.totalFiles) }} archivos ·
                                {{ formatBytes(entry.totalBytes) }}
                            </span>
                            <span class="text-xs text-muted-foreground">
                                <DateText
                                    :value="entry.createdAt"
                                    format="datetime"
                                />
                            </span>
                        </li>
                    </ul>
                </details>
            </section>

            <!-- Preguntas frecuentes -->
            <section
                class="flex flex-col gap-4 rounded-xl border bg-card p-5"
                aria-labelledby="storage-faq-title"
            >
                <h2
                    id="storage-faq-title"
                    class="flex items-center gap-2 text-base font-semibold"
                >
                    <HelpCircle class="size-4 text-muted-foreground" />
                    ¿Cómo funciona?
                </h2>
                <div class="flex flex-col gap-1">
                    <h3 class="text-sm font-medium">
                        Si el Synology no se conecta, ¿se sube al servidor?
                    </h3>
                    <p class="text-sm text-muted-foreground">
                        Sí. Si el Synology está activo pero no responde, la foto
                        o el video se guarda en el servidor para que nadie
                        pierda su trabajo, y te avisamos una vez por correo y en
                        notificaciones. Ese archivo queda «esperando subir al
                        Synology».
                    </p>
                </div>
                <div class="flex flex-col gap-1">
                    <h3 class="text-sm font-medium">
                        Y cuando vuelva a conectar, ¿se pasa solo?
                    </h3>
                    <p class="text-sm text-muted-foreground">
                        Sí. Cada 10 minutos la plataforma revisa si el Synology
                        ya responde y sube lo pendiente; también puedes pulsar
                        «Sincronizar ahora» o «Probar conexión».
                    </p>
                </div>
                <div class="flex flex-col gap-1">
                    <h3 class="text-sm font-medium">
                        ¿Puedo pasar al Synology lo que ya está en el servidor?
                    </h3>
                    <p class="text-sm text-muted-foreground">
                        Sí, con «Mover multimedia del servidor al Synology».
                        Cada archivo se copia, se verifica y luego se cambia su
                        ubicación; por seguridad se conserva una copia en el
                        servidor hasta que tú decidas liberarla.
                    </p>
                </div>
                <div class="flex flex-col gap-1">
                    <h3 class="text-sm font-medium">¿Qué no se mueve?</h3>
                    <p class="text-sm text-muted-foreground">
                        Los documentos privados de clientes (contratos,
                        identificaciones, evidencias) siempre se quedan en el
                        servidor.
                    </p>
                </div>
                <p
                    v-if="!props.synologyActive"
                    class="rounded-md bg-muted px-3 py-2 text-xs text-muted-foreground"
                >
                    Hoy el proveedor activo es el servidor: las subidas nuevas
                    se guardan ahí. Activa el Synology arriba para que las
                    nuevas vayan directo al NAS.
                </p>
            </section>
        </div>

        <!-- Confirmar traslado -->
        <AlertDialog v-model:open="startOpen">
            <AlertDialogContent class="gap-5 rounded-xl sm:max-w-md">
                <AlertDialogHeader>
                    <AlertDialogTitle
                        class="font-display text-lg font-semibold tracking-tight"
                    >
                        {{
                            startDirection === 'to_synology'
                                ? 'Mover multimedia al Synology'
                                : 'Traer multimedia al servidor'
                        }}
                    </AlertDialogTitle>
                    <AlertDialogDescription class="text-sm leading-relaxed">
                        Se moverán
                        {{ formatNumber(startPlan.files) }} archivos ({{
                            formatBytes(startPlan.bytes)
                        }}) con todas sus versiones. Puede tardar; puedes cerrar
                        esta página y volver después.
                    </AlertDialogDescription>
                </AlertDialogHeader>

                <div class="flex items-start gap-3 rounded-lg border p-3">
                    <Switch
                        id="storage-keep-source"
                        v-model="keepSource"
                        class="mt-0.5"
                    />
                    <div class="grid gap-1">
                        <Label for="storage-keep-source">
                            {{
                                startDirection === 'to_synology'
                                    ? 'Conservar copia en el servidor'
                                    : 'Conservar copia en el Synology'
                            }}
                        </Label>
                        <p class="text-xs text-muted-foreground">
                            Recomendado. Cuando compruebes que todo se ve bien,
                            podrás liberar ese espacio con un botón.
                        </p>
                    </div>
                </div>

                <AlertDialogFooter class="gap-2 sm:gap-2">
                    <AlertDialogCancel :disabled="starting" class="h-11 sm:h-9">
                        Cancelar
                    </AlertDialogCancel>
                    <Button
                        class="h-11 sm:h-9"
                        :disabled="starting"
                        :aria-busy="starting"
                        @click="confirmStart"
                    >
                        <Loader2 v-if="starting" class="size-4 animate-spin" />
                        {{ starting ? 'Iniciando…' : 'Iniciar traslado' }}
                    </Button>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>

        <ConfirmDialog
            v-if="props.migration"
            v-model:open="cancelOpen"
            title="Cancelar el traslado"
            description="Lo que ya se movió se queda en el destino y funciona normal. Lo pendiente se queda donde estaba; puedes reanudarlo después."
            confirm-label="Cancelar traslado"
            cancel-label="Seguir moviendo"
            loading-label="Cancelando…"
            tone="destructive"
            :confirm="() => post(traslados.cancelar(props.migration!.id).url)"
        />

        <ConfirmDialog
            v-if="props.migration"
            v-model:open="purgeOpen"
            :title="purgeLabel"
            description="Antes de borrar cada copia se vuelve a comprobar que el archivo esté completo en el destino. Esta acción no se puede deshacer."
            confirm-label="Borrar copias"
            loading-label="Borrando…"
            tone="destructive"
            :confirm="() => post(traslados.liberar(props.migration!.id).url)"
        />
    </div>
</template>
