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
    /** El NAS pasó la última prueba de conexión. */
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
    if (!m || m.totalFiles === 0) {
        return 0;
    }

    return Math.min(100, Math.round((m.processedFiles / m.totalFiles) * 100));
});

function formatEta(seconds: number | null): string {
    if (seconds === null) {
        return 'Calculando…';
    }

    if (seconds < 60) {
        return 'menos de 1 min';
    }

    const minutes = Math.round(seconds / 60);
    if (minutes < 60) {
        return `${minutes} min`;
    }

    const hours = Math.floor(minutes / 60);
    const rest = minutes % 60;

    return rest > 0 ? `${hours} h ${rest} min` : `${hours} h`;
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
                <StatusBadge
                    v-if="props.storage.remoteDown"
                    tone="warning"
                    :icon="CloudOff"
                    label="Synology sin conexión: las subidas van al servidor"
                />
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
                        class="text-xs break-words text-warning-ink"
                    >
                        Último error: {{ props.storage.pendingSyncError }}
                    </p>
                    <Button
                        v-if="props.storage.pendingSync > 0"
                        size="sm"
                        variant="outline"
                        :class="cn('mt-auto self-start', TOUCH)"
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

        <div class="grid gap-6 xl:grid-cols-3">
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

                    <Progress
                        :model-value="percent"
                        class="h-3"
                        :aria-label="`Avance del traslado: ${percent}%`"
                    />

                    <dl
                        class="grid grid-cols-2 gap-x-6 gap-y-3 text-sm md:grid-cols-4"
                    >
                        <div>
                            <dt class="text-xs text-muted-foreground">
                                Archivos
                            </dt>
                            <dd class="font-semibold tabular-nums">
                                {{
                                    formatNumber(props.migration.processedFiles)
                                }}
                                de
                                {{ formatNumber(props.migration.totalFiles) }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground">
                                Transferido
                            </dt>
                            <dd class="font-semibold tabular-nums">
                                {{
                                    formatBytes(
                                        props.migration.transferredBytes,
                                    )
                                }}
                                <span class="font-normal text-muted-foreground">
                                    de
                                    {{
                                        formatBytes(props.migration.totalBytes)
                                    }}</span
                                >
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground">
                                Errores
                            </dt>
                            <dd
                                :class="
                                    cn(
                                        'font-semibold tabular-nums',
                                        props.migration.counts.failed > 0 &&
                                            'text-danger-ink',
                                    )
                                "
                            >
                                {{
                                    formatNumber(props.migration.counts.failed)
                                }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground">
                                Tiempo restante
                            </dt>
                            <dd class="font-semibold">
                                {{ formatEta(props.migration.etaSeconds) }}
                            </dd>
                        </div>
                    </dl>

                    <p
                        v-if="props.migration.waitingForWorker"
                        class="flex items-start gap-1.5 rounded-md bg-warning-soft px-3 py-2 text-xs text-warning-ink"
                    >
                        <AlertTriangle class="mt-px size-3.5 shrink-0" />
                        El traslado sigue en cola. Verifica que el procesador de
                        tareas del servidor (queue:work) esté encendido.
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
                            :class="cn('mt-auto self-start', TOUCH)"
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
                            Primero configura el Synology y pulsa «Probar
                            conexión»; el botón se habilita cuando la prueba
                            sale bien.
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
                            :class="cn('mt-auto self-start', TOUCH)"
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

                    <ul class="flex flex-wrap gap-x-5 gap-y-1 text-sm">
                        <li>
                            <span class="font-semibold tabular-nums">{{
                                formatNumber(props.migration.counts.done)
                            }}</span>
                            movidos
                        </li>
                        <li>
                            <span class="font-semibold tabular-nums">{{
                                formatNumber(props.migration.counts.skipped)
                            }}</span>
                            ya estaban en el destino
                        </li>
                        <li
                            :class="
                                props.migration.counts.failed > 0 &&
                                'text-danger-ink'
                            "
                        >
                            <span class="font-semibold tabular-nums">{{
                                formatNumber(props.migration.counts.failed)
                            }}</span>
                            con error
                        </li>
                        <li v-if="props.migration.counts.pending > 0">
                            <span class="font-semibold tabular-nums">{{
                                formatNumber(props.migration.counts.pending)
                            }}</span>
                            sin procesar
                        </li>
                        <li>
                            {{ formatBytes(props.migration.transferredBytes) }}
                            transferidos
                        </li>
                    </ul>

                    <div
                        v-if="props.migration.failures.length > 0"
                        class="overflow-x-auto rounded-md border"
                    >
                        <table class="w-full text-left text-sm">
                            <caption class="sr-only">
                                Archivos que no se pudieron mover
                            </caption>
                            <thead
                                class="bg-muted/50 text-xs text-muted-foreground"
                            >
                                <tr>
                                    <th
                                        scope="col"
                                        class="px-3 py-2 font-medium"
                                    >
                                        Archivo
                                    </th>
                                    <th
                                        scope="col"
                                        class="px-3 py-2 font-medium"
                                    >
                                        Motivo
                                    </th>
                                    <th
                                        scope="col"
                                        class="px-3 py-2 text-right font-medium"
                                    >
                                        Intentos
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="failure in props.migration.failures"
                                    :key="failure.id"
                                    class="border-t"
                                >
                                    <td class="px-3 py-2 font-medium break-all">
                                        {{ failure.filename }}
                                    </td>
                                    <td
                                        class="px-3 py-2 break-words text-muted-foreground"
                                    >
                                        {{ failure.error ?? 'Sin detalle' }}
                                    </td>
                                    <td
                                        class="px-3 py-2 text-right tabular-nums"
                                    >
                                        {{ failure.attempts }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

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
