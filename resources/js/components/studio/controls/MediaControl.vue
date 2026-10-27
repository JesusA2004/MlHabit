<script setup lang="ts">
import { Clapperboard, FileText, ImagePlus, RefreshCw, X } from '@lucide/vue';
import { computed, ref } from 'vue';
import MediaPickerDialog, { type PickedMedia } from '@/components/admin/MediaPickerDialog.vue';
import type { ResolvedMedia } from '@/studio/types';

/** Referencia a un archivo de la biblioteca (guarda solo el ID). */
const props = defineProps<{
    modelValue: number | null | undefined;
    mediaType?: 'image' | 'video' | 'captions';
    label: string;
    media: ResolvedMedia | null;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: number | null];
    picked: [media: ResolvedMedia];
}>();

const open = ref(false);
defineExpose({ open: () => (open.value = true) });

const thumb = computed(() => (props.media?.type === 'video' ? props.media.posterUrl : props.media?.type === 'image' ? props.media.url : null));

function toResolved(m: PickedMedia): ResolvedMedia {
    return {
        id: m.id,
        type: m.type,
        mime: m.mimeType ?? '',
        url: m.url,
        posterUrl: m.posterUrl ?? null,
        width: m.width ?? null,
        height: m.height ?? null,
        duration: m.durationSeconds ?? null,
        alt: m.altText,
        filename: m.filename,
    };
}

function onSelect(m: PickedMedia) {
    const resolved = toResolved(m);
    emit('picked', resolved);
    emit('update:modelValue', m.id);
}
</script>

<template>
    <div class="flex items-center gap-3 rounded-lg border bg-muted/30 p-2">
        <button
            type="button"
            class="relative grid size-16 shrink-0 place-items-center overflow-hidden rounded-md bg-muted text-muted-foreground"
            :aria-label="modelValue ? `Cambiar ${label}` : `Elegir ${label}`"
            @click="open = true"
        >
            <img v-if="thumb" :src="thumb" alt="" class="size-full object-cover" />
            <Clapperboard v-else-if="mediaType === 'video'" class="size-6" />
            <FileText v-else-if="mediaType === 'captions'" class="size-6" />
            <ImagePlus v-else class="size-6" />
        </button>
        <div class="min-w-0 flex-1">
            <p class="truncate text-xs font-medium">
                {{ media?.filename ?? (modelValue ? `Archivo #${modelValue} (no disponible)` : 'Sin archivo') }}
            </p>
            <p v-if="media?.width && media?.height" class="text-[11px] text-muted-foreground">
                {{ media.width }}×{{ media.height }} px
            </p>
            <div class="mt-1.5 flex gap-1">
                <button
                    type="button"
                    class="inline-flex h-7 items-center gap-1 rounded-md bg-primary px-2.5 text-[11px] font-semibold text-primary-foreground hover:bg-primary/90"
                    @click="open = true"
                >
                    <RefreshCw v-if="modelValue" class="size-3" />
                    <ImagePlus v-else class="size-3" />
                    {{ modelValue ? 'Cambiar' : 'Elegir o subir' }}
                </button>
                <button
                    v-if="modelValue"
                    type="button"
                    class="inline-flex h-7 items-center gap-1 rounded-md px-2 text-[11px] text-muted-foreground hover:bg-muted hover:text-foreground"
                    @click="emit('update:modelValue', null)"
                >
                    <X class="size-3" /> Quitar
                </button>
            </div>
        </div>
    </div>
    <MediaPickerDialog v-model:open="open" :type="mediaType" @select="onSelect" />
</template>
