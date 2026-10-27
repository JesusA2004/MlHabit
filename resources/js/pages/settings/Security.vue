<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3';
import {
    Check,
    Copy,
    KeyRound,
    Laptop,
    LogOut,
    RefreshCw,
    ShieldAlert,
    ShieldCheck,
    Smartphone,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import FormSection from '@/components/admin/FormSection.vue';
import ConfirmDialog from '@/components/ds/ConfirmDialog.vue';
import DateText from '@/components/ds/DateText.vue';
import StatusBadge from '@/components/ds/StatusBadge.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { apiFetch } from '@/lib/http';
import {
    confirm as confirmPasswordRoute,
    confirmation as passwordConfirmationStatus,
} from '@/routes/password';
import { edit as editProfile } from '@/routes/profile';
import { edit } from '@/routes/security';
import { destroyOthers } from '@/routes/sessions';
import {
    confirm as confirmTwoFactor,
    disable as disableTwoFactor,
    enable as enableTwoFactor,
    qrCode,
    recoveryCodes as recoveryCodesRoute,
    regenerateRecoveryCodes,
    secretKey as secretKeyRoute,
} from '@/routes/two-factor';
import { update as updatePassword } from '@/routes/user-password';

type SessionItem = {
    id: string;
    device: string;
    browser: string;
    platform: string;
    isMobile: boolean;
    ipAddress: string | null;
    isCurrent: boolean;
    lastActiveAt: string;
};

const props = defineProps<{
    passwordRules: string;
    twoFactorEnabled: boolean;
    twoFactorPending: boolean;
    requiresConfirmation: boolean;
    twoFactorRequired: boolean;
    sessions: SessionItem[];
    sessionsSupported: boolean;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Mi cuenta', href: editProfile() },
            { title: 'Seguridad', href: edit() },
        ],
    },
});

/* ------------------------------------------------------------------ */
/* Confirmación de contraseña (Fortify `password.confirm`)             */
/* ------------------------------------------------------------------ */

const confirmOpen = ref(false);
const confirmPassword = ref('');
const confirmError = ref<string | null>(null);
const confirmProcessing = ref(false);
let confirmResolver: ((ok: boolean) => void) | null = null;

async function ensurePasswordConfirmed(): Promise<boolean> {
    try {
        const response = await apiFetch(passwordConfirmationStatus().url);
        const data = (await response.json()) as { confirmed?: boolean };

        if (data.confirmed) {
            return true;
        }
    } catch {
        // Si falla la consulta, pedimos la contraseña de todos modos.
    }

    confirmPassword.value = '';
    confirmError.value = null;
    confirmOpen.value = true;

    return new Promise<boolean>((resolve) => {
        confirmResolver = resolve;
    });
}

async function submitPasswordConfirmation() {
    confirmProcessing.value = true;
    confirmError.value = null;

    try {
        const response = await apiFetch(confirmPasswordRoute().url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ password: confirmPassword.value }),
        });

        if (response.ok) {
            confirmOpen.value = false;
            confirmResolver?.(true);
            confirmResolver = null;

            return;
        }

        const data = (await response.json().catch(() => ({}))) as {
            errors?: Record<string, string[]>;
        };
        confirmError.value =
            data.errors?.password?.[0] ?? 'La contraseña no es correcta.';
    } catch {
        confirmError.value =
            'No pudimos verificar tu contraseña. Revisa tu conexión e inténtalo de nuevo.';
    } finally {
        confirmProcessing.value = false;
    }
}

function onConfirmOpenChange(value: boolean) {
    if (confirmProcessing.value) {
        return;
    }

    confirmOpen.value = value;

    if (!value && confirmResolver) {
        confirmResolver(false);
        confirmResolver = null;
    }
}

/* ------------------------------------------------------------------ */
/* Verificación en dos pasos                                           */
/* ------------------------------------------------------------------ */

const enabling = ref(false);
const qrSvg = ref<string | null>(null);
const secret = ref<string | null>(null);
const recoveryCodes = ref<string[]>([]);
const showRecoveryCodes = ref(false);
const code = ref('');
const codeError = ref<string | null>(null);
const confirmingCode = ref(false);
const disableOpen = ref(false);
const regenerating = ref(false);

const setupInProgress = computed(
    () => props.twoFactorPending || qrSvg.value !== null,
);

async function readJson<T>(url: string): Promise<T | null> {
    const response = await apiFetch(url);

    return response.ok ? ((await response.json()) as T) : null;
}

async function loadSetupData() {
    const [qr, key] = await Promise.all([
        readJson<{ svg: string }>(qrCode().url),
        readJson<{ secretKey: string }>(secretKeyRoute().url),
    ]);

    qrSvg.value = qr?.svg ?? null;
    secret.value = key?.secretKey ?? null;
}

async function loadRecoveryCodes() {
    recoveryCodes.value =
        (await readJson<string[]>(recoveryCodesRoute().url)) ?? [];
}

async function startEnable() {
    if (!(await ensurePasswordConfirmed())) {
        return;
    }

    enabling.value = true;

    try {
        const response = await apiFetch(enableTwoFactor().url, {
            method: 'POST',
        });

        if (!response.ok) {
            toast.error(
                'No pudimos iniciar la configuración. Inténtalo de nuevo.',
            );

            return;
        }

        await loadSetupData();
        router.reload({ only: ['twoFactorEnabled', 'twoFactorPending'] });
    } finally {
        enabling.value = false;
    }
}

async function resumeSetup() {
    if (!(await ensurePasswordConfirmed())) {
        return;
    }

    await loadSetupData();
}

async function submitCode() {
    confirmingCode.value = true;
    codeError.value = null;

    try {
        const response = await apiFetch(confirmTwoFactor().url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ code: code.value.replace(/\s+/g, '') }),
        });

        if (!response.ok) {
            const data = (await response.json().catch(() => ({}))) as {
                errors?: Record<string, string[]>;
            };
            codeError.value =
                data.errors?.code?.[0] ??
                'El código no es válido. Revisa la hora de tu teléfono e inténtalo de nuevo.';

            return;
        }

        code.value = '';
        qrSvg.value = null;
        secret.value = null;
        await loadRecoveryCodes();
        showRecoveryCodes.value = true;
        toast.success('Verificación en dos pasos activada.');
        router.reload({ only: ['twoFactorEnabled', 'twoFactorPending'] });
    } finally {
        confirmingCode.value = false;
    }
}

async function revealRecoveryCodes() {
    if (showRecoveryCodes.value) {
        showRecoveryCodes.value = false;

        return;
    }

    if (!(await ensurePasswordConfirmed())) {
        return;
    }

    await loadRecoveryCodes();
    showRecoveryCodes.value = true;
}

async function regenerateCodes() {
    if (!(await ensurePasswordConfirmed())) {
        return;
    }

    regenerating.value = true;

    try {
        const response = await apiFetch(regenerateRecoveryCodes().url, {
            method: 'POST',
        });

        if (response.ok) {
            await loadRecoveryCodes();
            showRecoveryCodes.value = true;
            toast.success(
                'Generamos códigos de recuperación nuevos. Los anteriores ya no sirven.',
            );
        }
    } finally {
        regenerating.value = false;
    }
}

async function openDisable() {
    if (await ensurePasswordConfirmed()) {
        disableOpen.value = true;
    }
}

async function disable() {
    const response = await apiFetch(disableTwoFactor().url, {
        method: 'DELETE',
    });

    if (!response.ok) {
        throw new Error(
            'No pudimos desactivar la verificación. Inténtalo de nuevo.',
        );
    }

    qrSvg.value = null;
    secret.value = null;
    recoveryCodes.value = [];
    showRecoveryCodes.value = false;
    toast.success('Verificación en dos pasos desactivada.');
    router.reload({ only: ['twoFactorEnabled', 'twoFactorPending'] });
}

async function copy(text: string, label: string) {
    try {
        await navigator.clipboard.writeText(text);
        toast.success(`${label} copiado.`);
    } catch {
        toast.error(
            'No se pudo copiar. Selecciona el texto y cópialo manualmente.',
        );
    }
}

/* ------------------------------------------------------------------ */
/* Sesiones                                                            */
/* ------------------------------------------------------------------ */

const sessionsOpen = ref(false);
const sessionsPassword = ref('');
const sessionsError = ref<string | null>(null);
const sessionsProcessing = ref(false);
const otherSessions = computed(
    () => props.sessions.filter((s) => !s.isCurrent).length,
);

function openSessions() {
    sessionsPassword.value = '';
    sessionsError.value = null;
    sessionsOpen.value = true;
}

function closeOtherSessions() {
    router.delete(destroyOthers().url, {
        data: { password: sessionsPassword.value },
        preserveScroll: true,
        onStart: () => (sessionsProcessing.value = true),
        onFinish: () => (sessionsProcessing.value = false),
        onSuccess: () => {
            sessionsOpen.value = false;
        },
        onError: (errors) => {
            sessionsError.value =
                errors.password ??
                'No pudimos cerrar las sesiones. Inténtalo de nuevo.';
        },
    });
}
</script>

<template>
    <Head title="Seguridad" />

    <Heading
        title="Seguridad"
        description="Protege el acceso a tu cuenta: contraseña, verificación en dos pasos y sesiones abiertas."
    />

    <div
        v-if="props.twoFactorRequired && !props.twoFactorEnabled"
        role="alert"
        class="flex items-start gap-3 rounded-xl border border-warning/30 bg-warning-soft p-4 text-sm text-warning-ink"
    >
        <ShieldAlert class="mt-0.5 size-5 shrink-0" />
        <div>
            <p class="font-medium">
                Necesitas activar la verificación en dos pasos.
            </p>
            <p>
                ML Habitat la exige al equipo interno. Mientras no la actives no
                podrás entrar al panel.
            </p>
        </div>
    </div>

    <!-- Contraseña -->
    <div class="rounded-xl border bg-card text-card-foreground shadow-xs">
        <Form
            :action="updatePassword()"
            :options="{ preserveScroll: true }"
            reset-on-success
            :reset-on-error="[
                'password',
                'password_confirmation',
                'current_password',
            ]"
            v-slot="{ errors, processing, recentlySuccessful }"
        >
            <div class="px-4 sm:px-6">
                <FormSection
                    title="Contraseña"
                    description="Usa una frase larga y única que no utilices en otros sitios."
                >
                    <template #aside>
                        <div
                            class="mt-4 hidden items-start gap-2 rounded-lg bg-muted/60 p-3 text-xs text-muted-foreground lg:flex"
                        >
                            <KeyRound
                                class="mt-0.5 size-4 shrink-0 text-primary"
                            />
                            <span
                                >Ejemplo: «roble-claro-cocina-de-domingo». Un
                                gestor de contraseñas te ayuda a
                                recordarla.</span
                            >
                        </div>
                    </template>

                    <div class="grid gap-2">
                        <Label for="current_password">Contraseña actual</Label>
                        <PasswordInput
                            id="current_password"
                            name="current_password"
                            class="h-10"
                            autocomplete="current-password"
                            :aria-invalid="
                                errors.current_password ? true : undefined
                            "
                        />
                        <InputError :message="errors.current_password" />
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid content-start gap-2">
                            <Label for="password">Nueva contraseña</Label>
                            <PasswordInput
                                id="password"
                                name="password"
                                class="h-10"
                                autocomplete="new-password"
                                :passwordrules="props.passwordRules"
                                :aria-invalid="
                                    errors.password ? true : undefined
                                "
                            />
                            <InputError :message="errors.password" />
                        </div>

                        <div class="grid content-start gap-2">
                            <Label for="password_confirmation"
                                >Confirma la nueva contraseña</Label
                            >
                            <PasswordInput
                                id="password_confirmation"
                                name="password_confirmation"
                                class="h-10"
                                autocomplete="new-password"
                                :passwordrules="props.passwordRules"
                                :aria-invalid="
                                    errors.password_confirmation
                                        ? true
                                        : undefined
                                "
                            />
                            <InputError
                                :message="errors.password_confirmation"
                            />
                        </div>
                    </div>
                </FormSection>
            </div>

            <footer
                class="flex items-center justify-end gap-3 rounded-b-xl border-t bg-muted/40 px-4 py-3 sm:px-6"
            >
                <p
                    v-show="recentlySuccessful"
                    class="flex items-center gap-1.5 text-sm text-success-ink"
                    role="status"
                >
                    <Check class="size-4" /> Contraseña actualizada
                </p>
                <Button
                    type="submit"
                    :disabled="processing"
                    :aria-busy="processing"
                    data-test="update-password-button"
                >
                    <Spinner v-if="processing" />
                    {{ processing ? 'Actualizando…' : 'Actualizar contraseña' }}
                </Button>
            </footer>
        </Form>
    </div>

    <!-- Verificación en dos pasos -->
    <div
        class="rounded-xl border bg-card px-4 text-card-foreground shadow-xs sm:px-6"
    >
        <FormSection
            title="Verificación en dos pasos"
            description="Además de tu contraseña, te pediremos un código de 6 dígitos de una app como Google Authenticator, Microsoft Authenticator o 1Password."
        >
            <div class="flex flex-wrap items-center gap-3">
                <StatusBadge
                    v-if="props.twoFactorEnabled"
                    tone="success"
                    :icon="ShieldCheck"
                    label="Activa"
                />
                <StatusBadge
                    v-else-if="setupInProgress"
                    tone="warning"
                    label="Configuración pendiente"
                />
                <StatusBadge v-else tone="neutral" label="Desactivada" />
            </div>

            <!-- Sin activar -->
            <div
                v-if="!props.twoFactorEnabled && !setupInProgress"
                class="flex flex-col gap-3"
            >
                <p class="text-sm text-muted-foreground">
                    Si alguien obtiene tu contraseña, no podrá entrar sin el
                    código de tu teléfono.
                </p>
                <div>
                    <Button
                        type="button"
                        :disabled="enabling"
                        :aria-busy="enabling"
                        @click="startEnable"
                    >
                        <Spinner v-if="enabling" />
                        {{ enabling ? 'Preparando…' : 'Activar verificación' }}
                    </Button>
                </div>
            </div>

            <!-- Configuración en curso -->
            <div
                v-else-if="!props.twoFactorEnabled"
                class="flex flex-col gap-4"
            >
                <template v-if="qrSvg">
                    <ol
                        class="list-decimal space-y-1 pl-5 text-sm text-muted-foreground"
                    >
                        <li>
                            Abre tu app de autenticación y escanea el código QR.
                        </li>
                        <li>
                            Escribe el código de 6 dígitos que aparece en la
                            app.
                        </li>
                    </ol>
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start">
                        <!--
                            El SVG lo genera nuestro propio backend (Fortify + BaconQrCode)
                            a partir del secreto del usuario; no contiene datos de terceros,
                            por eso se inserta con v-html.
                        -->
                        <div
                            class="w-fit shrink-0 rounded-lg border bg-white p-3 [&_svg]:size-44"
                            role="img"
                            aria-label="Código QR para tu app de autenticación"
                            v-html="qrSvg"
                        />
                        <div
                            v-if="secret"
                            class="flex min-w-0 flex-col gap-2 text-sm"
                        >
                            <p class="text-muted-foreground">
                                ¿No puedes escanear? Escribe esta clave en la
                                app:
                            </p>
                            <div class="flex items-center gap-2">
                                <code
                                    class="min-w-0 truncate rounded-md bg-muted px-2 py-1 font-mono text-xs tabular-nums"
                                    >{{ secret }}</code
                                >
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    aria-label="Copiar clave"
                                    @click="copy(secret, 'Clave')"
                                >
                                    <Copy class="size-4" />
                                </Button>
                            </div>
                        </div>
                    </div>

                    <form
                        class="flex flex-col gap-2 sm:max-w-xs"
                        @submit.prevent="submitCode"
                    >
                        <Label for="two_factor_code"
                            >Código de verificación</Label
                        >
                        <Input
                            id="two_factor_code"
                            v-model="code"
                            inputmode="numeric"
                            autocomplete="one-time-code"
                            maxlength="7"
                            placeholder="123456"
                            class="h-10 tracking-widest tabular-nums"
                            :aria-invalid="codeError ? true : undefined"
                        />
                        <InputError :message="codeError ?? undefined" />
                        <div>
                            <Button
                                type="submit"
                                :disabled="
                                    confirmingCode || code.trim().length < 6
                                "
                                :aria-busy="confirmingCode"
                            >
                                <Spinner v-if="confirmingCode" />
                                {{
                                    confirmingCode
                                        ? 'Confirmando…'
                                        : 'Confirmar y activar'
                                }}
                            </Button>
                        </div>
                    </form>
                </template>
                <div v-else class="flex flex-col gap-3">
                    <p class="text-sm text-muted-foreground">
                        Empezaste a configurar la verificación pero falta
                        confirmar el código.
                    </p>
                    <div>
                        <Button type="button" @click="resumeSetup"
                            >Continuar configuración</Button
                        >
                    </div>
                </div>
            </div>

            <!-- Activa -->
            <div v-else class="flex flex-col gap-4">
                <p class="text-sm text-muted-foreground">
                    Guarda tus códigos de recuperación en un lugar seguro. Cada
                    uno sirve una sola vez si pierdes tu teléfono.
                </p>

                <div
                    v-if="showRecoveryCodes && recoveryCodes.length"
                    class="rounded-lg border bg-muted/50 p-3"
                >
                    <ul
                        class="grid grid-cols-1 gap-1 font-mono text-sm tabular-nums sm:grid-cols-2"
                        aria-label="Códigos de recuperación"
                    >
                        <li v-for="item in recoveryCodes" :key="item">
                            {{ item }}
                        </li>
                    </ul>
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        class="mt-2"
                        @click="copy(recoveryCodes.join('\n'), 'Códigos')"
                    >
                        <Copy class="size-4" /> Copiar códigos
                    </Button>
                </div>

                <div class="flex flex-wrap gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        @click="revealRecoveryCodes"
                    >
                        {{
                            showRecoveryCodes
                                ? 'Ocultar códigos'
                                : 'Ver códigos de recuperación'
                        }}
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        :disabled="regenerating"
                        :aria-busy="regenerating"
                        @click="regenerateCodes"
                    >
                        <Spinner v-if="regenerating" />
                        <RefreshCw v-else class="size-4" />
                        Generar códigos nuevos
                    </Button>
                    <Button
                        v-if="!props.twoFactorRequired"
                        type="button"
                        variant="ghost"
                        class="text-danger-ink hover:text-danger-ink"
                        @click="openDisable"
                    >
                        Desactivar
                    </Button>
                </div>
                <p
                    v-if="props.twoFactorRequired"
                    class="text-xs text-muted-foreground"
                >
                    La verificación es obligatoria para el equipo; no se puede
                    desactivar desde aquí.
                </p>
            </div>
        </FormSection>
    </div>

    <!-- Sesiones -->
    <div
        class="rounded-xl border bg-card px-4 text-card-foreground shadow-xs sm:px-6"
    >
        <FormSection
            title="Sesiones abiertas"
            description="Dispositivos donde tu cuenta tiene una sesión activa. Si no reconoces alguno, cierra las demás sesiones y cambia tu contraseña."
        >
            <p
                v-if="!props.sessionsSupported"
                class="rounded-lg bg-muted/60 p-3 text-sm text-muted-foreground"
            >
                La lista de sesiones solo está disponible cuando el servidor
                guarda las sesiones en la base de datos (<code
                    class="font-mono text-xs"
                    >SESSION_DRIVER=database</code
                >). Aun así puedes cerrar tus otras sesiones.
            </p>

            <ul
                v-else-if="props.sessions.length"
                class="divide-y rounded-lg border"
            >
                <li
                    v-for="session in props.sessions"
                    :key="session.id"
                    class="flex items-center gap-3 px-3 py-3"
                >
                    <component
                        :is="session.isMobile ? Smartphone : Laptop"
                        class="size-5 shrink-0 text-muted-foreground"
                    />
                    <div class="min-w-0 flex-1">
                        <p
                            class="flex flex-wrap items-center gap-2 text-sm font-medium"
                        >
                            {{ session.browser }} en {{ session.platform }}
                            <StatusBadge
                                v-if="session.isCurrent"
                                tone="brand"
                                size="sm"
                                label="Este dispositivo"
                            />
                        </p>
                        <p class="text-xs text-muted-foreground">
                            <span v-if="session.ipAddress" class="tabular-nums"
                                >{{ session.ipAddress }} ·
                            </span>
                            <span v-if="session.isCurrent">Activa ahora</span>
                            <template v-else
                                >Última actividad
                                <DateText
                                    :value="session.lastActiveAt"
                                    relative
                            /></template>
                        </p>
                    </div>
                </li>
            </ul>

            <div>
                <Button
                    type="button"
                    variant="outline"
                    :disabled="props.sessionsSupported && otherSessions === 0"
                    @click="openSessions"
                >
                    <LogOut class="size-4" />
                    Cerrar otras sesiones
                </Button>
                <p
                    v-if="props.sessionsSupported && otherSessions === 0"
                    class="mt-2 text-xs text-muted-foreground"
                >
                    Solo tienes la sesión de este dispositivo.
                </p>
            </div>
        </FormSection>
    </div>

    <!-- Diálogo: confirmar contraseña -->
    <Dialog :open="confirmOpen" @update:open="onConfirmOpenChange">
        <DialogContent class="sm:max-w-md">
            <form
                class="flex flex-col gap-4"
                @submit.prevent="submitPasswordConfirmation"
            >
                <DialogHeader>
                    <DialogTitle>Confirma tu contraseña</DialogTitle>
                    <DialogDescription
                        >Por seguridad, escribe tu contraseña para
                        continuar.</DialogDescription
                    >
                </DialogHeader>
                <div class="grid gap-2">
                    <Label for="confirm_password">Contraseña</Label>
                    <PasswordInput
                        id="confirm_password"
                        v-model="confirmPassword"
                        autocomplete="current-password"
                        class="h-10"
                        :aria-invalid="confirmError ? true : undefined"
                    />
                    <InputError :message="confirmError ?? undefined" />
                </div>
                <DialogFooter class="gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        :disabled="confirmProcessing"
                        @click="onConfirmOpenChange(false)"
                        >Cancelar</Button
                    >
                    <Button
                        type="submit"
                        :disabled="confirmProcessing || !confirmPassword"
                        :aria-busy="confirmProcessing"
                    >
                        <Spinner v-if="confirmProcessing" />
                        {{ confirmProcessing ? 'Verificando…' : 'Continuar' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <!-- Diálogo: cerrar otras sesiones -->
    <Dialog v-model:open="sessionsOpen">
        <DialogContent class="sm:max-w-md">
            <form
                class="flex flex-col gap-4"
                @submit.prevent="closeOtherSessions"
            >
                <DialogHeader>
                    <DialogTitle>Cerrar otras sesiones</DialogTitle>
                    <DialogDescription>
                        Se cerrará tu cuenta en todos los demás dispositivos.
                        Esta sesión seguirá abierta.
                    </DialogDescription>
                </DialogHeader>
                <div class="grid gap-2">
                    <Label for="sessions_password">Contraseña</Label>
                    <PasswordInput
                        id="sessions_password"
                        v-model="sessionsPassword"
                        autocomplete="current-password"
                        class="h-10"
                        :aria-invalid="sessionsError ? true : undefined"
                    />
                    <InputError :message="sessionsError ?? undefined" />
                </div>
                <DialogFooter class="gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        :disabled="sessionsProcessing"
                        @click="sessionsOpen = false"
                        >Cancelar</Button
                    >
                    <Button
                        type="submit"
                        :disabled="sessionsProcessing || !sessionsPassword"
                        :aria-busy="sessionsProcessing"
                    >
                        <Spinner v-if="sessionsProcessing" />
                        {{
                            sessionsProcessing ? 'Cerrando…' : 'Cerrar sesiones'
                        }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <ConfirmDialog
        v-model:open="disableOpen"
        title="¿Desactivar la verificación en dos pasos?"
        description="Tu cuenta quedará protegida solo con la contraseña. Tus códigos de recuperación dejarán de funcionar."
        confirm-label="Desactivar"
        loading-label="Desactivando…"
        tone="destructive"
        :confirm="disable"
    />
</template>
