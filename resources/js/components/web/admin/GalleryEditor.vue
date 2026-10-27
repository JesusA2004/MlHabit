<script setup lang="ts">
import {
    ArrowDown,
    ArrowUp,
    GripVertical,
    ImagePlus,
    Trash2,
} from '@lucide/vue';
import { ref } from 'vue';
import { VueDraggable } from 'vue-draggable-plus';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { GalleryEditorItem, MediaItem } from '@/types/web';
import MediaPicker from './MediaPicker.vue';

/**
 * Galería ordenable (arrastrar o flechas de teclado). Cada imagen declara
 * su origen —render conceptual o proyecto real— que se muestra en el sitio.
 */
const model = defineModel<GalleryEditorItem[]>({ default: () => [] });

const props = withDefaults(
    defineProps<{ collection?: string; max?: number }>(),
    { collection: 'catalogo/productos', max: 40 },
);

const picker = ref(false);

function add(items: MediaItem[]) {
    const existing = new Set(model.value.map((i) => i.id));
    const next = [...model.value];
    for (const item of items) {
        if (!existing.has(item.id) && next.length < props.max) {
            next.push({ ...item, caption: null });
        }
    }
    model.value = next;
}

function move(index: number, delta: number) {
    const next = [...model.value];
    const target = index + delta;
    if (target < 0 || target >= next.length) return;
    [next[index], next[target]] = [next[target], next[index]];
    model.value = next;
}

function remove(index: number) {
    model.value = model.value.filter((_, i) => i !== index);
}

function setOrigin(index: number, value: unknown) {
    const next = [...model.value];
    next[index] = {
        ...next[index],
        origin: value === 'render' || value === 'real_photo' ? value : null,
    };
    model.value = next;
}

function setCaption(index: number, value: string | number) {
    const next = [...model.value];
    next[index] = { ...next[index], caption: String(value) || null };
    model.value = next;
}
</script>

<template>
    <div class="flex flex-col gap-3">
        <VueDraggable
            v-model="model"
            :animation="150"
            handle="[data-handle]"
            tag="ul"
            class="flex flex-col gap-2"
        >
            <li
                v-for="(item, index) in model"
                :key="item.id"
                class="flex flex-col gap-3 rounded-lg border border-border bg-card p-2 sm:flex-row sm:items-center"
            >
                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        data-handle
                        class="hidden cursor-grab touch-none text-muted-foreground sm:block"
                        aria-hidden="true"
                        tabindex="-1"
                    >
                        <GripVertical class="size-4" />
                    </button>
                    <img
                        :src="item.thumbnailUrl ?? item.url ?? ''"
                        :alt="item.altText ?? ''"
                        class="size-16 shrink-0 rounded-md object-cover"
                    />
                    <div class="min-w-0 sm:hidden">
                        <p class="truncate text-sm">{{ item.filename }}</p>
                    </div>
                </div>
                <div class="grid min-w-0 flex-1 gap-2 sm:grid-cols-[11rem_1fr]">
                    <Select
                        :model-value="item.origin ?? 'none'"
                        @update:model-value="(v) => setOrigin(index, v)"
                    >
                        <SelectTrigger
                            class="w-full"
                            :aria-label="`Origen de la imagen ${index + 1}`"
                            ><SelectValue
                        /></SelectTrigger>
                        <SelectContent>
                            <SelectItem value="none">Sin indicar</SelectItem>
                            <SelectItem value="render"
                                >Render conceptual</SelectItem
                            >
                            <SelectItem value="real_photo"
                                >Proyecto real</SelectItem
                            >
                        </SelectContent>
                    </Select>
                    <Input
                        :model-value="item.caption ?? ''"
                        maxlength="200"
                        placeholder="Pie de foto (opcional)"
                        :aria-label="`Pie de foto de la imagen ${index + 1}`"
                        @update:model-value="(v) => setCaption(index, v)"
                    />
                </div>
                <div class="flex shrink-0 justify-end gap-1">
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        class="size-8"
                        :disabled="index === 0"
                        aria-label="Mover arriba"
                        @click="move(index, -1)"
                        ><ArrowUp class="size-4"
                    /></Button>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        class="size-8"
                        :disabled="index === model.length - 1"
                        aria-label="Mover abajo"
                        @click="move(index, 1)"
                        ><ArrowDown class="size-4"
                    /></Button>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        class="size-8 text-danger-ink"
                        aria-label="Quitar de la galería"
                        @click="remove(index)"
                        ><Trash2 class="size-4"
                    /></Button>
                </div>
            </li>
        </VueDraggable>
        <p
            v-if="!model.length"
            class="rounded-lg border border-dashed border-border px-4 py-6 text-center text-sm text-muted-foreground"
        >
            Aún no hay imágenes en la galería.
        </p>
        <Button
            type="button"
            variant="outline"
            class="self-start"
            :disabled="model.length >= props.max"
            @click="picker = true"
        >
            <ImagePlus class="size-4" /> Agregar imágenes
        </Button>
        <MediaPicker
            v-model:open="picker"
            multiple
            :collection="props.collection"
            title="Agregar a la galería"
            @select="add"
        />
    </div>
</template>
