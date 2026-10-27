<script setup lang="ts">
import { computed } from 'vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { useInitials } from '@/composables/useInitials';
import { cn } from '@/lib/utils';
import type { User } from '@/types';

type Props = {
    user: User;
    showEmail?: boolean;
    showRole?: boolean;
    size?: 'default' | 'lg';
};

const props = withDefaults(defineProps<Props>(), {
    showEmail: false,
    showRole: false,
    size: 'default',
});

const ROLE_LABELS: Record<string, string> = {
    admin: 'Administrador',
    editor: 'Editor',
    colaborador: 'Colaborador',
};

const { getInitials } = useInitials();

const showAvatar = computed(() => typeof props.user.avatar === 'string' && props.user.avatar !== '');

const roleLabel = computed(() => {
    const role = props.user.role;

    return typeof role === 'string' && role !== '' ? (ROLE_LABELS[role] ?? role) : null;
});
</script>

<template>
    <Avatar :class="cn('shrink-0 overflow-hidden rounded-lg', size === 'lg' ? 'size-12' : 'size-8')">
        <AvatarImage v-if="showAvatar" :src="user.avatar!" :alt="user.name" />
        <AvatarFallback
            :class="cn('rounded-lg bg-primary font-medium text-primary-foreground', size === 'lg' ? 'font-display text-lg' : 'text-xs')"
        >
            {{ getInitials(user.name) }}
        </AvatarFallback>
    </Avatar>

    <div :class="cn('grid min-w-0 flex-1 text-left leading-tight', size === 'lg' ? 'gap-0.5 text-base' : 'text-sm')">
        <span class="truncate font-medium">{{ user.name }}</span>
        <span v-if="showEmail" :class="cn('truncate text-muted-foreground', size === 'lg' ? 'text-sm' : 'text-xs')">{{ user.email }}</span>
        <span
            v-if="showRole && roleLabel"
            :class="
                cn(
                    'truncate',
                    showEmail
                        ? 'mt-1.5 w-fit rounded-full bg-brand/15 px-2 py-0.5 text-xs font-medium text-foreground ring-1 ring-brand/30'
                        : 'text-xs text-muted-foreground',
                )
            "
        >
            {{ roleLabel }}
        </span>
    </div>
</template>
