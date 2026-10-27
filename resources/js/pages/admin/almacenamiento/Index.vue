<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    CheckCircle2,
    Circle,
    Clock,
    HardDrive,
    Loader2,
    PlugZap,
    Power,
    Server,
    ShieldCheck,
    XCircle,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import FormSection from '@/components/admin/FormSection.vue';
import ConfirmDialog from '@/components/ds/ConfirmDialog.vue';
import DateText from '@/components/ds/DateText.vue';
import PageContainer from '@/components/ds/PageContainer.vue';
import PageHeader from '@/components/ds/PageHeader.vue';
import StatusBadge from '@/components/ds/StatusBadge.vue';
import InputError from '@/components/InputError.vue';
import StatTile from '@/components/platform/dashboard/StatTile.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { formatBytes } from '@/lib/format';
import { cn } from '@/lib/utils';
import almacenamiento from '@/routes/admin/almacenamiento';

interface TestResult {
    status: 'ok' | 'error' | 'pending';
    message: string;
    connect: boolean;
    read: boolean;
    write: boolean;
    checkedAt: string;
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
    space: { totalBytes: number; freeBytes: number } | null;
    config: {
        host: string | null;
        port: number;
        username: string | null;
        authMethod: 'password' | 'private_key';
        hasPassword: boolean;
        hasPrivateKey: boolean;
        transferLimitKbps: number | null;
        rootPath: string | null;
    } | null;
}

const props = defineProps<{ providers: Provider[] }>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Panel', href: '/admin' },
            { title: 'Almacenamiento', href: '/admin/almacenamiento' },
        ],
    },
});

const TOUCH = 'pointer-coarse:h-11';
const TOUCH_SELECT = 'pointer-coarse:data-[size=default]:h-11';

const DESCRIPTIONS: Record<Provider['key'], string> = {
    local: 'Los archivos viven en el mismo servidor que la plataforma. Es el respaldo siempre disponible.',
    synology:
        'NAS Synology de ML Habitat, conectado por SFTP a través de Tailscale. Ideal para videos de obra, renders pesados y respaldos.',
};

const activeProvider = computed(
    () => props.providers.find((p) => p.isActive) ?? null,
);
const totalFiles = computed(() =>
    props.providers.reduce((sum, p) => sum + p.filesCount, 0),
);
const totalBytes = computed(() =>
    props.providers.reduce((sum, p) => sum + p.filesBytes, 0),
);
const synologyProviders = computed(() =>
    props.providers.filter((p) => p.key === 'synology'),
);

type ConnectionTone = 'success' | 'error' | 'pending' | 'idle';

/** Estado de conexión legible a partir de la última prueba guardada. */
function connection(provider: Provider): {
    label: string;
    tone: ConnectionTone;
    message: string;
} {
    const result = provider.lastTestResult;

    if (result?.status === 'ok') {
        return { label: 'Conectado', tone: 'success', message: result.message };
    }

    if (result?.status === 'error') {
        return {
            label: 'Error de conexión',
            tone: 'error',
            message: result.message,
        };
    }

    if (provider.key === 'synology') {
        return {
            label: 'Pendiente',
            tone: 'pending',
            message: result?.message ?? 'Synology pendiente de configuración.',
        };
    }

    return {
        label: 'Sin probar',
        tone: 'idle',
        message: 'Aún no se ha probado la conexión.',
    };
}

const CONNECTION_TONE = {
    success: 'success',
    error: 'danger',
    pending: 'warning',
    idle: 'neutral',
} as const;
const CONNECTION_ICON = {
    success: CheckCircle2,
    error: XCircle,
    pending: Clock,
    idle: Circle,
} as const;

function usedPercent(space: { totalBytes: number; freeBytes: number }): number {
    if (space.totalBytes <= 0) {
        return 0;
    }

    return Math.min(
        100,
        Math.max(
            0,
            ((space.totalBytes - space.freeBytes) / space.totalBytes) * 100,
        ),
    );
}

function libraryPercent(provider: Provider): number {
    if (!provider.space || provider.space.totalBytes <= 0) {
        return 0;
    }

    return Math.min(
        100,
        (provider.filesBytes / provider.space.totalBytes) * 100,
    );
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

type SynologyForm = {
    host: string;
    port: number;
    username: string;
    auth_method: 'password' | 'private_key';
    password: string;
    private_key_path: string;
    transfer_limit_kbps: number | '';
};

const synologyForms = new Map<
    number,
    ReturnType<typeof useForm<SynologyForm>>
>();

function synologyForm(provider: Provider) {
    if (!synologyForms.has(provider.id)) {
        synologyForms.set(
            provider.id,
            useForm<SynologyForm>({
                host: provider.config?.host ?? '',
                port: provider.config?.port ?? 22,
                username: provider.config?.username ?? '',
                auth_method: provider.config?.authMethod ?? 'password',
                password: '',
                private_key_path: '',
                transfer_limit_kbps: provider.config?.transferLimitKbps ?? '',
            }),
        );
    }

    return synologyForms.get(provider.id)!;
}

function saveSynology(provider: Provider) {
    const form = synologyForm(provider);
    form.put(almacenamiento.update(provider.id).url, {
        preserveScroll: true,
        onSuccess: () => {
            form.password = '';
            form.private_key_path = '';
            form.defaults();
        },
    });
}
</script>

<template>
    <Head title="Almacenamiento" />

    <PageContainer>
        <PageHeader
            title="Almacenamiento"
            description="Dónde se guardan fotos, renders, videos y documentos. Los archivos nuevos se suben al proveedor activo; los existentes se quedan donde están."
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
            <article
                v-for="provider in props.providers"
                :key="provider.id"
                :class="
                    cn(
                        'flex flex-col gap-5 rounded-xl border bg-card p-5',
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
                        <component
                            :is="provider.key === 'local' ? HardDrive : Server"
                            class="size-5"
                        />
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
                            {{ DESCRIPTIONS[provider.key] }}
                        </p>
                    </div>
                </header>

                <!-- Estado de conexión -->
                <section
                    class="rounded-lg border p-3"
                    :aria-label="`Conexión de ${provider.label}`"
                    aria-live="polite"
                >
                    <div class="flex flex-wrap items-center gap-2">
                        <StatusBadge
                            :tone="CONNECTION_TONE[connection(provider).tone]"
                            :icon="CONNECTION_ICON[connection(provider).tone]"
                            :label="connection(provider).label"
                        />
                        <span class="text-xs text-muted-foreground">
                            Última verificación:
                            <DateText
                                :value="provider.lastTestedAt"
                                format="datetime"
                                fallback="nunca"
                            />
                        </span>
                    </div>
                    <p class="mt-2 text-sm">
                        {{ connection(provider).message }}
                    </p>
                    <ul
                        v-if="
                            provider.lastTestResult &&
                            provider.lastTestResult.status !== 'pending'
                        "
                        class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-xs"
                    >
                        <li
                            v-for="check in [
                                { k: 'connect', l: 'Conexión' },
                                { k: 'read', l: 'Lectura' },
                                { k: 'write', l: 'Escritura' },
                            ] as const"
                            :key="check.k"
                            class="inline-flex items-center gap-1.5"
                            :class="
                                provider.lastTestResult[check.k]
                                    ? 'text-success-ink'
                                    : 'text-danger-ink'
                            "
                        >
                            <CheckCircle2
                                v-if="provider.lastTestResult[check.k]"
                                class="size-3.5"
                            />
                            <XCircle v-else class="size-3.5" />
                            {{ check.l }}
                        </li>
                    </ul>
                </section>

                <!-- Uso -->
                <section
                    class="flex flex-col gap-2"
                    :aria-label="`Uso de ${provider.label}`"
                >
                    <div
                        class="flex items-baseline justify-between gap-3 text-sm"
                    >
                        <span
                            ><span class="font-semibold tabular-nums">{{
                                provider.filesCount
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
                                Math.round(usedPercent(provider.space))
                            "
                        >
                            <div
                                class="absolute inset-y-0 left-0 rounded-full bg-foreground/25"
                                :style="{
                                    width: `${usedPercent(provider.space)}%`,
                                }"
                            />
                            <div
                                class="absolute inset-y-0 left-0 rounded-full bg-primary"
                                :style="{
                                    width: `${Math.max(libraryPercent(provider), provider.filesBytes > 0 ? 0.5 : 0)}%`,
                                }"
                            />
                        </div>
                        <div
                            class="flex flex-wrap items-center justify-between gap-x-4 gap-y-1 text-xs text-muted-foreground"
                        >
                            <span class="inline-flex items-center gap-1.5"
                                ><span class="size-2 rounded-full bg-primary" />
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
                                {{ formatBytes(provider.space.totalBytes) }} ·
                                {{ formatBytes(provider.space.freeBytes) }}
                                libres
                            </span>
                        </div>
                    </template>
                    <p v-else class="text-xs text-muted-foreground">
                        La capacidad del disco se mostrará cuando el proveedor
                        esté conectado.
                    </p>
                </section>

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
        </div>

        <!-- Configuración de Synology -->
        <section
            v-for="provider in synologyProviders"
            :key="`cfg-${provider.id}`"
            class="rounded-xl border bg-card px-5"
        >
            <FormSection
                title="Conexión con Synology"
                description="Datos del NAS dentro de la red Tailscale. Las credenciales se guardan cifradas en el servidor y nunca se muestran de nuevo."
            >
                <template #aside>
                    <p
                        class="mt-3 flex items-start gap-1.5 text-xs text-muted-foreground"
                    >
                        <ShieldCheck
                            class="mt-px size-3.5 shrink-0 text-success"
                        />
                        Guarda y después usa «Probar conexión» para verificar
                        lectura y escritura. Solo podrás activarlo si la prueba
                        sale bien.
                    </p>
                </template>

                <form
                    class="grid gap-4 sm:grid-cols-6"
                    @submit.prevent="saveSynology(provider)"
                >
                    <div class="grid gap-1.5 sm:col-span-4">
                        <Label :for="`nas-host-${provider.id}`"
                            >Host (Tailscale)</Label
                        >
                        <Input
                            :id="`nas-host-${provider.id}`"
                            v-model="synologyForm(provider).host"
                            :class="TOUCH"
                            placeholder="nas.tailnet.ts.net"
                            autocomplete="off"
                        />
                        <InputError
                            :message="synologyForm(provider).errors.host"
                        />
                    </div>
                    <div class="grid gap-1.5 sm:col-span-2">
                        <Label :for="`nas-port-${provider.id}`">Puerto</Label>
                        <Input
                            :id="`nas-port-${provider.id}`"
                            v-model.number="synologyForm(provider).port"
                            type="number"
                            :class="TOUCH"
                        />
                        <InputError
                            :message="synologyForm(provider).errors.port"
                        />
                    </div>
                    <div class="grid gap-1.5 sm:col-span-3">
                        <Label :for="`nas-user-${provider.id}`">Usuario</Label>
                        <Input
                            :id="`nas-user-${provider.id}`"
                            v-model="synologyForm(provider).username"
                            :class="TOUCH"
                            autocomplete="off"
                        />
                        <InputError
                            :message="synologyForm(provider).errors.username"
                        />
                    </div>
                    <div class="grid gap-1.5 sm:col-span-3">
                        <Label :for="`nas-auth-${provider.id}`"
                            >Autenticación</Label
                        >
                        <Select v-model="synologyForm(provider).auth_method">
                            <SelectTrigger
                                :id="`nas-auth-${provider.id}`"
                                :class="cn('w-full', TOUCH_SELECT)"
                                ><SelectValue
                            /></SelectTrigger>
                            <SelectContent>
                                <SelectItem value="password"
                                    >Contraseña</SelectItem
                                >
                                <SelectItem value="private_key"
                                    >Llave privada</SelectItem
                                >
                            </SelectContent>
                        </Select>
                        <InputError
                            :message="synologyForm(provider).errors.auth_method"
                        />
                    </div>
                    <div
                        v-if="synologyForm(provider).auth_method === 'password'"
                        class="grid gap-1.5 sm:col-span-6"
                    >
                        <Label :for="`nas-pass-${provider.id}`"
                            >Contraseña</Label
                        >
                        <Input
                            :id="`nas-pass-${provider.id}`"
                            v-model="synologyForm(provider).password"
                            type="password"
                            autocomplete="new-password"
                            :class="TOUCH"
                            :placeholder="
                                provider.config?.hasPassword
                                    ? '••••••••  (guardada)'
                                    : ''
                            "
                        />
                        <p
                            v-if="provider.config?.hasPassword"
                            class="text-xs text-muted-foreground"
                        >
                            Ya hay una contraseña guardada. Déjalo en blanco
                            para conservarla.
                        </p>
                        <InputError
                            :message="synologyForm(provider).errors.password"
                        />
                    </div>
                    <div v-else class="grid gap-1.5 sm:col-span-6">
                        <Label :for="`nas-key-${provider.id}`"
                            >Ruta de la llave privada en el servidor</Label
                        >
                        <Input
                            :id="`nas-key-${provider.id}`"
                            v-model="synologyForm(provider).private_key_path"
                            :class="TOUCH"
                            placeholder="/etc/mlhabitat/nas_id_ed25519"
                            autocomplete="off"
                        />
                        <p class="text-xs text-muted-foreground">
                            {{
                                provider.config?.hasPrivateKey
                                    ? 'Ya hay una llave guardada. Déjalo en blanco para conservarla.'
                                    : 'Ruta absoluta del archivo de llave en el servidor web.'
                            }}
                        </p>
                        <InputError
                            :message="
                                synologyForm(provider).errors.private_key_path
                            "
                        />
                    </div>
                    <div class="grid gap-1.5 sm:col-span-3">
                        <Label :for="`nas-limit-${provider.id}`"
                            >Límite de transferencia (KB/s)</Label
                        >
                        <Input
                            :id="`nas-limit-${provider.id}`"
                            v-model.number="
                                synologyForm(provider).transfer_limit_kbps
                            "
                            type="number"
                            :class="TOUCH"
                            placeholder="Sin límite"
                        />
                        <p class="text-xs text-muted-foreground">
                            Útil para no saturar el internet de la oficina. Ej.:
                            2048.
                        </p>
                        <InputError
                            :message="
                                synologyForm(provider).errors
                                    .transfer_limit_kbps
                            "
                        />
                    </div>
                    <div class="grid gap-1.5 sm:col-span-3">
                        <span class="text-sm font-medium"
                            >Carpeta raíz autorizada</span
                        >
                        <code
                            class="rounded-md bg-muted px-2.5 py-2 text-xs break-all"
                            >{{
                                provider.config?.rootPath ?? 'Sin definir'
                            }}</code
                        >
                        <p class="text-xs text-muted-foreground">
                            Se define en las variables de entorno del servidor
                            (NAS_SFTP_ROOT), no desde aquí.
                        </p>
                    </div>
                    <div
                        class="flex flex-wrap items-center gap-2 sm:col-span-6"
                    >
                        <Button
                            type="submit"
                            :class="TOUCH"
                            :disabled="synologyForm(provider).processing"
                            :aria-busy="synologyForm(provider).processing"
                        >
                            <Loader2
                                v-if="synologyForm(provider).processing"
                                class="size-4 animate-spin"
                            />
                            {{
                                synologyForm(provider).processing
                                    ? 'Guardando…'
                                    : 'Guardar configuración'
                            }}
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            :class="TOUCH"
                            :disabled="
                                testing === provider.id ||
                                synologyForm(provider).isDirty
                            "
                            :title="
                                synologyForm(provider).isDirty
                                    ? 'Guarda los cambios antes de probar'
                                    : undefined
                            "
                            @click="testProvider(provider)"
                        >
                            <Loader2
                                v-if="testing === provider.id"
                                class="size-4 animate-spin"
                            />
                            <PlugZap v-else class="size-4" />
                            Probar conexión
                        </Button>
                    </div>
                </form>
            </FormSection>
        </section>
    </PageContainer>

    <ConfirmDialog
        v-model:open="activateOpen"
        :title="`Activar ${activateTarget?.label ?? ''}`"
        description="Los archivos nuevos se guardarán en este proveedor. Los que ya existen se quedan donde están y siguen disponibles."
        confirm-label="Activar"
        loading-label="Activando…"
        :confirm="activateProvider"
    />
</template>
