<script setup lang="ts">
import { cn } from '@/lib/utils';
import UserAvatar from '@/components/ds/UserAvatar.vue';
import type { User } from '@/types';

/** Avatar + nombre (+ correo / rol) del usuario autenticado. */
const props = withDefaults(
    defineProps<{
        user: User;
        showEmail?: boolean;
        showRole?: boolean;
        size?: 'default' | 'lg';
    }>(),
    { showEmail: false, showRole: false, size: 'default' },
);
</script>

<template>
    <UserAvatar
        :name="props.user.name"
        :src="typeof props.user.avatar === 'string' ? props.user.avatar : null"
        :initials="props.user.avatarInitials"
        :size="props.size === 'lg' ? 'lg' : 'sm'"
        shape="rounded"
    />
    <div
        :class="
            cn(
                'grid min-w-0 flex-1 text-left leading-tight',
                props.size === 'lg' ? 'gap-0.5 text-base' : 'text-sm',
            )
        "
    >
        <span class="truncate font-medium">{{ props.user.name }}</span>
        <span
            v-if="props.showEmail"
            class="truncate text-xs text-muted-foreground"
            >{{ props.user.email }}</span
        >
        <span
            v-if="props.showRole && props.user.roleLabel"
            :class="
                cn(
                    'truncate text-xs',
                    props.showEmail
                        ? 'mt-1.5 w-fit rounded-full bg-primary-soft px-2 py-0.5 font-medium text-primary'
                        : 'text-muted-foreground',
                )
            "
        >
            {{ props.user.roleLabel }}
        </span>
    </div>
</template>
