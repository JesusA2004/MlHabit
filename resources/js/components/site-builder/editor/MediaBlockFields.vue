<script setup lang="ts">
import {
    ArrowDown,
    ArrowUp,
    CircleCheck,
    ImagePlus,
    Trash2,
    TriangleAlert,
} from '@lucide/vue';
import { computed, inject, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import MediaPicker from '@/components/web/admin/MediaPicker.vue';
import type { MediaItem } from '@/types/web';
import { parseVideoUrl } from '../embed';
import type { Block, GalleryItem } from '../types';
import { editorKey } from './keys';

/**
 * Propiedades de los bloques de galería y de video de YouTube/Vimeo
 * (panel derecho del editor visual).
 */
const props = defineProps<{ block: Block; sectionKey: string }>();

const editor = inject(editorKey)!;
const p = computed(() => props.block.props);

function str(key: string, fallback = ''): string {
    const v = p.value[key];

    return typeof v === 'string' ? v : fallback;
}

function set(patch: Record<string, unknown>) {
    editor.updateProps(props.sectionKey, props.block.id, patch);
}

function setLive(key: string, value: unknown) {
    editor.live(() => {
        const b = editor.block(props.sectionKey, props.block.id);
        if (b) b.props = { ...b.props, [key]: value };
    }, false);
}

function settle() {
    editor.live(() => {}, true);
}

// ----- Video incrustado ------------------------------------------------
const url = ref(str('url'));
watch(
    () => props.block.id,
    () => (url.value = str('url')),
);
const parsed = computed(() => (url.value ? parseVideoUrl(url.value) : null));

function commitUrl() {
    const value = url.value.trim();
    const ref_ = value ? parseVideoUrl(value) : null;
    // Un enlace no permitido no se guarda (el aviso queda a la vista).
    if (value !== '' && !ref_) return;
    if (value === str('url')) return;
    set({
        url: value,
        provider: ref_?.provider ?? null,
        videoId: ref_?.id ?? null,
    });
}

// ----- Galería ---------------------------------------------------------
const items = computed<GalleryItem[]>(() =>
    Array.isArray(p.value.items) ? (p.value.items as GalleryItem[]) : [],
);
const pickerOpen = ref(false);

function thumb(id: number): string | null {
    const ref_ = editor.library.value[String(id)];

    return ref_?.thumbnailUrl ?? ref_?.url ?? null;
}

function onPick(picked: MediaItem[]) {
    const library = { ...editor.library.value };
    const existing = new Set(items.value.map((i) => i.mediaId));
    const added: GalleryItem[] = [];
    for (const item of picked) {
        library[String(item.id)] = item;
        if (!existing.has(item.id)) {
            added.push({ mediaId: item.id, alt: '', caption: '' });
        }
    }
    editor.library.value = library;
    set({ items: [...items.value, ...added].slice(0, 24) });
}

function moveItem(index: number, delta: number) {
    const next = [...items.value];
    const target = index + delta;
    if (target < 0 || target >= next.length) return;
    [next[index], next[target]] = [next[target], next[index]];
    set({ items: next });
}

function removeItem(index: number) {
    set({ items: items.value.filter((_, i) => i !== index) });
}

function setItemText(index: number, key: 'alt' | 'caption', value: string) {
    const next = items.value.map((item, i) =>
        i === index ? { ...item, [key]: value } : item,
    );
    setLive('items', next);
}
</script>

<template>
    <template v-if="props.block.type === 'embed'">
        <div class="grid gap-1.5">
            <Label :for="`embed-${props.block.id}`"
                >Enlace de YouTube o Vimeo</Label
            >
            <Input
                :id="`embed-${props.block.id}`"
                v-model="url"
                type="url"
                inputmode="url"
                maxlength="300"
                placeholder="https://youtu.be/…"
                @blur="commitUrl"
                @keydown.enter.prevent="commitUrl"
            />
            <p
                v-if="url && parsed"
                class="flex items-center gap-1.5 text-xs text-success-ink"
            >
                <CircleCheck class="size-3.5" aria-hidden="true" />
                Video de {{ parsed.provider === 'vimeo' ? 'Vimeo' : 'YouTube' }}
                reconocido
            </p>
            <p
                v-else-if="url"
                class="flex items-center gap-1.5 text-xs text-danger-ink"
            >
                <TriangleAlert class="size-3.5" aria-hidden="true" />
                Solo se aceptan enlaces de youtube.com, youtu.be o vimeo.com.
            </p>
            <p v-else class="text-xs text-muted-foreground">
                Copia el enlace desde «Compartir» en YouTube o Vimeo.
            </p>
        </div>
        <div class="grid gap-1.5">
            <Label :for="`embed-title-${props.block.id}`"
                >Descripción del video</Label
            >
            <Input
                :id="`embed-title-${props.block.id}`"
                :model-value="str('title')"
                maxlength="160"
                placeholder="Ej. Instalación de una cocina en roble"
                @update:model-value="(v) => setLive('title', String(v))"
                @blur="settle"
            />
            <p class="text-xs text-muted-foreground">
                La leen los lectores de pantalla.
            </p>
        </div>
        <div class="grid gap-1.5">
            <Label>Proporción</Label>
            <ToggleGroup
                type="single"
                variant="outline"
                size="sm"
                :model-value="str('aspect', '16:9')"
                aria-label="Proporción del video"
                @update:model-value="(v) => v && set({ aspect: v })"
            >
                <ToggleGroupItem value="16:9">16:9</ToggleGroupItem>
                <ToggleGroupItem value="4:3">4:3</ToggleGroupItem>
                <ToggleGroupItem value="1:1">1:1</ToggleGroupItem>
                <ToggleGroupItem value="9:16">Vertical</ToggleGroupItem>
            </ToggleGroup>
        </div>
    </template>

    <template v-else-if="props.block.type === 'gallery'">
        <div class="grid gap-2">
            <div class="flex items-center justify-between">
                <Label>Fotos ({{ items.length }}/24)</Label>
                <Button
                    variant="outline"
                    size="sm"
                    :disabled="items.length >= 24"
                    @click="pickerOpen = true"
                >
                    <ImagePlus class="size-4" /> Agregar
                </Button>
            </div>
            <p v-if="!items.length" class="text-xs text-muted-foreground">
                Elige varias fotos públicas de la biblioteca multimedia.
            </p>
            <ul class="grid gap-2">
                <li
                    v-for="(item, i) in items"
                    :key="item.mediaId"
                    class="flex gap-2 rounded-lg border border-border p-2"
                >
                    <span
                        class="size-14 shrink-0 overflow-hidden rounded-md bg-muted"
                    >
                        <img
                            v-if="thumb(item.mediaId)"
                            :src="thumb(item.mediaId) ?? ''"
                            alt=""
                            class="h-full w-full object-cover"
                        />
                    </span>
                    <div class="grid min-w-0 flex-1 gap-1">
                        <Input
                            :model-value="item.alt"
                            class="h-8 text-xs"
                            maxlength="200"
                            :aria-label="`Texto alternativo de la foto ${i + 1}`"
                            placeholder="Texto alternativo"
                            @update:model-value="
                                (v) => setItemText(i, 'alt', String(v))
                            "
                            @blur="settle"
                        />
                        <Input
                            :model-value="item.caption"
                            class="h-8 text-xs"
                            maxlength="160"
                            :aria-label="`Pie de foto ${i + 1}`"
                            placeholder="Pie de foto (opcional)"
                            @update:model-value="
                                (v) => setItemText(i, 'caption', String(v))
                            "
                            @blur="settle"
                        />
                    </div>
                    <div class="flex flex-col">
                        <Button
                            variant="ghost"
                            size="icon"
                            class="size-7"
                            :disabled="i === 0"
                            aria-label="Mover antes"
                            @click="moveItem(i, -1)"
                            ><ArrowUp class="size-3.5"
                        /></Button>
                        <Button
                            variant="ghost"
                            size="icon"
                            class="size-7"
                            :disabled="i === items.length - 1"
                            aria-label="Mover después"
                            @click="moveItem(i, 1)"
                            ><ArrowDown class="size-3.5"
                        /></Button>
                        <Button
                            variant="ghost"
                            size="icon"
                            class="size-7 text-danger-ink"
                            :aria-label="`Quitar la foto ${i + 1}`"
                            @click="removeItem(i)"
                            ><Trash2 class="size-3.5"
                        /></Button>
                    </div>
                </li>
            </ul>
        </div>
        <div class="grid gap-1.5">
            <Label>Acomodo</Label>
            <ToggleGroup
                type="single"
                variant="outline"
                size="sm"
                :model-value="str('layout', 'grid')"
                aria-label="Acomodo de la galería"
                @update:model-value="(v) => v && set({ layout: v })"
            >
                <ToggleGroupItem value="grid">Cuadrícula</ToggleGroupItem>
                <ToggleGroupItem value="mosaic">Mosaico</ToggleGroupItem>
                <ToggleGroupItem value="carousel">Carrusel</ToggleGroupItem>
            </ToggleGroup>
        </div>
        <div class="grid gap-1.5">
            <Label>Columnas</Label>
            <ToggleGroup
                type="single"
                variant="outline"
                size="sm"
                :model-value="String(p.columns ?? 3)"
                aria-label="Columnas"
                @update:model-value="(v) => v && set({ columns: Number(v) })"
            >
                <ToggleGroupItem v-for="n in 4" :key="n" :value="String(n)">{{
                    n
                }}</ToggleGroupItem>
            </ToggleGroup>
            <p class="text-xs text-muted-foreground">
                En móvil se reducen solas a una o dos.
            </p>
        </div>
        <div class="grid gap-1.5">
            <Label>Proporción de las fotos</Label>
            <ToggleGroup
                type="single"
                variant="outline"
                size="sm"
                :model-value="str('aspect', '4:3')"
                aria-label="Proporción de las fotos"
                @update:model-value="(v) => v && set({ aspect: v })"
            >
                <ToggleGroupItem value="4:3">4:3</ToggleGroupItem>
                <ToggleGroupItem value="1:1">1:1</ToggleGroupItem>
                <ToggleGroupItem value="3:4">3:4</ToggleGroupItem>
                <ToggleGroupItem value="16:9">16:9</ToggleGroupItem>
            </ToggleGroup>
        </div>
        <div class="grid gap-1.5">
            <Label>Separación</Label>
            <ToggleGroup
                type="single"
                variant="outline"
                size="sm"
                :model-value="str('gap', 'md')"
                aria-label="Separación entre fotos"
                @update:model-value="(v) => v && set({ gap: v })"
            >
                <ToggleGroupItem value="sm">Poca</ToggleGroupItem>
                <ToggleGroupItem value="md">Media</ToggleGroupItem>
                <ToggleGroupItem value="lg">Amplia</ToggleGroupItem>
            </ToggleGroup>
        </div>
        <label class="flex items-center justify-between gap-2 text-sm">
            Abrir en pantalla completa al tocar
            <Switch
                :model-value="p.lightbox !== false"
                @update:model-value="(v: boolean) => set({ lightbox: v })"
            />
        </label>
        <label class="flex items-center justify-between gap-2 text-sm">
            Mostrar origen (render o proyecto real)
            <Switch
                :model-value="p.showOrigin !== false"
                @update:model-value="(v: boolean) => set({ showOrigin: v })"
            />
        </label>

        <MediaPicker
            v-model:open="pickerOpen"
            type="image"
            multiple
            collection="sitio/editor"
            @select="onPick"
        />
    </template>
</template>
