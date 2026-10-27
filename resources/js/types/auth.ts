/**
 * Usuario autenticado tal como lo comparte HandleInertiaRequests
 * (`auth.user`). `permissions` son claves como `leads.view`; el rol
 * `super_admin` ve todo.
 */
export type User = {
    id: number;
    name: string;
    email: string;
    role: string;
    roleLabel: string;
    permissions: string[];
    avatarInitials?: string;
    avatar?: string | null;
    email_verified_at?: string | null;
    created_at?: string;
    updated_at?: string;
    [key: string]: unknown;
};

export type Auth = {
    user: User | null;
};

export const SUPER_ADMIN_ROLE = 'super_admin';
