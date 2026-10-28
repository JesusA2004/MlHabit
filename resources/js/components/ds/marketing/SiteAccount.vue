<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { ArrowUpRight, LogIn, LogOut } from '@lucide/vue';
import { computed } from 'vue';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { ICON_STROKE } from '@/components/web/visual/icons';
import { getInitials } from '@/composables/useInitials';
import { accountArea, accountLinks, loginPath } from '@/lib/navigation';
import { cn } from '@/lib/utils';

/**
 * Acceso único del sitio (cabecera de escritorio y tableta).
 * - Sin sesión: "Iniciar sesión". El backend (LoginResponse) lleva a cada
 *   quien a su área según su rol: cliente → portal, técnico → agenda,
 *   equipo → panel.
 * - Con sesión: avatar + menú con "Ir a mi panel" y "Cerrar sesión".
 * Bajo `xl` se muestra compacto (solo icono / iniciales) para que nunca
 * choque con el botón principal.
 */
const props = defineProps<{ transparent: boolean }>();

const page = usePage();
const user = computed(() => page.props.auth?.user ?? null);
const area = computed(() => (user.value ? accountArea(user.value) : null));
const firstName = computed(
    () => user.value?.name?.trim().split(/\s+/)[0] ?? '',
);

const pill = computed(() =>
    cn(
        'inline-flex h-11 shrink-0 items-center gap-2 rounded-full text-[0.9375rem] font-medium transition-colors focus-visible:ring-[3px] focus-visible:ring-ring/40 focus-visible:outline-none',
        props.transparent
            ? 'text-white/90 ring-1 ring-white/30 hover:bg-white/12 hover:text-white'
            : 'text-foreground ring-1 ring-border hover:bg-foreground/[0.05]',
    ),
);

function logout() {
    router.flushAll();
}
</script>

<template>
    <Link
        v-if="!user"
        :href="loginPath"
        :class="cn(pill, 'w-11 justify-center xl:w-auto xl:px-4')"
        aria-label="Iniciar sesión"
        data-test="site-login"
    >
        <LogIn
            class="size-4.5"
            :stroke-width="ICON_STROKE"
            aria-hidden="true"
        />
        <span class="hidden xl:inline">Iniciar sesión</span>
    </Link>

    <DropdownMenu v-else :modal="false">
        <DropdownMenuTrigger
            :class="cn(pill, 'pr-1 pl-1 xl:pr-4')"
            :aria-label="`Tu cuenta: ${user.name}`"
            data-test="site-account"
        >
            <span
                class="grid size-9 place-items-center rounded-full bg-primary text-[0.8125rem] font-semibold text-primary-foreground"
                aria-hidden="true"
            >
                {{ getInitials(user.name) }}
            </span>
            <span class="hidden max-w-[9rem] truncate xl:inline">{{
                firstName
            }}</span>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end" :side-offset="10" class="w-64">
            <DropdownMenuLabel class="font-normal">
                <span class="block truncate text-sm font-medium">{{
                    user.name
                }}</span>
                <span class="block truncate text-xs text-muted-foreground">{{
                    user.roleLabel
                }}</span>
            </DropdownMenuLabel>
            <DropdownMenuSeparator />
            <DropdownMenuItem v-if="area" as-child>
                <Link
                    :href="area.href"
                    class="flex min-h-10 w-full cursor-pointer items-center justify-between gap-2.5 font-medium text-primary"
                >
                    {{ area.label }}
                    <ArrowUpRight class="size-4" aria-hidden="true" />
                </Link>
            </DropdownMenuItem>
            <DropdownMenuItem as-child>
                <Link
                    :href="accountLinks.logout"
                    method="post"
                    as="button"
                    class="flex min-h-10 w-full cursor-pointer items-center gap-2.5"
                    data-test="site-logout"
                    @click="logout"
                >
                    <LogOut class="size-4" aria-hidden="true" />
                    Cerrar sesión
                </Link>
            </DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
