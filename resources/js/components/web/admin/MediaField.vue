<script setup lang="ts">
import { ImagePlus, X } from '@lucide/vue';
import { ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import type { MediaItem, MediaRef } from '@/types/web';
import MediaPicker from './MediaPicker.vue';

/**
 * Campo de imagen única: vista previa, elegir de la biblioteca o quitar.
 * v-model = id del archivo; `media` = referencia inicial para la vista previa.
 */
const model = defineModel<number | null>({ default: null });

const props = withDefaults(
    defineProps<{
        media?: MediaRef | null;
        label?: string;
        collection?: string;
        aspect?: string;
        invalid?: boolean;
    }>(),
    {
        media: null,
        label: 'Elegir imagen',
        collection: 'sitio/general',
        aspect: 'aspect-[16/10]',
        invalid: false,
    },
);

const emit = defineEmits<{ change: [media: MediaRef | null] }>();

const preview = ref<MediaRef | null>(props.media);
const picker = ref(false);

watch(
    () => props.media,
    (value) => {
        if (value && value.id === model.value) preview.value = value;
    },
);

function onSelect(items: MediaItem[]) {
    const item = items[0];
    if (!item) return;
    preview.value = item;
    model.value = item.id;
    emit('change', item);
}

function clear() {
    preview.value = null;
    model.value = null;
    emit('change', null);
}
</script>

<template>
    <div class="flex flex-col gap-2">
        <div
            v-if="model && preview"
            :class="[
                'relative overflow-hidden rounded-lg border bg-muted',
                props.aspect,
                props.invalid ? 'border-danger' : 'border-border',
            ]"
        >
            <img
                :src="preview.thumbnailUrl ?? preview.url ?? ''"
                :alt="preview.altText ?? ''"
                class="h-full w-full object-cover"
            />
            <div
                class="absolute inset-x-0 bottom-0 flex items-center justify-between gap-2 bg-gradient-to-t from-black/70 to-transparent p-2 pt-6"
            >
                <span class="truncate text-xs text-white">{{
                    preview.filename
                }}</span>
                <div class="flex shrink-0 gap-1.5">
                    <Button
                        type="button"
                        size="sm"
                        variant="secondary"
                        class="h-7"
                        @click="picker = true"
                        >Cambiar</Button
                    >
                    <Button
                        type="button"
                        size="icon"
                        variant="secondary"
                        class="size-7"
                        aria-label="Quitar imagen"
                        @click="clear"
                        ><X class="size-3.5"
                    /></Button>
                </div>
            </div>
        </div>
        <button
            v-else
            type="button"
            :class="[
                'flex flex-col items-center justify-center gap-2 rounded-lg border-2 border-dashed bg-surface/60 text-sm text-muted-foreground transition-colors hover:border-primary/50 hover:bg-primary-soft/40 hover:text-foreground',
                props.aspect,
                props.invalid ? 'border-danger' : 'border-input',
            ]"
            @click="picker = true"
        >
            <ImagePlus class="size-5" aria-hidden="true" />
            {{ props.label }}
        </button>
        <MediaPicker
            v-model:open="picker"
            :collection="props.collection"
            @select="onSelect"
        />
    </div>
</template>
