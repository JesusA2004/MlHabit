<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    CheckCircle2,
    Circle,
    CloudOff,
    HardDrive,
    Info,
    Loader2,
    Lock,
    PlugZap,
    Power,
    Server,
    XCircle,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import ConfirmDialog from '@/components/ds/ConfirmDialog.vue';
import DateText from '@/components/ds/DateText.vue';
import PageContainer from '@/components/ds/PageContainer.vue';
import PageHeader from '@/components/ds/PageHeader.vue';
import StatusBadge from '@/components/ds/StatusBadge.vue';
import StatTile from '@/components/platform/dashboard/StatTile.vue';
import StorageMigrationPanel from '@/components/platform/storage/StorageMigrationPanel.vue';
import type {
    MigrationHistoryEntry,
    MigrationPlan,
    StorageMigration,
    StorageSummary,
} from '@/components/platform/storage/types';
import { Button } from '@/components/ui/button';
import { formatBytes, formatNumber } from '@/lib/format';
import { cn } from '@/lib/utils';
import almacenamiento from '@/routes/admin/almacenamiento';

interface TestResult {
    status: 'ok' | 'error' | 'disconnected';
    message: string;
    connect?: boolean;
    read?: boolean;
    write?: boolean;
    checkedAt: string;
}

type SynologyState = 'connected' | 'disconnected' | 'error';

interface SynologyInfo {
    name: string;
    host: string;
    protocol: 'SMB/CIFS';
    share: string;
    mountPath: string;
    state: SynologyState;
    message: string;
    mounted: boolean;
    capacity: {
        totalBytes: number;
        usedBytes: number;
        freeBytes: number;
    } | null;
    checkedAt: string | null;
    checks: {
        mount: boolean;
        reachable: boolean;
        write: boolean;
        read: boolean;
        delete: boolean;
    } | null;
    canActivate: boolean;
}

interface Provider {
    id: number;
    key: 'local' | 'synology';
    label: string;
    isActive: boolean;
    lastTestedAt: string | null;
    lastTestResult: TestResult | null;
    filesCount: number;
    filesBytes: number;
    space?: { totalBytes: number; freeBytes: number } | null;
    synology?: SynologyInfo;
}

const props = defineProps<{
    providers: Provider[];
    storage: StorageSummary;
    migration: StorageMigration | null;
    history: MigrationHistoryEntry[];
    plan: { toSynology: MigrationPlan; toLocal: MigrationPlan };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Panel', href: '/admin' },
            { title: 'Almacenamiento', href: '/admin/almacenamiento' },
        ],
    },
});

const TOUCH = 'pointer-coarse:h-11';

const synologyProvider = computed(
    () => props.providers.find((p) => p.key === 'synology') ?? null,
);
const synologyReady = computed(
    () => synologyProvider.value?.synology?.canActivate ?? false,
);
const synologyActive = computed(
    () => synologyProvider.value?.isActive ?? false,
);

const activeProvider = computed(
    () => props.providers.find((p) => p.isActive) ?? null,
);
const totalFiles = computed(() =>
    props.providers.reduce((sum, p) => sum + p.filesCount, 0),
);
const totalBytes = computed(() =>
    props.providers.reduce((sum, p) => sum + p.filesBytes, 0),
);

/** Documentos privados de clientes: siempre en el servidor. */
const privateDisk = computed(
    () => props.storage.disks.find((d) => d.disk === 'media_private') ?? null,
);

// ----------------------------------------------------------- Estado Synology

const STATE_META: Record<
    SynologyState,
    {
        label: string;
        tone: 'success' | 'warning' | 'danger';
        icon: typeof CheckCircle2;
    }
> = {
    connected: { label: 'Conectado', tone: 'success', icon: CheckCircle2 },
    disconnected: { label: 'Desconectado', tone: 'warning', icon: CloudOff },
    error: { label: 'Error', tone: 'danger', icon: XCircle },
};

const SYNOLOGY_CHECKS = [
    { k: 'mount', l: 'Montaje' },
    { k: 'reachable', l: 'Red' },
    { k: 'write', l: 'Escritura' },
    { k: 'read', l: 'Lectura' },
    { k: 'delete', l: 'Borrado' },
] as const;

function activateHint(info: SynologyInfo): string {
    if (!info.mounted) {
        return 'Se podrá activar cuando el NAS esté montado en el servidor.';
    }

    if (info.state !== 'connected') {
        return 'Se podrá activar cuando el NAS vuelva a conectar.';
    }

    return 'Pulsa «Probar conexión»; se habilita si la prueba sale bien.';
}

// ----------------------------------------------------------- Estado local

function localStatus(provider: Provider): {
    label: string;
    tone: 'success' | 'danger' | 'neutral';
    icon: typeof CheckCircle2;
    message: string;
} {
    const result = provider.lastTestResult;

    if (result?.status === 'ok') {
        return {
            label: 'Disponible',
            tone: 'success',
            icon: CheckCircle2,
            message: result.message,
        };
    }

    if (result) {
        return {
            label: 'Con error',
            tone: 'danger',
            icon: XCircle,
            message: result.message,
        };
    }

    return {
        label: 'Sin probar',
        tone: 'neutral',
        icon: Circle,
        message: 'Aún no se ha probado.',
    };
}

// ----------------------------------------------------------- Uso de disco

function clampPercent(value: number): number {
    return Math.min(100, Math.max(0, value));
}

function percentOf(part: number, total: number): number {
    return total > 0 ? clampPercent((part / total) * 100) : 0;
}

// ----------------------------------------------------------------- Acciones

const testing = ref<number | null>(null);

function testProvider(provider: Provider) {
    testing.value = provider.id;
    router.post(
        almacenamiento.probar(provider.id).url,
        {},
        {
            preserveScroll: true,
            onError: () => {
                toast.error('No se pudo ejecutar la prueba de conexión.');
            },
            onFinish: () => (testing.value = null),
        },
    );
}

const activating = ref<number | null>(null);
const activateTarget = ref<Provider | null>(null);
const activateOpen = ref(false);

function askActivate(provider: Provider) {
    activateTarget.value = provider;
    activateOpen.value = true;
}

function activateProvider(): Promise<void> {
    const provider = activateTarget.value;

    return new Promise((resolve, reject) => {
        if (!provider) {
            resolve();

            return;
        }

        activating.value = provider.id;
        router.post(
            almacenamiento.activar(provider.id).url,
            {},
            {
                preserveScroll: true,
                onSuccess: () => resolve(),
                onError: () =>
                    reject(new Error('No se pudo activar el proveedor.')),
                onFinish: () => (activating.value = null),
            },
        );
    });
}
</script>

<template>
    <Head title="Almacenamiento" />

    <PageContainer>
        <PageHeader
            title="Almacenamiento"
            description="Dónde se guardan fotos, renders, videos y documentos. Lo nuevo se sube al proveedor activo; si el Synology no responde, se guarda en el servidor y se pasa solo cuando vuelva."
        />

        <div class="grid gap-4 sm:grid-cols-3">
            <StatTile
                label="Proveedor activo"
                :value="activeProvider?.label ?? 'Ninguno'"
                hint="Recibe las subidas nuevas"
            />
            <StatTile label="Archivos guardados" :value="totalFiles" />
            <StatTile
                label="Espacio ocupado por los archivos"
                :value="formatBytes(totalBytes)"
            />
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            <template v-for="provider in props.providers" :key="provider.id">
                <!-- ============================== Synology (SMB/CIFS) -->
                <article
                    v-if="provider.key === 'synology' && provider.synology"
                    data-tour="storage-synology"
                    :class="
                        cn(
                            'flex min-w-0 flex-col gap-5 rounded-xl border bg-card p-5',
                            provider.isActive &&
                                'border-primary/50 ring-1 ring-primary/25',
                        )
                    "
                >
                    <header class="flex items-start gap-3">
                        <span
                            class="grid size-10 shrink-0 place-items-center rounded-lg"
                            :class="
                                provider.isActive
                                    ? 'bg-primary-soft text-primary'
                                    : 'bg-muted text-muted-foreground'
                            "
                        >
                            <Server class="size-5" />
                        </span>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <h2 class="text-base font-semibold">
                                    {{ provider.label }}
                                </h2>
                                <StatusBadge
                                    v-if="provider.isActive"
                                    tone="success"
                                    label="Activo"
                                    :icon="CheckCircle2"
                                />
                                <StatusBadge
                                    v-else
                                    tone="neutral"
                                    label="Inactivo"
                                />
                            </div>
                            <p class="mt-1 text-sm text-muted-foreground">
                                NAS de la oficina, conectado al servidor por
                                Tailscale. El acceso se administra en el
                                servidor, no aquí.
                            </p>
                        </div>
                    </header>

                    <!-- Estado -->
                    <section
                        class="rounded-lg border p-3"
                        :aria-label="`Estado de ${provider.label}`"
                        aria-live="polite"
                    >
                        <div class="flex flex-wrap items-center gap-2">
                            <StatusBadge
                                :tone="STATE_META[provider.synology.state].tone"
                                :icon="STATE_META[provider.synology.state].icon"
                                :label="
                                    STATE_META[provider.synology.state].label
                                "
                            />
                            <span class="text-xs text-muted-foreground">
                                Última comprobación:
                                <DateText
                                    :value="
                                        provider.synology.checkedAt ??
                                        provider.lastTestedAt
                                    "
                                    format="datetime"
                                    fallback="nunca"
                                />
                            </span>
                        </div>
                        <p
                            v-if="provider.synology.message"
                            class="mt-2 text-sm break-words"
                        >
                            {{ provider.synology.message }}
                        </p>
                        <ul
                            v-if="provider.synology.checks"
                            class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-xs"
                            aria-label="Resultado de la última prueba"
                        >
                            <li
                                v-for="check in SYNOLOGY_CHECKS"
                                :key="check.k"
                                class="inline-flex items-center gap-1.5"
                                :class="
                                    provider.synology.checks[check.k]
                                        ? 'text-success-ink'
                                        : 'text-danger-ink'
                                "
                            >
                                <CheckCircle2
                                    v-if="provider.synology.checks[check.k]"
                                    class="size-3.5"
                                />
                                <XCircle v-else class="size-3.5" />
                                {{ check.l }}
                                <span class="sr-only">{{
                                    provider.synology.checks[check.k]
                                        ? 'correcto'
                                        : 'falló'
                                }}</span>
                            </li>
                        </ul>
                        <p v-else class="mt-2 text-xs text-muted-foreground">
                            Aún no hay una prueba completa. Pulsa «Probar
                            conexión».
                        </p>
                    </section>

                    <!-- Datos de conexión -->
                    <dl
                        class="grid grid-cols-1 gap-x-6 gap-y-3 text-sm sm:grid-cols-2"
                    >
                        <div class="min-w-0">
                            <dt class="text-xs text-muted-foreground">
                                Nombre
                            </dt>
                            <dd class="font-medium break-words">
                                {{ provider.synology.name }}
                            </dd>
                        </div>
                        <div class="min-w-0">
                            <dt class="text-xs text-muted-foreground">
                                IP Tailscale
                            </dt>
                            <dd class="font-medium break-all tabular-nums">
                                {{ provider.synology.host }}
                            </dd>
                        </div>
                        <div class="min-w-0">
                            <dt class="text-xs text-muted-foreground">
                                Protocolo
                            </dt>
                            <dd class="font-medium">
                                {{ provider.synology.protocol }}
                            </dd>
                        </div>
                        <div class="min-w-0">
                            <dt class="text-xs text-muted-foreground">
                                Recurso compartido
                            </dt>
                            <dd class="font-medium break-words">
                                {{ provider.synology.share }}
                            </dd>
                        </div>
                        <div class="min-w-0 sm:col-span-2">
                            <dt class="text-xs text-muted-foreground">
                                Punto de montaje
                            </dt>
                            <dd
                                class="mt-0.5 flex flex-wrap items-center gap-2"
                            >
                                <code
                                    class="max-w-full rounded-md bg-muted px-2 py-1 text-xs break-all"
                                    >{{ provider.synology.mountPath }}</code
                                >
                                <span
                                    class="text-xs"
                                    :class="
                                        provider.synology.mounted
                                            ? 'text-success-ink'
                                            : 'text-danger-ink'
                                    "
                                >
                                    {{
                                        provider.synology.mounted
                                            ? 'Montado'
                                            : 'Sin montar'
                                    }}
                                </span>
                            </dd>
                        </div>
                    </dl>

                    <!-- Capacidad -->
                    <section
                        class="flex flex-col gap-2"
                        :aria-label="`Capacidad de ${provider.label}`"
                    >
                        <template v-if="provider.synology.capacity">
                            <div
                                class="relative h-2.5 w-full overflow-hidden rounded-full bg-muted"
                                role="meter"
                                :aria-label="`Disco ocupado en ${provider.label}`"
                                aria-valuemin="0"
                                aria-valuemax="100"
                                :aria-valuenow="
                                    Math.round(
                                        percentOf(
                                            provider.synology.capacity
                                                .usedBytes,
                                            provider.synology.capacity
                                                .totalBytes,
                                        ),
                                    )
                                "
                            >
                                <div
                                    class="absolute inset-y-0 left-0 rounded-full bg-foreground/25"
                                    :style="{
                                        width: `${percentOf(provider.synology.capacity.usedBytes, provider.synology.capacity.totalBytes)}%`,
                                    }"
                                />
                                <div
                                    class="absolute inset-y-0 left-0 rounded-full bg-primary"
                                    :style="{
                                        width: `${Math.max(percentOf(provider.filesBytes, provider.synology.capacity.totalBytes), provider.filesBytes > 0 ? 0.5 : 0)}%`,
                                    }"
                                />
                            </div>
                            <dl
                                class="grid grid-cols-3 gap-3 text-sm tabular-nums"
                            >
                                <div>
                                    <dt class="text-xs text-muted-foreground">
                                        Total
                                    </dt>
                                    <dd class="font-semibold">
                                        {{
                                            formatBytes(
                                                provider.synology.capacity
                                                    .totalBytes,
                                            )
                                        }}
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-xs text-muted-foreground">
                                        Usado
                                    </dt>
                                    <dd class="font-semibold">
                                        {{
                                            formatBytes(
                                                provider.synology.capacity
                                                    .usedBytes,
                                            )
                                        }}
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-xs text-muted-foreground">
                                        Disponible
                                    </dt>
                                    <dd class="font-semibold">
                                        {{
                                            formatBytes(
                                                provider.synology.capacity
                                                    .freeBytes,
                                            )
                                        }}
                                    </dd>
                                </div>
                            </dl>
                        </template>
                        <p v-else class="text-xs text-muted-foreground">
                            La capacidad se muestra cuando el NAS está
                            conectado.
                        </p>
                        <p
                            class="flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-muted-foreground"
                        >
                            <span class="inline-flex items-center gap-1.5"
                                ><span class="size-2 rounded-full bg-primary" />
                                Biblioteca multimedia:</span
                            >
                            <span
                                class="font-medium text-foreground tabular-nums"
                                >{{ formatNumber(provider.filesCount) }}
                                archivos ·
                                {{ formatBytes(provider.filesBytes) }}</span
                            >
                        </p>
                    </section>

                    <footer class="mt-auto flex flex-col gap-2 border-t pt-4">
                        <div class="flex flex-wrap items-center gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                :class="TOUCH"
                                :disabled="testing === provider.id"
                                :aria-busy="testing === provider.id"
                                @click="testProvider(provider)"
                            >
                                <Loader2
                                    v-if="testing === provider.id"
                                    class="size-4 animate-spin"
                                />
                                <PlugZap v-else class="size-4" />
                                {{
                                    testing === provider.id
                                        ? 'Probando…'
                                        : 'Probar conexión'
                                }}
                            </Button>
                            <Button
                                v-if="!provider.isActive"
                                variant="secondary"
                                size="sm"
                                :class="TOUCH"
                                :disabled="
                                    !provider.synology.canActivate ||
                                    activating === provider.id
                                "
                                :aria-describedby="
                                    provider.synology.canActivate
                                        ? undefined
                                        : `activate-hint-${provider.id}`
                                "
                                @click="askActivate(provider)"
                            >
                                <Power class="size-4" />
                                Activar proveedor
                            </Button>
                            <span v-else class="text-xs text-muted-foreground"
                                >Las subidas nuevas se guardan aquí.</span
                            >
                        </div>
                        <p
                            v-if="
                                !provider.isActive &&
                                !provider.synology.canActivate
                            "
                            :id="`activate-hint-${provider.id}`"
                            class="flex items-start gap-1.5 text-xs text-muted-foreground"
                        >
                            <Info class="mt-px size-3.5 shrink-0" />
                            {{ activateHint(provider.synology) }}
                        </p>
                    </footer>
                </article>

                <!-- ============================== Servidor (local) -->
                <article
                    v-else
                    data-tour="storage-local"
                    :class="
                        cn(
                            'flex min-w-0 flex-col gap-5 rounded-xl border bg-card p-5',
                            provider.isActive &&
                                'border-primary/50 ring-1 ring-primary/25',
                        )
                    "
                >
                    <header class="flex items-start gap-3">
                        <span
                            class="grid size-10 shrink-0 place-items-center rounded-lg"
                            :class="
                                provider.isActive
                                    ? 'bg-primary-soft text-primary'
                                    : 'bg-muted text-muted-foreground'
                            "
                        >
                            <HardDrive class="size-5" />
                        </span>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <h2 class="text-base font-semibold">
                                    {{ provider.label }}
                                </h2>
                                <StatusBadge
                                    v-if="provider.isActive"
                                    tone="success"
                                    label="Activo"
                                    :icon="CheckCircle2"
                                />
                                <StatusBadge
                                    v-else
                                    tone="neutral"
                                    label="Inactivo"
                                />
                            </div>
                            <p class="mt-1 text-sm text-muted-foreground">
                                Los archivos viven en el mismo servidor que la
                                plataforma. Es el respaldo siempre disponible.
                            </p>
                        </div>
                    </header>

                    <section
                        class="rounded-lg border p-3"
                        :aria-label="`Estado de ${provider.label}`"
                        aria-live="polite"
                    >
                        <div class="flex flex-wrap items-center gap-2">
                            <StatusBadge
                                :tone="localStatus(provider).tone"
                                :icon="localStatus(provider).icon"
                                :label="localStatus(provider).label"
                            />
                            <span class="text-xs text-muted-foreground">
                                Última comprobación:
                                <DateText
                                    :value="provider.lastTestedAt"
                                    format="datetime"
                                    fallback="nunca"
                                />
                            </span>
                        </div>
                        <p class="mt-2 text-sm break-words">
                            {{ localStatus(provider).message }}
                        </p>
                    </section>

                    <section
                        class="flex flex-col gap-2"
                        :aria-label="`Uso de ${provider.label}`"
                    >
                        <div
                            class="flex items-baseline justify-between gap-3 text-sm"
                        >
                            <span
                                ><span class="font-semibold tabular-nums">{{
                                    formatNumber(provider.filesCount)
                                }}</span>
                                archivos</span
                            >
                            <span class="text-muted-foreground tabular-nums">{{
                                formatBytes(provider.filesBytes)
                            }}</span>
                        </div>
                        <template v-if="provider.space">
                            <div
                                class="relative h-2.5 w-full overflow-hidden rounded-full bg-muted"
                                role="meter"
                                :aria-label="`Disco ocupado en ${provider.label}`"
                                aria-valuemin="0"
                                aria-valuemax="100"
                                :aria-valuenow="
                                    Math.round(
                                        percentOf(
                                            provider.space.totalBytes -
                                                provider.space.freeBytes,
                                            provider.space.totalBytes,
                                        ),
                                    )
                                "
                            >
                                <div
                                    class="absolute inset-y-0 left-0 rounded-full bg-foreground/25"
                                    :style="{
                                        width: `${percentOf(provider.space.totalBytes - provider.space.freeBytes, provider.space.totalBytes)}%`,
                                    }"
                                />
                                <div
                                    class="absolute inset-y-0 left-0 rounded-full bg-primary"
                                    :style="{
                                        width: `${Math.max(percentOf(provider.filesBytes, provider.space.totalBytes), provider.filesBytes > 0 ? 0.5 : 0)}%`,
                                    }"
                                />
                            </div>
                            <div
                                class="flex flex-wrap items-center justify-between gap-x-4 gap-y-1 text-xs text-muted-foreground"
                            >
                                <span class="inline-flex items-center gap-1.5"
                                    ><span
                                        class="size-2 rounded-full bg-primary"
                                    />
                                    Archivos de la plataforma</span
                                >
                                <span class="inline-flex items-center gap-1.5"
                                    ><span
                                        class="size-2 rounded-full bg-foreground/25"
                                    />
                                    Otros datos del disco</span
                                >
                                <span class="tabular-nums">
                                    {{
                                        formatBytes(
                                            provider.space.totalBytes -
                                                provider.space.freeBytes,
                                        )
                                    }}
                                    usados de
                                    {{ formatBytes(provider.space.totalBytes) }}
                                    ·
                                    {{ formatBytes(provider.space.freeBytes) }}
                                    libres
                                </span>
                            </div>
                        </template>
                        <p v-else class="text-xs text-muted-foreground">
                            No se pudo leer el espacio del disco.
                        </p>
                    </section>

                    <p
                        v-if="privateDisk"
                        class="flex items-start gap-2 rounded-md bg-muted px-3 py-2 text-xs text-muted-foreground"
                    >
                        <Lock class="mt-px size-3.5 shrink-0" />
                        <span>
                            <span class="font-medium text-foreground"
                                >{{ privateDisk.label }}:</span
                            >
                            {{ formatNumber(privateDisk.files) }} archivos ·
                            {{ formatBytes(privateDisk.bytes) }}. Siempre se
                            quedan en el servidor.
                        </span>
                    </p>

                    <footer
                        class="mt-auto flex flex-wrap items-center gap-2 border-t pt-4"
                    >
                        <Button
                            variant="outline"
                            size="sm"
                            :class="TOUCH"
                            :disabled="testing === provider.id"
                            :aria-busy="testing === provider.id"
                            @click="testProvider(provider)"
                        >
                            <Loader2
                                v-if="testing === provider.id"
                                class="size-4 animate-spin"
                            />
                            <PlugZap v-else class="size-4" />
                            {{
                                testing === provider.id
                                    ? 'Probando…'
                                    : 'Probar conexión'
                            }}
                        </Button>
                        <Button
                            v-if="!provider.isActive"
                            variant="secondary"
                            size="sm"
                            :class="TOUCH"
                            :disabled="activating === provider.id"
                            @click="askActivate(provider)"
                        >
                            <Power class="size-4" />
                            Activar proveedor
                        </Button>
                        <span v-else class="text-xs text-muted-foreground"
                            >Las subidas nuevas se guardan aquí.</span
                        >
                    </footer>
                </article>
            </template>
        </div>

        <StorageMigrationPanel
            data-tour="storage-migration"
            :storage="props.storage"
            :migration="props.migration"
            :history="props.history"
            :plan="props.plan"
            :synology-ready="synologyReady"
            :synology-active="synologyActive"
        />
    </PageContainer>

    <ConfirmDialog
        v-model:open="activateOpen"
        :title="`Activar ${activateTarget?.label ?? ''}`"
        description="Los archivos nuevos se guardarán en este proveedor. Los que ya existen se quedan donde están y siguen disponibles; puedes moverlos después con «Mover multimedia»."
        confirm-label="Activar"
        loading-label="Activando…"
        :confirm="activateProvider"
    />
</template>
