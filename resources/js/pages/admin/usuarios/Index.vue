<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    KeyRound,
    MailPlus,
    MoreHorizontal,
    Pencil,
    ShieldCheck,
    ShieldOff,
    UserPlus,
    Users,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import ConfirmDialog from '@/components/ds/ConfirmDialog.vue';
import DataView from '@/components/ds/DataView.vue';
import DateText from '@/components/ds/DateText.vue';
import EmptyState from '@/components/ds/EmptyState.vue';
import FilterBar from '@/components/ds/FilterBar.vue';
import FormSheet from '@/components/ds/FormSheet.vue';
import PageContainer from '@/components/ds/PageContainer.vue';
import PageHeader from '@/components/ds/PageHeader.vue';
import StatusBadge from '@/components/ds/StatusBadge.vue';
import type { DataColumn } from '@/components/ds/types';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { Switch } from '@/components/ui/switch';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import usuarios from '@/routes/admin/usuarios';
import type { LengthAwarePaginator } from '@/types/ui';

type UserRow = {
    id: number;
    name: string;
    email: string;
    phone: string | null;
    role: string;
    roleLabel: string;
    isStaff: boolean;
    isActive: boolean;
    twoFactorEnabled: boolean;
    invitationPending: boolean;
    lastLoginAt: string | null;
    createdAt: string | null;
    isSelf: boolean;
    canEdit: boolean;
};

type RoleOption = {
    value: string;
    label: string;
    description: string;
    isStaff: boolean;
};

const props = defineProps<{
    users: LengthAwarePaginator<UserRow>;
    filters: { q: string; rol: string; estado: string; tipo: string };
    roles: RoleOption[];
    assignableRoles: string[];
    counts: { equipo: number; clientes: number; tecnicos: number };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Panel', href: '/admin' },
            { title: 'Usuarios', href: '/admin/usuarios' },
        ],
    },
});

const ALL = '__todos';

const search = ref(props.filters.q);
const role = ref(props.filters.rol || ALL);
const status = ref(props.filters.estado || ALL);
const type = ref(props.filters.tipo || 'equipo');

const activeCount = computed(
    () => (role.value !== ALL ? 1 : 0) + (status.value !== ALL ? 1 : 0),
);

function reload() {
    router.get(
        usuarios.index().url,
        {
            q: search.value || undefined,
            rol: role.value !== ALL ? role.value : undefined,
            estado: status.value !== ALL ? status.value : undefined,
            tipo: type.value,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: ['users', 'filters'],
        },
    );
}

watch([search, role, status, type], reload);

function clearFilters() {
    role.value = ALL;
    status.value = ALL;
    search.value = '';
}

const roleOptions = computed(() =>
    props.roles.filter((r) =>
        type.value === 'equipo'
            ? r.isStaff
            : type.value === 'clientes'
              ? r.value === 'cliente'
              : type.value === 'tecnicos'
                ? r.value === 'tecnico'
                : true,
    ),
);
const staffRoles = computed(() =>
    props.roles.filter((r) => props.assignableRoles.includes(r.value)),
);

const columns: DataColumn<UserRow>[] = [
    { key: 'name', label: 'Nombre', primary: true },
    { key: 'role', label: 'Rol' },
    { key: 'status', label: 'Estado' },
    { key: 'twoFactor', label: 'Dos pasos', hideOnMobile: true },
    { key: 'lastLoginAt', label: 'Último acceso' },
];

// ---- Invitar ----
const inviteOpen = ref(false);
const inviteForm = useForm({ name: '', email: '', phone: '', role: '' });

function openInvite() {
    inviteForm.reset();
    inviteForm.clearErrors();
    inviteForm.role =
        staffRoles.value.find((r) => r.value === 'comercial')?.value ??
        staffRoles.value[0]?.value ??
        '';
    inviteOpen.value = true;
}

function submitInvite() {
    inviteForm.post(usuarios.store().url, {
        preserveScroll: true,
        onSuccess: () => {
            inviteOpen.value = false;
            inviteForm.reset();
        },
    });
}

// ---- Editar ----
const editOpen = ref(false);
const editing = ref<UserRow | null>(null);
const editForm = useForm({ name: '', phone: '', role: '', is_active: true });

function openEdit(user: UserRow) {
    editing.value = user;
    editForm.clearErrors();
    editForm.name = user.name;
    editForm.phone = user.phone ?? '';
    editForm.role = user.role;
    editForm.is_active = user.isActive;
    editOpen.value = true;
}

const editRoleOptions = computed(() => {
    if (!editing.value) {
        return [];
    }

    if (!editing.value.isStaff) {
        return props.roles.filter((r) => r.value === editing.value?.role);
    }

    const options = staffRoles.value;

    return options.some((r) => r.value === editing.value?.role)
        ? options
        : [
              ...options,
              ...props.roles.filter((r) => r.value === editing.value?.role),
          ];
});

function submitEdit() {
    if (!editing.value) {
        return;
    }

    const payload: Record<string, unknown> = {
        name: editForm.name,
        phone: editForm.phone || null,
        is_active: editForm.is_active,
    };

    if (editing.value.role !== editForm.role) {
        payload.role = editForm.role;
    }

    editForm
        .transform(() => payload)
        .patch(usuarios.update(editing.value.id).url, {
            preserveScroll: true,
            onSuccess: () => {
                editOpen.value = false;
            },
        });
}

// ---- Acciones con confirmación ----
const confirmTarget = ref<UserRow | null>(null);
const confirmKind = ref<
    'invite' | 'twoFactor' | 'deactivate' | 'activate' | null
>(null);
const confirmOpen = ref(false);

function ask(kind: NonNullable<typeof confirmKind.value>, user: UserRow) {
    confirmTarget.value = user;
    confirmKind.value = kind;
    confirmOpen.value = true;
}

const confirmCopy = computed(() => {
    const name = confirmTarget.value?.name ?? '';

    switch (confirmKind.value) {
        case 'invite':
            return {
                title: 'Reenviar invitación',
                description: `Enviaremos a ${name} un nuevo enlace para crear su contraseña. Vence en 72 horas.`,
                label: 'Reenviar',
                loading: 'Enviando…',
                tone: 'default' as const,
            };
        case 'twoFactor':
            return {
                title: 'Restablecer verificación en dos pasos',
                description: `${name} tendrá que configurarla de nuevo y se cerrarán sus sesiones abiertas. Hazlo solo si perdió su teléfono o sus códigos.`,
                label: 'Restablecer',
                loading: 'Restableciendo…',
                tone: 'destructive' as const,
            };
        case 'deactivate':
            return {
                title: 'Desactivar cuenta',
                description: `${name} ya no podrá iniciar sesión y se cerrarán sus sesiones. Su historial se conserva y puedes reactivarla cuando quieras.`,
                label: 'Desactivar',
                loading: 'Desactivando…',
                tone: 'destructive' as const,
            };
        case 'activate':
            return {
                title: 'Reactivar cuenta',
                description: `${name} podrá volver a iniciar sesión con su contraseña.`,
                label: 'Reactivar',
                loading: 'Reactivando…',
                tone: 'default' as const,
            };
        default:
            return {
                title: '',
                description: '',
                label: 'Confirmar',
                loading: 'Procesando…',
                tone: 'default' as const,
            };
    }
});

function runConfirm(): Promise<void> {
    const user = confirmTarget.value;
    const kind = confirmKind.value;

    return new Promise((resolve, reject) => {
        if (!user || !kind) {
            resolve();

            return;
        }

        const options = {
            preserveScroll: true,
            onSuccess: () => resolve(),
            onError: (errors: Record<string, string>) =>
                reject(
                    new Error(
                        Object.values(errors)[0] ??
                            'No se pudo completar la acción.',
                    ),
                ),
        };

        if (kind === 'invite') {
            router.post(usuarios.invitacion(user.id).url, {}, options);
        } else if (kind === 'twoFactor') {
            router.delete(usuarios.dosPasos(user.id).url, options);
        } else {
            router.patch(
                usuarios.update(user.id).url,
                { is_active: kind === 'activate' },
                options,
            );
        }
    });
}

const emptyCopy = computed(() =>
    props.filters.q || activeCount.value > 0
        ? {
              title: 'Sin resultados',
              description:
                  'Ninguna cuenta coincide con la búsqueda o los filtros.',
          }
        : type.value === 'equipo'
          ? {
                title: 'Aún no hay cuentas del equipo',
                description:
                    'Invita a tu equipo para que entre al panel con su propio rol.',
            }
          : {
                title: 'Aún no hay cuentas aquí',
                description:
                    'Los clientes se crean desde el CRM y los técnicos desde operaciones.',
            },
);
</script>

<template>
    <Head title="Usuarios" />

    <PageContainer>
        <PageHeader
            title="Usuarios"
            description="Cuentas del equipo, clientes y técnicos. Invita al personal interno y controla su rol y acceso."
        >
            <template #actions>
                <Button @click="openInvite">
                    <UserPlus class="size-4" />
                    Invitar al equipo
                </Button>
            </template>
        </PageHeader>

        <Tabs v-model="type" class="w-full">
            <TabsList class="w-full sm:w-auto">
                <TabsTrigger value="equipo" class="flex-1 sm:flex-none"
                    >Equipo
                    <span class="ml-1 text-muted-foreground tabular-nums">{{
                        counts.equipo
                    }}</span></TabsTrigger
                >
                <TabsTrigger value="clientes" class="flex-1 sm:flex-none"
                    >Clientes
                    <span class="ml-1 text-muted-foreground tabular-nums">{{
                        counts.clientes
                    }}</span></TabsTrigger
                >
                <TabsTrigger value="tecnicos" class="flex-1 sm:flex-none"
                    >Técnicos
                    <span class="ml-1 text-muted-foreground tabular-nums">{{
                        counts.tecnicos
                    }}</span></TabsTrigger
                >
            </TabsList>
        </Tabs>

        <FilterBar
            v-model:search="search"
            placeholder="Buscar por nombre o correo…"
            :active-count="activeCount"
            @clear="clearFilters"
        >
            <template #filters>
                <Select v-model="role">
                    <SelectTrigger
                        class="w-full md:w-52"
                        aria-label="Filtrar por rol"
                    >
                        <SelectValue placeholder="Todos los roles" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem :value="ALL">Todos los roles</SelectItem>
                        <SelectItem
                            v-for="option in roleOptions"
                            :key="option.value"
                            :value="option.value"
                            >{{ option.label }}</SelectItem
                        >
                    </SelectContent>
                </Select>
                <Select v-model="status">
                    <SelectTrigger
                        class="w-full md:w-48"
                        aria-label="Filtrar por estado"
                    >
                        <SelectValue placeholder="Todos los estados" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem :value="ALL">Todos los estados</SelectItem>
                        <SelectItem value="activos">Activas</SelectItem>
                        <SelectItem value="pendientes"
                            >Invitación pendiente</SelectItem
                        >
                        <SelectItem value="inactivos">Desactivadas</SelectItem>
                    </SelectContent>
                </Select>
            </template>
        </FilterBar>

        <DataView
            :columns="columns"
            :paginator="users"
            item-label="cuentas"
            :only="['users', 'filters']"
        >
            <template #cell-name="{ row }">
                <div class="flex min-w-0 flex-col">
                    <span class="truncate font-medium text-foreground">
                        {{ row.name }}
                        <span
                            v-if="row.isSelf"
                            class="ml-1 text-xs font-normal text-muted-foreground"
                            >(tú)</span
                        >
                    </span>
                    <span class="truncate text-sm text-muted-foreground">{{
                        row.email
                    }}</span>
                </div>
            </template>
            <template #cell-role="{ row }">
                <span class="text-sm">{{ row.roleLabel }}</span>
            </template>
            <template #cell-status="{ row }">
                <StatusBadge
                    v-if="!row.isActive"
                    tone="neutral"
                    label="Desactivada"
                />
                <StatusBadge
                    v-else-if="row.invitationPending"
                    tone="warning"
                    label="Invitación pendiente"
                />
                <StatusBadge v-else tone="success" label="Activa" />
            </template>
            <template #cell-twoFactor="{ row }">
                <span
                    class="inline-flex items-center gap-1.5 text-sm"
                    :class="
                        row.twoFactorEnabled
                            ? 'text-success-ink'
                            : 'text-muted-foreground'
                    "
                >
                    <component
                        :is="row.twoFactorEnabled ? ShieldCheck : ShieldOff"
                        class="size-4"
                        aria-hidden="true"
                    />
                    {{ row.twoFactorEnabled ? 'Activa' : 'Sin activar' }}
                </span>
            </template>
            <template #cell-lastLoginAt="{ row }">
                <DateText
                    v-if="row.lastLoginAt"
                    :value="row.lastLoginAt"
                    relative
                    class="text-sm"
                />
                <span v-else class="text-sm text-muted-foreground">Nunca</span>
            </template>

            <template #actions="{ row }">
                <DropdownMenu v-if="row.canEdit">
                    <DropdownMenuTrigger as-child>
                        <Button
                            variant="ghost"
                            size="icon"
                            :aria-label="`Acciones para ${row.name}`"
                        >
                            <MoreHorizontal class="size-4" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" class="w-60">
                        <DropdownMenuItem @select="openEdit(row)">
                            <Pencil class="size-4" /> Editar datos y rol
                        </DropdownMenuItem>
                        <DropdownMenuItem
                            v-if="row.invitationPending"
                            @select="ask('invite', row)"
                        >
                            <MailPlus class="size-4" /> Reenviar invitación
                        </DropdownMenuItem>
                        <DropdownMenuItem
                            v-if="row.twoFactorEnabled && !row.isSelf"
                            @select="ask('twoFactor', row)"
                        >
                            <KeyRound class="size-4" /> Restablecer dos pasos
                        </DropdownMenuItem>
                        <template v-if="!row.isSelf">
                            <DropdownMenuSeparator />
                            <DropdownMenuItem
                                v-if="row.isActive"
                                class="text-danger-ink"
                                @select="ask('deactivate', row)"
                            >
                                Desactivar cuenta
                            </DropdownMenuItem>
                            <DropdownMenuItem
                                v-else
                                @select="ask('activate', row)"
                                >Reactivar cuenta</DropdownMenuItem
                            >
                        </template>
                    </DropdownMenuContent>
                </DropdownMenu>
            </template>

            <template #empty>
                <EmptyState
                    :icon="Users"
                    :title="emptyCopy.title"
                    :description="emptyCopy.description"
                >
                    <template
                        v-if="
                            type === 'equipo' && !filters.q && activeCount === 0
                        "
                        #action
                    >
                        <Button @click="openInvite"
                            ><UserPlus class="size-4" /> Invitar al
                            equipo</Button
                        >
                    </template>
                </EmptyState>
            </template>
        </DataView>
    </PageContainer>

    <!-- Invitar -->
    <FormSheet
        v-model:open="inviteOpen"
        title="Invitar al equipo"
        description="Le enviaremos un correo para crear su contraseña. El enlace vence en 72 horas."
    >
        <form
            id="invite-form"
            class="grid gap-5"
            @submit.prevent="submitInvite"
        >
            <div class="grid gap-2">
                <Label for="invite-name">Nombre completo</Label>
                <Input
                    id="invite-name"
                    v-model="inviteForm.name"
                    autocomplete="off"
                    required
                    :aria-invalid="inviteForm.errors.name ? true : undefined"
                />
                <InputError :message="inviteForm.errors.name" />
            </div>
            <div class="grid gap-2">
                <Label for="invite-email">Correo</Label>
                <Input
                    id="invite-email"
                    v-model="inviteForm.email"
                    type="email"
                    autocomplete="off"
                    required
                    :aria-invalid="inviteForm.errors.email ? true : undefined"
                />
                <InputError :message="inviteForm.errors.email" />
            </div>
            <div class="grid gap-2">
                <Label for="invite-phone"
                    >Teléfono
                    <span class="font-normal text-muted-foreground"
                        >(opcional)</span
                    ></Label
                >
                <Input
                    id="invite-phone"
                    v-model="inviteForm.phone"
                    type="tel"
                    inputmode="tel"
                    autocomplete="off"
                    :aria-invalid="inviteForm.errors.phone ? true : undefined"
                />
                <InputError :message="inviteForm.errors.phone" />
            </div>
            <div class="grid gap-2">
                <Label for="invite-role">Rol</Label>
                <Select v-model="inviteForm.role">
                    <SelectTrigger
                        id="invite-role"
                        class="w-full"
                        :aria-invalid="
                            inviteForm.errors.role ? true : undefined
                        "
                    >
                        <SelectValue placeholder="Elige un rol" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="option in staffRoles"
                            :key="option.value"
                            :value="option.value"
                            >{{ option.label }}</SelectItem
                        >
                    </SelectContent>
                </Select>
                <p class="text-sm text-muted-foreground">
                    {{
                        staffRoles.find((r) => r.value === inviteForm.role)
                            ?.description
                    }}
                </p>
                <InputError :message="inviteForm.errors.role" />
            </div>
        </form>
        <template #footer>
            <Button variant="outline" type="button" @click="inviteOpen = false"
                >Cancelar</Button
            >
            <Button
                type="submit"
                form="invite-form"
                :disabled="inviteForm.processing"
            >
                <Spinner v-if="inviteForm.processing" />
                {{ inviteForm.processing ? 'Enviando…' : 'Enviar invitación' }}
            </Button>
        </template>
    </FormSheet>

    <!-- Editar -->
    <FormSheet
        v-model:open="editOpen"
        :title="editing ? `Editar a ${editing.name}` : 'Editar usuario'"
        :description="editing?.email"
    >
        <form
            v-if="editing"
            id="edit-form"
            class="grid gap-5"
            @submit.prevent="submitEdit"
        >
            <div class="grid gap-2">
                <Label for="edit-name">Nombre completo</Label>
                <Input
                    id="edit-name"
                    v-model="editForm.name"
                    required
                    :aria-invalid="editForm.errors.name ? true : undefined"
                />
                <InputError :message="editForm.errors.name" />
            </div>
            <div class="grid gap-2">
                <Label for="edit-phone">Teléfono</Label>
                <Input
                    id="edit-phone"
                    v-model="editForm.phone"
                    type="tel"
                    inputmode="tel"
                    :aria-invalid="editForm.errors.phone ? true : undefined"
                />
                <InputError :message="editForm.errors.phone" />
            </div>
            <div class="grid gap-2">
                <Label for="edit-role">Rol</Label>
                <Select
                    v-model="editForm.role"
                    :disabled="editing.isSelf || !editing.isStaff"
                >
                    <SelectTrigger
                        id="edit-role"
                        class="w-full"
                        :aria-invalid="editForm.errors.role ? true : undefined"
                    >
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="option in editRoleOptions"
                            :key="option.value"
                            :value="option.value"
                            >{{ option.label }}</SelectItem
                        >
                    </SelectContent>
                </Select>
                <p v-if="editing.isSelf" class="text-sm text-muted-foreground">
                    No puedes cambiar tu propio rol.
                </p>
                <p
                    v-else-if="!editing.isStaff"
                    class="text-sm text-muted-foreground"
                >
                    El rol de clientes y técnicos se administra desde su módulo.
                </p>
                <InputError :message="editForm.errors.role" />
            </div>
            <div
                class="flex items-start justify-between gap-4 rounded-lg border p-4"
            >
                <div class="grid gap-1">
                    <Label for="edit-active">Cuenta activa</Label>
                    <p class="text-sm text-muted-foreground">
                        Si la desactivas, se cierran sus sesiones y no podrá
                        entrar. Su historial se conserva.
                    </p>
                    <InputError :message="editForm.errors.is_active" />
                </div>
                <Switch
                    id="edit-active"
                    v-model="editForm.is_active"
                    :disabled="editing.isSelf"
                />
            </div>
        </form>
        <template #footer>
            <Button variant="outline" type="button" @click="editOpen = false"
                >Cancelar</Button
            >
            <Button
                type="submit"
                form="edit-form"
                :disabled="editForm.processing"
            >
                <Spinner v-if="editForm.processing" />
                {{ editForm.processing ? 'Guardando…' : 'Guardar cambios' }}
            </Button>
        </template>
    </FormSheet>

    <ConfirmDialog
        v-model:open="confirmOpen"
        :title="confirmCopy.title"
        :description="confirmCopy.description"
        :confirm-label="confirmCopy.label"
        :loading-label="confirmCopy.loading"
        :tone="confirmCopy.tone"
        :confirm="runConfirm"
    />
</template>
