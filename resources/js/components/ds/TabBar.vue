<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import type { HTMLAttributes } from 'vue';
import { isActivePath } from '@/lib/navigation';
import { cn } from '@/lib/utils';
import type { TabNavItem } from '@/types/navigation';

/**
 * Barra de pestañas inferior estilo app (móvil). Respeta el área segura del
 * dispositivo y marca la pestaña activa con `aria-current`.
 */
const props = defineProps<{
    items: TabNavItem[];
    label: string;
    class?: HTMLAttributes['class'];
}>();

const page = usePage();
</script>

<template>
    <nav
        :aria-label="props.label"
        :class="
            cn(
                'fixed inset-x-0 bottom-0 z-40 border-t border-border/80 bg-background/90 pb-safe backdrop-blur-lg supports-[backdrop-filter]:bg-background/75',
                props.class,
            )
        "
    >
        <ul class="mx-auto flex max-w-lg items-stretch justify-around px-1">
            <li v-for="item in props.items" :key="item.href" class="flex-1">
                <Link
                    :href="item.href"
                    :aria-current="
                        isActivePath(page.url, item) ? 'page' : undefined
                    "
                    :class="
                        cn(
                            'group flex min-h-14 flex-col items-center justify-center gap-1 rounded-lg px-1 pt-2 pb-1.5 text-[11px] font-medium transition-colors tap-highlight-none',
                            isActivePath(page.url, item)
                                ? 'text-primary'
                                : 'text-muted-foreground hover:text-foreground',
                        )
                    "
                >
                    <span
                        :class="
                            cn(
                                'grid h-7 w-12 place-items-center rounded-full transition-colors duration-200',
                                isActivePath(page.url, item) &&
                                    'bg-primary-soft',
                            )
                        "
                    >
                        <component
                            :is="item.icon"
                            class="size-5"
                            :stroke-width="
                                isActivePath(page.url, item) ? 2.2 : 1.8
                            "
                        />
                    </span>
                    <span class="truncate">{{ item.title }}</span>
                </Link>
            </li>
        </ul>
    </nav>
</template>
