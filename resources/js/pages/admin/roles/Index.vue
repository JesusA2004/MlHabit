<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Lock, RotateCcw } from '@lucide/vue';
import { computed, reactive, ref, watch } from 'vue';
import ConfirmDialog from '@/components/ds/ConfirmDialog.vue';
import PageContainer from '@/components/ds/PageContainer.vue';
import PageHeader from '@/components/ds/PageHeader.vue';
import StatusBadge from '@/components/ds/StatusBadge.vue';
import {
    Accordion,
    AccordionContent,
    AccordionItem,
    AccordionTrigger,
} from '@/components/ui/accordion';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Spinner } from '@/components/ui/spinner';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import rolesRoutes from '@/routes/admin/roles';

type PermissionItem = { value: string; label: string };
type Group = { label: string; permissions: PermissionItem[] };
type Role = {
    value: string;
    label: string;
    description: string;
    locked: 'all' | 'none' | null;
    permissions: string[];
    defaults: string[];
    userCount: number;
};

const props = defineProps<{ groups: Group[]; roles: Role[] }>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Panel', href: '/admin' },
            { title: 'Roles y permisos', href: '/admin/roles' },
        ],
    },
});

/** Estado editable: rol → conjunto de permisos marcados. */
const state = reactive<Record<string, Set<string>>>({});

function resetState() {
    for (const role of props.roles) {
        state[role.value] = new Set(role.permissions);
    }
}

resetState();
watch(() => props.roles, resetState, { deep: true });

const editableRoles = computed(() =>
    props.roles.filter((r) => r.locked === null),
);

function isChecked(role: Role, permission: string): boolean {
    if (role.locked === 'all') {
        return true;
    }

    if (role.locked === 'none') {
        return false;
    }

    return state[role.value]?.has(permission) ?? false;
}

function toggle(
    role: Role,
    permission: string,
    value: boolean | 'indeterminate',
) {
    if (role.locked !== null) {
        return;
    }

    const set = new Set(state[role.value]);

    if (value === true) {
        set.add(permission);
    } else {
        set.delete(permission);
    }

    state[role.value] = set;
}

function isDirty(role: Role): boolean {
    if (role.locked !== null) {
        return false;
    }

    const current = state[role.value] ?? new Set<string>();

    return (
        current.size !== role.permissions.length ||
        role.permissions.some((p) => !current.has(p))
    );
}

const dirtyRoles = computed(() => editableRoles.value.filter(isDirty));
const differsFromDefaults = (role: Role) =>
    role.locked === null &&
    (role.defaults.length !== role.permissions.length ||
        role.defaults.some((p) => !role.permissions.includes(p)));

const saving = ref(false);

async function saveRole(role: Role, permissions?: string[]): Promise<void> {
    const payload = permissions ?? Array.from(state[role.value] ?? []);

    return new Promise((resolve, reject) => {
        router.put(
            rolesRoutes.update(role.value).url,
            { permissions: payload },
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => resolve(),
                onError: () =>
                    reject(
                        new Error(
                            `No se pudieron guardar los permisos de ${role.label}.`,
                        ),
                    ),
            },
        );
    });
}

async function saveAll() {
    if (saving.value) {
        return;
    }

    saving.value = true;

    // Se toma una foto de los cambios: cada respuesta recarga los props.
    const pending = dirtyRoles.value.map((role) => ({
        role,
        permissions: Array.from(state[role.value] ?? []),
    }));

    try {
        for (const item of pending) {
            await saveRole(item.role, item.permissions);
        }
    } finally {
        saving.value = false;
    }
}

const resetTarget = ref<Role | null>(null);
const resetOpen = ref(false);

function askReset(role: Role) {
    resetTarget.value = role;
    resetOpen.value = true;
}

function runReset(): Promise<void> {
    const role = resetTarget.value;

    return new Promise((resolve, reject) => {
        if (!role) {
            resolve();

            return;
        }

        router.post(
            rolesRoutes.restablecer(role.value).url,
            {},
            {
                preserveScroll: true,
                onSuccess: () => resolve(),
                onError: () =>
                    reject(
                        new Error('No se pudieron restablecer los permisos.'),
                    ),
            },
        );
    });
}

function countFor(role: Role): number {
    if (role.locked === 'all') {
        return props.groups.reduce(
            (total, g) => total + g.permissions.length,
            0,
        );
    }

    return role.locked === 'none' ? 0 : (state[role.value]?.size ?? 0);
}

const lockCopy = {
    all: 'Tiene todos los permisos siempre.',
    none: 'Los clientes solo usan su portal; no tienen permisos internos.',
} as const;
</script>

<template>
    <Head title="Roles y permisos" />

    <PageContainer width="wide" class="pb-28">
        <PageHeader
            title="Roles y permisos"
            description="Decide qué puede ver y hacer cada rol. Los cambios aplican de inmediato para todas las personas con ese rol y quedan en la bitácora."
        />

        <!-- Escritorio: matriz con roles como columnas -->
        <div class="hidden overflow-hidden rounded-xl border bg-card lg:block">
            <TooltipProvider :delay-duration="200">
                <div class="max-h-[70vh] overflow-auto">
                    <table
                        class="w-full border-separate border-spacing-0 text-sm"
                    >
                        <caption class="sr-only">
                            Matriz de permisos por rol
                        </caption>
                        <thead>
                            <tr>
                                <th
                                    scope="col"
                                    class="sticky top-0 left-0 z-20 min-w-64 border-b bg-card px-4 py-3 text-left font-medium text-muted-foreground"
                                >
                                    Permiso
                                </th>
                                <th
                                    v-for="role in roles"
                                    :key="role.value"
                                    scope="col"
                                    class="sticky top-0 z-10 min-w-28 border-b bg-card px-2 py-3 text-center align-bottom font-medium"
                                >
                                    <Tooltip>
                                        <TooltipTrigger as-child>
                                            <span
                                                class="inline-flex cursor-default flex-col items-center gap-1"
                                            >
                                                <span
                                                    class="flex items-center gap-1"
                                                >
                                                    <Lock
                                                        v-if="role.locked"
                                                        class="size-3.5 text-muted-foreground"
                                                        aria-hidden="true"
                                                    />
                                                    {{ role.label }}
                                                </span>
                                                <span
                                                    class="text-xs font-normal text-muted-foreground tabular-nums"
                                                    >{{ countFor(role) }} ·
                                                    {{ role.userCount }}
                                                    {{
                                                        role.userCount === 1
                                                            ? 'persona'
                                                            : 'personas'
                                                    }}</span
                                                >
                                            </span>
                                        </TooltipTrigger>
                                        <TooltipContent class="max-w-64">{{
                                            role.locked
                                                ? lockCopy[role.locked]
                                                : role.description
                                        }}</TooltipContent>
                                    </Tooltip>
                                    <div
                                        v-if="role.locked === null"
                                        class="mt-1"
                                    >
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            class="h-7 px-2 text-xs"
                                            :disabled="
                                                !differsFromDefaults(role)
                                            "
                                            @click="askReset(role)"
                                        >
                                            <RotateCcw class="size-3" />
                                            Recomendados
                                        </Button>
                                    </div>
                                </th>
                            </tr>
                        </thead>
                        <tbody v-for="group in groups" :key="group.label">
                            <tr>
                                <th
                                    scope="colgroup"
                                    :colspan="roles.length + 1"
                                    class="sticky left-0 border-b bg-muted/60 px-4 py-2 text-left text-xs font-medium text-muted-foreground"
                                >
                                    {{ group.label }}
                                </th>
                            </tr>
                            <tr
                                v-for="permission in group.permissions"
                                :key="permission.value"
                                class="hover:bg-muted/30"
                            >
                                <th
                                    scope="row"
                                    class="sticky left-0 border-b bg-card px-4 py-2 text-left font-normal"
                                >
                                    {{ permission.label }}
                                    <span
                                        class="block font-mono text-[11px] text-muted-foreground"
                                        >{{ permission.value }}</span
                                    >
                                </th>
                                <td
                                    v-for="role in roles"
                                    :key="role.value"
                                    class="border-b px-2 py-2 text-center"
                                >
                                    <Checkbox
                                        :model-value="
                                            isChecked(role, permission.value)
                                        "
                                        :disabled="
                                            role.locked !== null || saving
                                        "
                                        :aria-label="`${permission.label} para ${role.label}`"
                                        @update:model-value="
                                            (v) =>
                                                toggle(
                                                    role,
                                                    permission.value,
                                                    v,
                                                )
                                        "
                                    />
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </TooltipProvider>
        </div>

        <!-- Móvil y tableta: un acordeón por rol -->
        <Accordion
            type="single"
            collapsible
            class="flex flex-col gap-3 lg:hidden"
        >
            <AccordionItem
                v-for="role in roles"
                :key="role.value"
                :value="role.value"
                class="rounded-xl border bg-card px-4"
            >
                <AccordionTrigger class="py-4 hover:no-underline">
                    <span
                        class="flex min-w-0 flex-col items-start gap-1 text-left"
                    >
                        <span class="flex items-center gap-2 font-medium">
                            <Lock
                                v-if="role.locked"
                                class="size-3.5 text-muted-foreground"
                                aria-hidden="true"
                            />
                            {{ role.label }}
                            <StatusBadge
                                v-if="isDirty(role)"
                                tone="warning"
                                size="sm"
                                label="Sin guardar"
                            />
                        </span>
                        <span class="text-xs font-normal text-muted-foreground"
                            >{{ countFor(role) }} permisos ·
                            {{ role.userCount }}
                            {{
                                role.userCount === 1 ? 'persona' : 'personas'
                            }}</span
                        >
                    </span>
                </AccordionTrigger>
                <AccordionContent class="pb-4">
                    <p class="mb-4 text-sm text-muted-foreground">
                        {{
                            role.locked
                                ? lockCopy[role.locked]
                                : role.description
                        }}
                    </p>
                    <div
                        v-for="group in groups"
                        :key="group.label"
                        class="mb-4"
                    >
                        <p
                            class="mb-2 text-xs font-medium text-muted-foreground"
                        >
                            {{ group.label }}
                        </p>
                        <ul class="grid gap-1">
                            <li
                                v-for="permission in group.permissions"
                                :key="permission.value"
                            >
                                <label
                                    class="flex min-h-11 items-center gap-3 rounded-md px-2 hover:bg-muted/40"
                                >
                                    <Checkbox
                                        :model-value="
                                            isChecked(role, permission.value)
                                        "
                                        :disabled="
                                            role.locked !== null || saving
                                        "
                                        @update:model-value="
                                            (v) =>
                                                toggle(
                                                    role,
                                                    permission.value,
                                                    v,
                                                )
                                        "
                                    />
                                    <span class="text-sm">{{
                                        permission.label
                                    }}</span>
                                </label>
                            </li>
                        </ul>
                    </div>
                    <div
                        v-if="role.locked === null"
                        class="flex flex-wrap gap-2"
                    >
                        <Button
                            :disabled="!isDirty(role) || saving"
                            @click="saveRole(role)"
                            >Guardar {{ role.label }}</Button
                        >
                        <Button
                            variant="outline"
                            :disabled="!differsFromDefaults(role) || saving"
                            @click="askReset(role)"
                        >
                            <RotateCcw class="size-4" /> Valores recomendados
                        </Button>
                    </div>
                </AccordionContent>
            </AccordionItem>
        </Accordion>
    </PageContainer>

    <!-- Barra de guardado (escritorio) -->
    <div
        v-if="dirtyRoles.length > 0"
        class="fixed inset-x-0 bottom-0 z-30 hidden border-t bg-card/95 px-6 py-3 backdrop-blur lg:block"
        role="region"
        aria-label="Cambios sin guardar"
    >
        <div
            class="mx-auto flex max-w-[96rem] items-center justify-between gap-4"
        >
            <p class="text-sm">
                Cambios sin guardar en:
                <span class="font-medium">{{
                    dirtyRoles.map((r) => r.label).join(', ')
                }}</span>
            </p>
            <div class="flex gap-2">
                <Button variant="outline" :disabled="saving" @click="resetState"
                    >Descartar</Button
                >
                <Button :disabled="saving" @click="saveAll">
                    <Spinner v-if="saving" />
                    {{ saving ? 'Guardando…' : 'Guardar cambios' }}
                </Button>
            </div>
        </div>
    </div>

    <ConfirmDialog
        v-model:open="resetOpen"
        :title="`Restablecer ${resetTarget?.label ?? ''}`"
        description="Se reemplazarán los permisos actuales de este rol por los valores recomendados de ML Habitat. El cambio aplica de inmediato y queda en la bitácora."
        confirm-label="Restablecer"
        loading-label="Restableciendo…"
        :confirm="runReset"
    />
</template>
