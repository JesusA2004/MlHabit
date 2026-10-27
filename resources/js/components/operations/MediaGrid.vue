<script setup lang="ts">
import { FileText, Film, ImageOff, Trash2 } from '@lucide/vue';
import { formatBytes } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { MediaItem } from '@/types/operations';

/**
 * Cuadrícula de archivos privados (evidencia, documentos). Las imágenes se
 * muestran en miniatura; videos y documentos como tarjeta. Cada archivo
 * abre en otra pestaña vía la ruta protegida (archivos.show).
 */
const props = withDefaults(
    defineProps<{
        items: MediaItem[];
        removable?: boolean;
        size?: 'sm' | 'md';
        emptyText?: string;
    }>(),
    { removable: false, size: 'md', emptyText: 'Sin archivos.' },
);

const emit = defineEmits<{ remove: [media: MediaItem] }>();

const tile = props.size === 'sm' ? 'size-20' : 'aspect-square w-full';
</script>

<template>
    <p v-if="items.length === 0" class="text-sm text-muted-foreground">
        {{ emptyText }}
    </p>
    <ul
        v-else
        :class="
            cn(
                'grid gap-2',
                size === 'sm'
                    ? 'flex flex-wrap'
                    : 'grid-cols-3 sm:grid-cols-4 lg:grid-cols-6',
            )
        "
    >
        <li v-for="media in items" :key="media.id" class="group relative">
            <a
                :href="media.url ?? undefined"
                target="_blank"
                rel="noopener"
                :class="
                    cn(
                        'flex overflow-hidden rounded-lg border bg-muted',
                        tile,
                        !media.url && 'pointer-events-none',
                    )
                "
                :title="media.name"
            >
                <img
                    v-if="media.type === 'image' && media.url"
                    :src="media.thumbnail ?? media.url"
                    :alt="media.name"
                    loading="lazy"
                    class="size-full object-cover transition-transform duration-200 group-hover:scale-[1.03]"
                />
                <span
                    v-else
                    class="flex size-full flex-col items-center justify-center gap-1 p-2 text-center text-xs text-muted-foreground"
                >
                    <Film
                        v-if="media.type === 'video'"
                        class="size-6"
                        aria-hidden="true"
                    />
                    <FileText
                        v-else-if="media.url"
                        class="size-6"
                        aria-hidden="true"
                    />
                    <ImageOff v-else class="size-6" aria-hidden="true" />
                    <span class="line-clamp-2 break-all">{{ media.name }}</span>
                    <span class="tabular-nums">{{
                        formatBytes(media.size)
                    }}</span>
                </span>
            </a>
            <button
                v-if="removable"
                type="button"
                class="absolute top-1 right-1 grid size-8 place-items-center rounded-md bg-background/90 text-destructive opacity-0 shadow-sm transition-opacity group-hover:opacity-100 focus-visible:opacity-100 pointer-coarse:opacity-100"
                :aria-label="`Quitar ${media.name}`"
                @click="emit('remove', media)"
            >
                <Trash2 class="size-4" />
            </button>
        </li>
    </ul>
</template>
