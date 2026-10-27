<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { LogOut, Palette, ShieldCheck, UserRound } from '@lucide/vue';
import {
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu';
import UserInfo from '@/components/UserInfo.vue';
import { accountLinks } from '@/lib/navigation';
import type { User } from '@/types';

/** Contenido del menú de cuenta: perfil, seguridad, apariencia y salir. */
const props = withDefaults(
    defineProps<{ user: User; showAppearance?: boolean }>(),
    {
        showAppearance: true,
    },
);

function handleLogout() {
    router.flushAll();
}

const itemClass =
    'flex min-h-10 w-full cursor-pointer items-center gap-2.5 md:min-h-9';
</script>

<template>
    <DropdownMenuLabel class="p-0 font-normal">
        <div class="flex items-start gap-3 px-2 py-2.5 text-left text-sm">
            <UserInfo :user="props.user" show-email show-role />
        </div>
    </DropdownMenuLabel>
    <DropdownMenuSeparator />
    <DropdownMenuGroup>
        <DropdownMenuItem as-child>
            <Link :class="itemClass" :href="accountLinks.profile" prefetch>
                <UserRound class="size-4" />
                Mi perfil
            </Link>
        </DropdownMenuItem>
        <DropdownMenuItem as-child>
            <Link :class="itemClass" :href="accountLinks.security" prefetch>
                <ShieldCheck class="size-4" />
                Seguridad
            </Link>
        </DropdownMenuItem>
        <DropdownMenuItem v-if="props.showAppearance" as-child>
            <Link :class="itemClass" :href="accountLinks.appearance" prefetch>
                <Palette class="size-4" />
                Apariencia
            </Link>
        </DropdownMenuItem>
    </DropdownMenuGroup>
    <DropdownMenuSeparator />
    <DropdownMenuItem as-child>
        <Link
            :class="itemClass"
            :href="accountLinks.logout"
            method="post"
            as="button"
            data-test="logout-button"
            @click="handleLogout"
        >
            <LogOut class="size-4" />
            Cerrar sesión
        </Link>
    </DropdownMenuItem>
</template>
