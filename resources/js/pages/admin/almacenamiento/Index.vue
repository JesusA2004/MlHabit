<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    CheckCircle2,
    Circle,
    Clock,
    Files,
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
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';
import FormSection from '@/components/admin/FormSection.vue';
import StatCard from '@/components/admin/StatCard.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { cn } from '@/lib/utils';
import almacenamiento from '@/routes/admin/almacenamiento';
import type { BreadcrumbItem } from '@/types';

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
            { title: 'Almacenamiento', href: almacenamiento.index() },
        ] as BreadcrumbItem[],
    },
});

const TOUCH = 'pointer-coarse:h-11';
const TOUCH_SELECT = 'pointer-coarse:data-[size=default]:h-11';

function formatBytes(bytes: number): string {
    if (bytes <= 0) return '0 B';
    const units = ['B', 'KB', 'MB', 'GB', 'TB'];
    const exp = Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), units.length - 1);

    return `${(bytes / 1024 ** exp).toFixed(exp === 0 ? 0 : 1)} ${units[exp]}`;
}

function formatDate(iso: string | null): string {
    if (!iso) return 'Nunca';

    return new Intl.DateTimeFormat('es-MX', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(iso));
}

const DESCRIPTIONS: Record<Provider['key'], string> = {
    local: 'Los archivos viven en el mismo servidor que el sitio. Es el respaldo siempre disponible.',
    synology: 'NAS Synology en casa, conectado por SFTP a través de Tailscale. Ideal para videos pesados y respaldos.',
};

const activeProvider = computed(() => props.providers.find((p) => p.isActive) ?? null);
const totalFiles = computed(() => props.providers.reduce((sum, p) => sum + p.filesCount, 0));
const totalBytes = computed(() => props.providers.reduce((sum, p) => sum + p.filesBytes, 0));
const synologyProviders = computed(() => props.providers.filter((p) => p.key === 'synology'));

/** Estado de conexión legible a partir de la última prueba guardada. */
function connection(provider: Provider): { label: string; tone: 'success' | 'error' | 'pending' | 'idle'; message: string } {
    const result = provider.lastTestResult;
    if (result?.status === 'ok') return { label: 'Conectado', tone: 'success', message: result.message };
    if (result?.status === 'error') return { label: 'Error de conexión', tone: 'error', message: result.message };
    if (provider.key === 'synology') {
        return { label: 'Pendiente', tone: 'pending', message: result?.message ?? 'Synology pendiente de configuración.' };
    }

    return { label: 'Sin probar', tone: 'idle', message: 'Aún no se ha probado la conexión.' };
}

const TONE_CLASSES = {
    success: 'border-transparent bg-success/12 text-success',
    error: 'border-transparent bg-destructive/10 text-destructive',
    pending: 'border-transparent bg-warning/15 text-warning',
    idle: 'border-transparent bg-muted text-muted-foreground',
} as const;

function usedPercent(space: { totalBytes: number; freeBytes: number }): number {
    if (space.totalBytes <= 0) return 0;

    return Math.min(100, Math.max(0, ((space.totalBytes - space.freeBytes) / space.totalBytes) * 100));
}

function libraryPercent(provider: Provider): number {
    if (!provider.space || provider.space.totalBytes <= 0) return 0;

    return Math.min(100, (provider.filesBytes / provider.space.totalBytes) * 100);
}

// ----------------------------------------------------------------- Acciones

const testing = ref<number | null>(null);
function testProvider(provider: Provider) {
    testing.value = provider.id;
    router.post(almacenamiento.probar(provider.id).url, {}, {
        preserveScroll: true,
        onSuccess: () => {
            const result = props.providers.find((p) => p.id === provider.id)?.lastTestResult;
            if (!result) return;
            if (result.status === 'ok') toast.success(result.message);
            else if (result.status === 'pending') toast.warning(result.message);
            else toast.error(result.message);
        },
        onError: () => {
            toast.error('No se pudo ejecutar la prueba de conexión.');
        },
        onFinish: () => (testing.value = null),
    });
}

const activating = ref<number | null>(null);
function activateProvider(provider: Provider) {
    activating.value = provider.id;
    router.post(almacenamiento.activar(provider.id).url, {}, {
        preserveScroll: true,
        onError: () => {
            toast.error('No se pudo activar el proveedor.');
        },
        onFinish: () => (activating.value = null),
    });
}

const synologyForms = new Map<number, ReturnType<typeof useForm>>();
function synologyForm(provider: Provider) {
    if (!synologyForms.has(provider.id)) {
        synologyForms.set(
            provider.id,
            useForm({
                host: provider.config?.host ?? '',
                port: provider.config?.port ?? 22,
                username: provider.config?.username ?? '',
                auth_method: provider.config?.authMethod ?? 'password',
                password: '',
                private_key_path: '',
                transfer_limit_kbps: provider.config?.transferLimitKbps ?? null,
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
        },
        onError: () => {
            toast.error('Revisa los campos marcados.');
        },
    });
}
</script>

<template>
    <Head title="Almacenamiento" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6 lg:p-8">
        <AdminPageHeader
            eyebrow="Sistema"
            title="Almacenamiento"
            description="Dónde se guardan las fotografías y videos de la biblioteca. Los archivos nuevos se suben al proveedor activo; los existentes se quedan donde están."
        />

        <div class="grid gap-4 sm:grid-cols-3">
            <StatCard label="Proveedor activo" :value="activeProvider?.label ?? 'Ninguno'" :icon="Power" tone="brand" hint="Recibe las subidas nuevas" />
            <StatCard label="Archivos en la biblioteca" :value="totalFiles" :icon="Files" />
            <StatCard label="Espacio ocupado por la biblioteca" :value="formatBytes(totalBytes)" :icon="HardDrive" />
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            <article
                v-for="provider in props.providers"
                :key="provider.id"
                :class="cn('flex flex-col gap-5 rounded-xl border bg-card p-5', provider.isActive && 'border-brand/60 ring-1 ring-brand/30')"
            >
                <header class="flex items-start gap-3">
                    <span class="grid size-10 shrink-0 place-items-center rounded-lg" :class="provider.isActive ? 'bg-brand/15 text-brand' : 'bg-muted text-muted-foreground'">
                        <component :is="provider.key === 'local' ? HardDrive : Server" class="size-5" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="text-base font-semibold">{{ provider.label }}</h2>
                            <Badge v-if="provider.isActive" variant="outline" :class="TONE_CLASSES.success">
                                <CheckCircle2 /> Activo
                            </Badge>
                            <Badge v-else variant="outline" :class="TONE_CLASSES.idle">Inactivo</Badge>
                        </div>
                        <p class="mt-1 text-sm text-muted-foreground">{{ DESCRIPTIONS[provider.key] }}</p>
                    </div>
                </header>

                <!-- Estado de conexión -->
                <section class="rounded-lg border p-3" :aria-label="`Conexión de ${provider.label}`" aria-live="polite">
                    <div class="flex flex-wrap items-center gap-2">
                        <Badge variant="outline" :class="TONE_CLASSES[connection(provider).tone]">
                            <CheckCircle2 v-if="connection(provider).tone === 'success'" />
                            <XCircle v-else-if="connection(provider).tone === 'error'" />
                            <Clock v-else-if="connection(provider).tone === 'pending'" />
                            <Circle v-else />
                            {{ connection(provider).label }}
                        </Badge>
                        <span class="text-xs text-muted-foreground">Última verificación: {{ formatDate(provider.lastTestedAt) }}</span>
                    </div>
                    <p class="mt-2 text-sm">{{ connection(provider).message }}</p>
                    <ul v-if="provider.lastTestResult && provider.lastTestResult.status !== 'pending'" class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-xs">
                        <li
                            v-for="check in [
                                { k: 'connect', l: 'Conexión' },
                                { k: 'read', l: 'Lectura' },
                                { k: 'write', l: 'Escritura' },
                            ] as const"
                            :key="check.k"
                            class="inline-flex items-center gap-1.5"
                            :class="provider.lastTestResult[check.k] ? 'text-success' : 'text-destructive'"
                        >
                            <CheckCircle2 v-if="provider.lastTestResult[check.k]" class="size-3.5" />
                            <XCircle v-else class="size-3.5" />
                            {{ check.l }}
                        </li>
                    </ul>
                </section>

                <!-- Uso -->
                <section class="flex flex-col gap-2" :aria-label="`Uso de ${provider.label}`">
                    <div class="flex items-baseline justify-between gap-3 text-sm">
                        <span><span class="font-semibold tabular-nums">{{ provider.filesCount }}</span> archivos</span>
                        <span class="text-muted-foreground tabular-nums">{{ formatBytes(provider.filesBytes) }}</span>
                    </div>
                    <template v-if="provider.space">
                        <div
                            class="relative h-2.5 w-full overflow-hidden rounded-full bg-muted"
                            role="meter"
                            :aria-label="`Disco ocupado en ${provider.label}`"
                            aria-valuemin="0"
                            aria-valuemax="100"
                            :aria-valuenow="Math.round(usedPercent(provider.space))"
                        >
                            <div class="absolute inset-y-0 left-0 rounded-full bg-foreground/25" :style="{ width: `${usedPercent(provider.space)}%` }" />
                            <div class="absolute inset-y-0 left-0 rounded-full bg-brand" :style="{ width: `${Math.max(libraryPercent(provider), provider.filesBytes > 0 ? 0.5 : 0)}%` }" />
                        </div>
                        <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-1 text-xs text-muted-foreground">
                            <span class="inline-flex items-center gap-1.5"><span class="size-2 rounded-full bg-brand" /> Biblioteca</span>
                            <span class="inline-flex items-center gap-1.5"><span class="size-2 rounded-full bg-foreground/25" /> Otros datos del disco</span>
                            <span class="tabular-nums">
                                {{ formatBytes(provider.space.totalBytes - provider.space.freeBytes) }} usados de {{ formatBytes(provider.space.totalBytes) }}
                                · {{ formatBytes(provider.space.freeBytes) }} libres
                            </span>
                        </div>
                    </template>
                    <p v-else class="text-xs text-muted-foreground">La capacidad del disco se mostrará cuando el proveedor esté conectado.</p>
                </section>

                <footer class="mt-auto flex flex-wrap items-center gap-2 border-t pt-4">
                    <Button
                        variant="outline"
                        size="sm"
                        :class="TOUCH"
                        :disabled="testing === provider.id"
                        :aria-busy="testing === provider.id"
                        @click="testProvider(provider)"
                    >
                        <Loader2 v-if="testing === provider.id" class="size-4 animate-spin" />
                        <PlugZap v-else class="size-4" />
                        {{ testing === provider.id ? 'Probando…' : 'Probar conexión' }}
                    </Button>
                    <Button
                        v-if="!provider.isActive"
                        variant="secondary"
                        size="sm"
                        :class="TOUCH"
                        :disabled="activating === provider.id"
                        @click="activateProvider(provider)"
                    >
                        <Loader2 v-if="activating === provider.id" class="size-4 animate-spin" />
                        <Power v-else class="size-4" />
                        Activar proveedor
                    </Button>
                    <span v-else class="text-xs text-muted-foreground">Las subidas nuevas se guardan aquí.</span>
                </footer>
            </article>
        </div>

        <!-- Configuración de Synology -->
        <section v-for="provider in synologyProviders" :key="`cfg-${provider.id}`" class="rounded-xl border bg-card px-5">
            <FormSection
                title="Conexión con Synology"
                description="Datos del NAS dentro de tu red Tailscale. Las credenciales se guardan cifradas en el servidor y nunca se muestran de nuevo."
            >
                <template #aside>
                    <p class="mt-3 flex items-start gap-1.5 text-xs text-muted-foreground">
                        <ShieldCheck class="mt-px size-3.5 shrink-0 text-success" />
                        Guarda y después usa «Probar conexión» para verificar lectura y escritura.
                    </p>
                </template>

                <form class="grid gap-4 sm:grid-cols-6" @submit.prevent="saveSynology(provider)">
                    <div class="grid gap-1.5 sm:col-span-4">
                        <Label :for="`nas-host-${provider.id}`">Host (Tailscale)</Label>
                        <Input :id="`nas-host-${provider.id}`" v-model="synologyForm(provider).host" :class="TOUCH" placeholder="nas.tailnet.ts.net" autocomplete="off" />
                        <InputError :message="synologyForm(provider).errors.host" />
                    </div>
                    <div class="grid gap-1.5 sm:col-span-2">
                        <Label :for="`nas-port-${provider.id}`">Puerto</Label>
                        <Input :id="`nas-port-${provider.id}`" v-model="synologyForm(provider).port" type="number" :class="TOUCH" />
                        <InputError :message="synologyForm(provider).errors.port" />
                    </div>
                    <div class="grid gap-1.5 sm:col-span-3">
                        <Label :for="`nas-user-${provider.id}`">Usuario</Label>
                        <Input :id="`nas-user-${provider.id}`" v-model="synologyForm(provider).username" :class="TOUCH" autocomplete="off" />
                        <InputError :message="synologyForm(provider).errors.username" />
                    </div>
                    <div class="grid gap-1.5 sm:col-span-3">
                        <Label :for="`nas-auth-${provider.id}`">Autenticación</Label>
                        <Select v-model="synologyForm(provider).auth_method">
                            <SelectTrigger :id="`nas-auth-${provider.id}`" :class="cn('w-full', TOUCH_SELECT)"><SelectValue /></SelectTrigger>
                            <SelectContent>
                                <SelectItem value="password">Contraseña</SelectItem>
                                <SelectItem value="private_key">Llave privada</SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="synologyForm(provider).errors.auth_method" />
                    </div>
                    <div v-if="synologyForm(provider).auth_method === 'password'" class="grid gap-1.5 sm:col-span-6">
                        <Label :for="`nas-pass-${provider.id}`">Contraseña</Label>
                        <Input
                            :id="`nas-pass-${provider.id}`"
                            v-model="synologyForm(provider).password"
                            type="password"
                            autocomplete="new-password"
                            :class="TOUCH"
                            :placeholder="provider.config?.hasPassword ? '••••••••  (guardada)' : ''"
                        />
                        <p v-if="provider.config?.hasPassword" class="text-xs text-muted-foreground">Ya hay una contraseña guardada. Déjalo en blanco para conservarla.</p>
                        <InputError :message="synologyForm(provider).errors.password" />
                    </div>
                    <div v-else class="grid gap-1.5 sm:col-span-6">
                        <Label :for="`nas-key-${provider.id}`">Ruta de la llave privada en el servidor</Label>
                        <Input
                            :id="`nas-key-${provider.id}`"
                            v-model="synologyForm(provider).private_key_path"
                            :class="TOUCH"
                            placeholder="/etc/mrinsight/nas_id_ed25519"
                            autocomplete="off"
                        />
                        <p class="text-xs text-muted-foreground">
                            {{ provider.config?.hasPrivateKey ? 'Ya hay una llave guardada. Déjalo en blanco para conservarla.' : 'Ruta absoluta del archivo de llave en el servidor web.' }}
                        </p>
                        <InputError :message="synologyForm(provider).errors.private_key_path" />
                    </div>
                    <div class="grid gap-1.5 sm:col-span-3">
                        <Label :for="`nas-limit-${provider.id}`">Límite de transferencia (KB/s)</Label>
                        <Input
                            :id="`nas-limit-${provider.id}`"
                            v-model="synologyForm(provider).transfer_limit_kbps"
                            type="number"
                            :class="TOUCH"
                            placeholder="Sin límite"
                        />
                        <p class="text-xs text-muted-foreground">Útil para no saturar el internet de casa. Ej.: 2048.</p>
                        <InputError :message="synologyForm(provider).errors.transfer_limit_kbps" />
                    </div>
                    <div class="grid gap-1.5 sm:col-span-3">
                        <span class="text-sm font-medium">Carpeta raíz autorizada</span>
                        <code class="rounded-md bg-muted px-2.5 py-2 text-xs break-all">{{ provider.config?.rootPath ?? 'Sin definir' }}</code>
                        <p class="text-xs text-muted-foreground">Se define en las variables de entorno del servidor, no desde aquí.</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2 sm:col-span-6">
                        <Button type="submit" :class="TOUCH" :disabled="synologyForm(provider).processing" :aria-busy="synologyForm(provider).processing">
                            <Loader2 v-if="synologyForm(provider).processing" class="size-4 animate-spin" />
                            Guardar configuración
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            :class="TOUCH"
                            :disabled="testing === provider.id || synologyForm(provider).isDirty"
                            :title="synologyForm(provider).isDirty ? 'Guarda los cambios antes de probar' : undefined"
                            @click="testProvider(provider)"
                        >
                            <Loader2 v-if="testing === provider.id" class="size-4 animate-spin" />
                            <PlugZap v-else class="size-4" />
                            Probar conexión
                        </Button>
                        <span v-if="synologyForm(provider).recentlySuccessful" class="inline-flex items-center gap-1.5 text-xs text-success" role="status">
                            <CheckCircle2 class="size-3.5" /> Guardado
                        </span>
                    </div>
                </form>
            </FormSection>
        </section>
    </div>
</template>
