<script setup lang="ts">
import {
    AlignVerticalJustifyCenter,
    AlignVerticalJustifyEnd,
    AlignVerticalJustifyStart,
    Copy,
    ImagePlus,
    Trash2,
} from '@lucide/vue';
import { computed, inject, ref } from 'vue';
import ConfirmDialog from '@/components/ds/ConfirmDialog.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import MediaPicker from '@/components/web/admin/MediaPicker.vue';
import type { MediaItem } from '@/types/web';
import { GRADIENTS, SURFACE_TOKENS } from '../tokens';
import type {
    RenderSection,
    SectionBackground,
    SectionSettings,
} from '../types';
import ColorField from './ColorField.vue';
import InspectorGroup from './InspectorGroup.vue';
import { editorKey } from './keys';
import SbSlider from './SbSlider.vue';

/** Ajustes de la sección: fondo, espaciado, alto, ancho, ancla. */
const props = defineProps<{ section: RenderSection; index: number }>();

const editor = inject(editorKey)!;

const s = computed(() => props.section.layout.settings);
const bg = computed(() => s.value.background);

function set(patch: Partial<SectionSettings>, record = true) {
    editor.updateSettings(props.section.key, patch, record);
}

function setBg(patch: Partial<SectionBackground>, record = true) {
    set({ background: { ...bg.value, ...patch } }, record);
}

const pickerOpen = ref(false);
function onPick(items: MediaItem[]) {
    const item = items[0];
    if (!item) return;
    editor.library.value = { ...editor.library.value, [String(item.id)]: item };
    setBg({
        kind: 'image',
        mediaId: item.id,
        overlay: Math.max(bg.value.overlay, 40),
    });
}
const bgRef = computed(() =>
    bg.value.mediaId !== null
        ? (editor.library.value[String(bg.value.mediaId)] ?? null)
        : null,
);

const deleteOpen = ref(false);
const name = computed(() => s.value.name || 'Sección');
</script>

<template>
    <div>
        <div class="flex items-center gap-2 border-b border-border px-4 py-3">
            <div class="min-w-0 flex-1">
                <p class="truncate text-sm font-semibold">{{ name }}</p>
                <p class="text-xs text-muted-foreground">
                    Sección {{ props.index + 1 }} ·
                    {{ props.section.layout.blocks.length }} elementos
                </p>
            </div>
            <Button
                variant="ghost"
                size="icon"
                class="size-8"
                aria-label="Duplicar sección"
                title="Duplicar sección"
                @click="editor.duplicateSection(props.section.key)"
            >
                <Copy class="size-4" />
            </Button>
            <Button
                variant="ghost"
                size="icon"
                class="size-8 text-danger-ink hover:text-danger-ink"
                aria-label="Eliminar sección"
                title="Eliminar sección"
                @click="deleteOpen = true"
            >
                <Trash2 class="size-4" />
            </Button>
        </div>

        <InspectorGroup title="Sección">
            <div class="grid gap-1.5">
                <Label :for="`sn-${props.section.key}`">Nombre interno</Label>
                <Input
                    :id="`sn-${props.section.key}`"
                    :model-value="s.name"
                    maxlength="60"
                    placeholder="p. ej. Cocinas destacadas"
                    @update:model-value="(v) => set({ name: String(v) }, false)"
                    @blur="editor.live(() => {}, true)"
                />
            </div>
            <label class="flex items-center justify-between gap-2 text-sm">
                Mostrar en el sitio
                <Switch
                    :model-value="props.section.isVisible"
                    @update:model-value="
                        () => editor.toggleSectionVisible(props.section.key)
                    "
                />
            </label>
        </InspectorGroup>

        <InspectorGroup title="Fondo">
            <ToggleGroup
                type="single"
                variant="outline"
                size="sm"
                class="w-full"
                :model-value="bg.kind"
                aria-label="Tipo de fondo"
                @update:model-value="
                    (v) => v && setBg({ kind: v as SectionBackground['kind'] })
                "
            >
                <ToggleGroupItem value="none" class="flex-1 px-1 text-xs"
                    >Ninguno</ToggleGroupItem
                >
                <ToggleGroupItem value="color" class="flex-1 px-1 text-xs"
                    >Color</ToggleGroupItem
                >
                <ToggleGroupItem value="gradient" class="flex-1 px-1 text-xs"
                    >Degradado</ToggleGroupItem
                >
                <ToggleGroupItem value="image" class="flex-1 px-1 text-xs"
                    >Foto</ToggleGroupItem
                >
            </ToggleGroup>

            <ColorField
                v-if="bg.kind === 'color'"
                :model-value="bg.color"
                :tokens="SURFACE_TOKENS"
                label="Color de fondo"
                @update:model-value="(v) => setBg({ color: v })"
            />

            <div
                v-else-if="bg.kind === 'gradient'"
                class="grid grid-cols-5 gap-1.5"
                role="radiogroup"
                aria-label="Degradado"
            >
                <button
                    v-for="g in GRADIENTS"
                    :key="g.value"
                    type="button"
                    role="radio"
                    :aria-checked="bg.gradient === g.value"
                    :aria-label="g.label"
                    :title="g.label"
                    class="aspect-square rounded-lg ring-1 ring-black/10 transition-transform hover:scale-105 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    :class="
                        bg.gradient === g.value &&
                        'ring-2 ring-primary ring-offset-2 ring-offset-card'
                    "
                    :style="{ background: g.css }"
                    @click="setBg({ gradient: g.value })"
                />
            </div>

            <template v-else-if="bg.kind === 'image'">
                <button
                    type="button"
                    class="relative grid aspect-[16/9] place-items-center overflow-hidden rounded-lg bg-muted ring-1 ring-border hover:ring-primary/40 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    @click="pickerOpen = true"
                >
                    <img
                        v-if="bgRef?.thumbnailUrl || bgRef?.url"
                        :src="bgRef?.thumbnailUrl ?? bgRef?.url ?? ''"
                        alt=""
                        class="absolute inset-0 h-full w-full object-cover"
                    />
                    <span
                        class="relative inline-flex items-center gap-1.5 rounded-full bg-white/90 px-3 py-1.5 text-xs font-medium shadow-sm"
                    >
                        <ImagePlus class="size-3.5" aria-hidden="true" />
                        {{ bg.mediaId ? 'Cambiar foto' : 'Elegir foto' }}
                    </span>
                </button>
                <div class="grid gap-2">
                    <div class="flex items-center justify-between">
                        <Label class="text-xs text-muted-foreground"
                            >Velo para legibilidad</Label
                        ><span class="text-xs tabular-nums"
                            >{{ bg.overlay }} %</span
                        >
                    </div>
                    <SbSlider
                        :model-value="bg.overlay"
                        :min="0"
                        :max="90"
                        :step="5"
                        label="Velo sobre la foto"
                        @update:model-value="
                            (v) => setBg({ overlay: v }, false)
                        "
                        @commit="editor.live(() => {}, true)"
                    />
                </div>
                <ToggleGroup
                    type="single"
                    variant="outline"
                    size="sm"
                    :model-value="bg.overlayStyle"
                    aria-label="Forma del velo"
                    @update:model-value="
                        (v) =>
                            v &&
                            setBg({
                                overlayStyle:
                                    v as SectionBackground['overlayStyle'],
                            })
                    "
                >
                    <ToggleGroupItem value="flat" class="text-xs"
                        >Parejo</ToggleGroupItem
                    >
                    <ToggleGroupItem value="left" class="text-xs"
                        >Desde la izquierda</ToggleGroupItem
                    >
                    <ToggleGroupItem value="bottom" class="text-xs"
                        >Desde abajo</ToggleGroupItem
                    >
                </ToggleGroup>
            </template>

            <div class="grid gap-1.5">
                <Label class="text-xs text-muted-foreground"
                    >Color del texto</Label
                >
                <ToggleGroup
                    type="single"
                    variant="outline"
                    size="sm"
                    :model-value="s.tone"
                    aria-label="Color del texto"
                    @update:model-value="
                        (v) => v && set({ tone: v as SectionSettings['tone'] })
                    "
                >
                    <ToggleGroupItem value="auto">Automático</ToggleGroupItem>
                    <ToggleGroupItem value="light">Oscuro</ToggleGroupItem>
                    <ToggleGroupItem value="dark">Claro</ToggleGroupItem>
                </ToggleGroup>
            </div>
        </InspectorGroup>

        <InspectorGroup title="Espacio y tamaño">
            <div class="grid gap-2">
                <div class="grid gap-1">
                    <Label class="text-xs text-muted-foreground"
                        >Espacio arriba y abajo</Label
                    >
                    <Select
                        :model-value="s.padding"
                        @update:model-value="
                            (v) =>
                                set({
                                    padding: v as SectionSettings['padding'],
                                })
                        "
                    >
                        <SelectTrigger
                            class="h-9 w-full"
                            aria-label="Espacio arriba y abajo"
                            ><SelectValue
                        /></SelectTrigger>
                        <SelectContent>
                            <SelectItem value="none">Ninguno</SelectItem>
                            <SelectItem value="sm">Chico</SelectItem>
                            <SelectItem value="md">Mediano</SelectItem>
                            <SelectItem value="lg">Amplio</SelectItem>
                            <SelectItem value="xl">Muy amplio</SelectItem>
                            <SelectItem value="hero"
                                >Portada (con menú)</SelectItem
                            >
                        </SelectContent>
                    </Select>
                </div>
                <div class="grid gap-1">
                    <Label class="text-xs text-muted-foreground"
                        >Alto mínimo</Label
                    >
                    <Select
                        :model-value="s.minHeight"
                        @update:model-value="
                            (v) =>
                                set({
                                    minHeight:
                                        v as SectionSettings['minHeight'],
                                })
                        "
                    >
                        <SelectTrigger
                            class="h-9 w-full"
                            aria-label="Alto mínimo"
                            ><SelectValue
                        /></SelectTrigger>
                        <SelectContent>
                            <SelectItem value="auto"
                                >Según contenido</SelectItem
                            >
                            <SelectItem value="sm">Bajo</SelectItem>
                            <SelectItem value="md">Medio</SelectItem>
                            <SelectItem value="lg">Alto</SelectItem>
                            <SelectItem value="screen"
                                >Pantalla completa</SelectItem
                            >
                        </SelectContent>
                    </Select>
                </div>
            </div>
            <div class="flex items-center justify-between gap-2">
                <Label class="text-xs text-muted-foreground"
                    >Contenido vertical</Label
                >
                <ToggleGroup
                    type="single"
                    variant="outline"
                    size="sm"
                    :model-value="s.verticalAlign"
                    aria-label="Alineación vertical"
                    @update:model-value="
                        (v) =>
                            v &&
                            set({
                                verticalAlign:
                                    v as SectionSettings['verticalAlign'],
                            })
                    "
                >
                    <ToggleGroupItem value="start" aria-label="Arriba"
                        ><AlignVerticalJustifyStart class="size-4"
                    /></ToggleGroupItem>
                    <ToggleGroupItem value="center" aria-label="Centro"
                        ><AlignVerticalJustifyCenter class="size-4"
                    /></ToggleGroupItem>
                    <ToggleGroupItem value="end" aria-label="Abajo"
                        ><AlignVerticalJustifyEnd class="size-4"
                    /></ToggleGroupItem>
                </ToggleGroup>
            </div>
            <label class="flex items-center justify-between gap-2 text-sm">
                <span
                    >Panel redondeado<span
                        class="block text-xs text-muted-foreground"
                        >El fondo queda dentro de una tarjeta.</span
                    ></span
                >
                <Switch
                    :model-value="s.surface === 'panel'"
                    @update:model-value="
                        (v: boolean) => set({ surface: v ? 'panel' : 'none' })
                    "
                />
            </label>
            <label class="flex items-center justify-between gap-2 text-sm">
                <span
                    >A todo lo ancho<span
                        class="block text-xs text-muted-foreground"
                        >Los elementos usan todo el ancho de la pantalla.</span
                    ></span
                >
                <Switch
                    :model-value="s.fullBleed"
                    @update:model-value="(v: boolean) => set({ fullBleed: v })"
                />
            </label>
            <label
                v-if="props.index === 0"
                class="flex items-center justify-between gap-2 text-sm"
            >
                <span
                    >Menú transparente encima<span
                        class="block text-xs text-muted-foreground"
                        >Para portadas con foto o fondo oscuro.</span
                    ></span
                >
                <Switch
                    :model-value="s.overlayHeader"
                    @update:model-value="
                        (v: boolean) => set({ overlayHeader: v })
                    "
                />
            </label>
        </InspectorGroup>

        <InspectorGroup
            title="Enlace a esta sección"
            description="Para botones que llevan aquí, p. ej. /#como-funciona."
        >
            <div class="flex items-center gap-1.5">
                <span class="text-sm text-muted-foreground">#</span>
                <Input
                    :model-value="s.anchor"
                    maxlength="40"
                    placeholder="nombre-de-seccion"
                    aria-label="Ancla"
                    @update:model-value="
                        (v) =>
                            set(
                                {
                                    anchor: String(v)
                                        .toLowerCase()
                                        .replace(/[^a-z0-9-]+/g, '-')
                                        .replace(/^-+/, ''),
                                },
                                false,
                            )
                    "
                    @blur="set({ anchor: s.anchor.replace(/-+$/, '') })"
                />
            </div>
        </InspectorGroup>

        <InspectorGroup
            title="Recorrido guiado"
            description="Qué paso del recorrido del visitante representa esta sección (el riel lateral y el botón flotante lo usan)."
        >
            <Select
                :model-value="s.journey || 'none'"
                @update:model-value="
                    (v) =>
                        set({
                            journey: (v === 'none'
                                ? ''
                                : v) as SectionSettings['journey'],
                        })
                "
            >
                <SelectTrigger
                    class="h-9 w-full"
                    aria-label="Paso del recorrido"
                    ><SelectValue
                /></SelectTrigger>
                <SelectContent>
                    <SelectItem value="none">Ninguno</SelectItem>
                    <SelectItem value="inspirate">Inspírate</SelectItem>
                    <SelectItem value="espacio">Elige tu espacio</SelectItem>
                    <SelectItem value="proceso">Así trabajamos</SelectItem>
                    <SelectItem value="visita">Visita y medición</SelectItem>
                    <SelectItem value="pagos">Formas de pago</SelectItem>
                    <SelectItem value="agenda">Agenda</SelectItem>
                </SelectContent>
            </Select>
        </InspectorGroup>

        <MediaPicker
            v-model:open="pickerOpen"
            type="image"
            collection="sitio/editor"
            @select="onPick"
        />
        <ConfirmDialog
            v-model:open="deleteOpen"
            :title="`¿Eliminar «${name}»?`"
            description="La sección se quita del borrador. Puedes deshacerlo con Ctrl+Z."
            confirm-label="Eliminar sección"
            tone="destructive"
            :confirm="() => editor.deleteSection(props.section.key)"
        />
    </div>
</template>
