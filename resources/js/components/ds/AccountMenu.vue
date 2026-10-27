<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import UserMenuContent from '@/components/UserMenuContent.vue';
import UserAvatar from './UserAvatar.vue';

/** Botón de avatar con el menú de cuenta (layouts de técnicos y portal). */
const props = withDefaults(defineProps<{ showAppearance?: boolean }>(), {
    showAppearance: true,
});

const page = usePage();
const user = computed(() => page.props.auth?.user ?? null);
</script>

<template>
    <DropdownMenu v-if="user">
        <DropdownMenuTrigger
            class="grid size-11 place-items-center rounded-full outline-none focus-visible:ring-3 focus-visible:ring-ring/40"
            :aria-label="`Cuenta de ${user.name}`"
        >
            <UserAvatar
                :name="user.name"
                :initials="user.avatarInitials"
                size="sm"
            />
        </DropdownMenuTrigger>
        <DropdownMenuContent
            class="min-w-64 rounded-lg"
            align="end"
            :side-offset="6"
        >
            <UserMenuContent
                :user="user"
                :show-appearance="props.showAppearance"
            />
        </DropdownMenuContent>
    </DropdownMenu>
</template>
