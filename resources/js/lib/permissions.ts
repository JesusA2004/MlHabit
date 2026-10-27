import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { ComputedRef } from 'vue';
import { SUPER_ADMIN_ROLE } from '@/types/auth';
import type { User } from '@/types/auth';
import type { AdminNavGroup } from '@/types/navigation';

/** ¿El usuario tiene el permiso? `super_admin` siempre puede. */
export function userCan(
    user: User | null | undefined,
    permission: string,
): boolean {
    if (!user) {
        return false;
    }

    if (user.role === SUPER_ADMIN_ROLE) {
        return true;
    }

    return (
        Array.isArray(user.permissions) && user.permissions.includes(permission)
    );
}

/** Devuelve solo los elementos permitidos y descarta grupos vacíos. */
export function filterNavigation(
    groups: AdminNavGroup[],
    user: User | null | undefined,
): AdminNavGroup[] {
    return groups
        .map((group) => ({
            ...group,
            items: group.items.filter((item) => userCan(user, item.permission)),
        }))
        .filter((group) => group.items.length > 0);
}

const TECHNICIAN_ROLES = ['technician', 'tecnico', 'installer', 'instalador'];
const CUSTOMER_ROLES = ['customer', 'client', 'cliente'];

/** Qué "casa" le corresponde al usuario: panel, app de técnicos o portal. */
export function shellFor(
    user: User | null | undefined,
    groups: AdminNavGroup[],
): 'admin' | 'technician' | 'portal' | 'guest' {
    if (!user) {
        return 'guest';
    }

    if (TECHNICIAN_ROLES.includes(user.role)) {
        return 'technician';
    }

    if (CUSTOMER_ROLES.includes(user.role)) {
        return 'portal';
    }

    return filterNavigation(groups, user).length > 0 ? 'admin' : 'portal';
}

export type UsePermissionsReturn = {
    user: ComputedRef<User | null>;
    can: (permission: string) => boolean;
    canAny: (permissions: string[]) => boolean;
    isSuperAdmin: ComputedRef<boolean>;
};

/** Acceso reactivo al usuario compartido y a sus permisos. */
export function usePermissions(): UsePermissionsReturn {
    const page = usePage();
    const user = computed(() => page.props.auth?.user ?? null);

    return {
        user,
        can: (permission) => userCan(user.value, permission),
        canAny: (permissions) =>
            permissions.some((p) => userCan(user.value, p)),
        isSuperAdmin: computed(() => user.value?.role === SUPER_ADMIN_ROLE),
    };
}
