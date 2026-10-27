<script setup lang="ts">
import {
    AlignCenter,
    AlignLeft,
    AlignRight,
    ArrowDownToLine,
    ArrowUpToLine,
    ChevronDown,
    ChevronUp,
    Copy,
    Crop,
    ImagePlus,
    Lock,
    LockOpen,
    Pencil,
    Play,
    Plus,
    Trash2,
    X,
} from '@lucide/vue';
import { computed, inject, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    NumberField,
    NumberFieldContent,
    NumberFieldDecrement,
    NumberFieldIncrement,
    NumberFieldInput,
} from '@/components/ui/number-field';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import MediaPicker from '@/components/web/admin/MediaPicker.vue';
import SchemaFieldInput from '@/components/web/admin/SchemaFieldInput.vue';
import type { MediaItem, VisitPolicyData } from '@/types/web';
import { COLUMNS, MAX_HEIGHT, MAX_ROW } from '../grid';
import { ICONS } from '../icons';
import { BLOCKS } from '../registry';
import { INK_TOKENS, MOTION_PRESETS, SURFACE_TOKENS } from '../tokens';
import type { Block } from '../types';
import ColorField from './ColorField.vue';
import InspectorGroup from './InspectorGroup.vue';
import { editorKey } from './keys';
import { previewMotion } from './motion';
import SbSlider from './SbSlider.vue';

const props = defineProps<{
    block: Block;
    sectionKey: string;
    visitPolicy: VisitPolicyData | null;
}>();

const editor = inject(editorKey)!;

const def = computed(() => BLOCKS[props.block.type]);
const p = computed(() => props.block.props);
const dynamic = computed(() => editor.dynamic[props.block.type] ?? null);
const multiple = computed(() => editor.selection.blocks.length > 1);

function str(key: string, fallback = ''): string {
    const v = p.value[key];

    return typeof v === 'string' ? v : fallback;
}

function num(key: string, fallback = 0): number {
    const v = p.value[key];

    return typeof v === 'number' ? v : fallback;
}

function set(key: string, value: unknown) {
    editor.updateProps(props.sectionKey, props.block.id, { [key]: value });
}

/** Escritura continua (teclear, deslizar): un paso de historial al terminar. */
function setLive(key: string, value: unknown) {
    editor.live(() => {
        const b = editor.block(props.sectionKey, props.block.id);
        if (b) b.props = { ...b.props, [key]: value };
    }, false);
}

function settle() {
    editor.live(() => {}, true);
}

function setGeometry(key: 'x' | 'y' | 'w' | 'h', value: number | undefined) {
    if (value === undefined || Number.isNaN(value)) return;
    const b = props.block;
    const next = { x: b.x, y: b.y, w: b.w, h: b.h, [key]: Math.round(value) };
    next.x = Math.min(Math.max(0, next.x), COLUMNS - 1);
    next.w = Math.min(Math.max(1, next.w), COLUMNS - next.x);
    next.y = Math.min(Math.max(0, next.y), MAX_ROW);
    next.h = Math.min(Math.max(1, next.h), MAX_HEIGHT);
    editor.updateBlock(props.sectionKey, b.id, next);
}

function toggleHide(device: 'tablet' | 'mobile', hidden: boolean) {
    const set_ = new Set(props.block.hideOn);
    if (hidden) set_.add(device);
    else set_.delete(device);
    editor.updateBlock(props.sectionKey, props.block.id, { hideOn: [...set_] });
}

function play() {
    previewMotion(
        document.querySelector(`[data-block-id="${props.block.id}"]`),
        props.block.motion.preset,
        0,
    );
}

// Errores del servidor para este bloque.
const errorPrefix = computed(() => {
    const si = editor.sections.value.findIndex(
        (s) => s.key === props.sectionKey,
    );
    const bi =
        editor
            .section(props.sectionKey)
            ?.layout.blocks.findIndex((b) => b.id === props.block.id) ?? -1;

    return `sections.${si}.layout.blocks.${bi}.`;
});
const blockErrors = computed(() =>
    Object.entries(editor.errors.value)
        .filter(([path]) => path.startsWith(errorPrefix.value))
        .map(([path, messages]) => ({
            path: path.slice(errorPrefix.value.length),
            message: messages[0],
        })),
);
const fieldErrors = computed(
    () =>
        Object.fromEntries(
            blockErrors.value
                .filter((e) => e.path.startsWith('props.'))
                .map((e) => [`content.${e.path.slice(6)}`, e.message]),
        ) as Record<string, string | undefined>,
);

// Medios
const pickerOpen = ref(false);
const pickerType = computed(() =>
    props.block.type === 'video' ? 'video' : 'image',
);
function onPick(items: MediaItem[]) {
    const item = items[0];
    if (!item) return;
    editor.library.value = { ...editor.library.value, [String(item.id)]: item };
    set('mediaId', item.id);
}
const mediaRef = computed(() => {
    const id = p.value.mediaId;

    return typeof id === 'number'
        ? (editor.library.value[String(id)] ?? null)
        : null;
});

// Lista
const listItems = computed(() =>
    Array.isArray(p.value.items)
        ? (p.value.items as { text: string }[]).map((i) => i.text ?? '')
        : [],
);
function setListItem(index: number, text: string, record: boolean) {
    const next = [...listItems.value];
    next[index] = text;
    const value = next.map((t) => ({ text: t }));
    if (record) set('items', value);
    else setLive('items', value);
}
function addListItem() {
    set(
        'items',
        [...listItems.value, 'Nuevo punto'].map((t) => ({ text: t })),
    );
}
function removeListItem(index: number) {
    set(
        'items',
        listItems.value.filter((_, i) => i !== index).map((t) => ({ text: t })),
    );
}

const LINKS = [
    { value: '/solicitar', label: 'Solicitar asesoría' },
    { value: '/agendar-visita', label: 'Agendar visita' },
    { value: '/catalogo', label: 'Catálogo' },
    { value: '/inspiracion', label: 'Inspiración' },
    { value: '/preguntas-frecuentes', label: 'Preguntas frecuentes' },
    { value: '/#como-funciona', label: 'Cómo funciona' },
];

const BUTTON_VARIANTS = [
    { value: 'primary', label: 'Principal (eucalipto)' },
    { value: 'secondary', label: 'Secundario (piedra)' },
    { value: 'outline', label: 'Contorno' },
    { value: 'light', label: 'Claro (sobre fondo oscuro)' },
    { value: 'outline-light', label: 'Contorno claro' },
    { value: 'oak', label: 'Roble' },
    { value: 'link', label: 'Enlace con flecha' },
];

const SHAPES = [
    { value: 'rect', label: 'Rectángulo' },
    { value: 'circle', label: 'Círculo' },
    { value: 'ring', label: 'Aro' },
    { value: 'line', label: 'Línea divisoria' },
    { value: 'rings', label: 'Anillos concéntricos' },
    { value: 'swatches', label: 'Muestrario de acabados' },
    { value: 'elevation', label: 'Alzado de cocina' },
];
</script>

<template>
    <div>
        <!-- Encabezado -->
        <div class="flex items-center gap-2.5 border-b border-border px-4 py-3">
            <span
                class="grid size-8 place-items-center rounded-lg bg-primary-soft text-primary"
            >
                <component :is="def.icon" class="size-4" aria-hidden="true" />
            </span>
            <div class="min-w-0 flex-1">
                <p class="truncate text-sm font-semibold">
                    {{
                        multiple
                            ? `${editor.selection.blocks.length} elementos`
                            : def.label
                    }}
                </p>
                <p class="text-xs text-muted-foreground tabular-nums">
                    Columna {{ props.block.x + 1 }} · fila
                    {{ props.block.y + 1 }} · {{ props.block.w }}×{{
                        props.block.h
                    }}
                </p>
            </div>
            <Button
                variant="ghost"
                size="icon"
                class="size-8"
                :aria-label="props.block.locked ? 'Desbloquear' : 'Bloquear'"
                :title="props.block.locked ? 'Desbloquear' : 'Bloquear'"
                @click="editor.toggleLock()"
            >
                <Lock v-if="props.block.locked" class="size-4" />
                <LockOpen v-else class="size-4" />
            </Button>
            <Button
                variant="ghost"
                size="icon"
                class="size-8"
                aria-label="Duplicar (Ctrl+D)"
                title="Duplicar (Ctrl+D)"
                @click="editor.duplicateSelected()"
            >
                <Copy class="size-4" />
            </Button>
            <Button
                variant="ghost"
                size="icon"
                class="size-8 text-danger-ink hover:text-danger-ink"
                aria-label="Eliminar (Supr)"
                title="Eliminar (Supr)"
                @click="editor.deleteSelected()"
            >
                <Trash2 class="size-4" />
            </Button>
        </div>

        <div
            v-if="blockErrors.length"
            role="alert"
            class="mx-4 mt-4 rounded-lg bg-danger-soft p-3 text-xs text-danger-ink"
        >
            <p v-for="e in blockErrors" :key="e.path">{{ e.message }}</p>
        </div>

        <!-- Contenido -->
        <InspectorGroup v-if="!multiple" title="Contenido">
            <template v-if="props.block.type === 'heading'">
                <div class="grid gap-1.5">
                    <Label :for="`h-${props.block.id}`">Texto</Label>
                    <Textarea
                        :id="`h-${props.block.id}`"
                        :model-value="str('text')"
                        rows="3"
                        maxlength="200"
                        @update:model-value="(v) => setLive('text', String(v))"
                        @blur="settle"
                    />
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <div class="grid gap-1.5">
                        <Label>Nivel</Label>
                        <Select
                            :model-value="str('level', 'h2')"
                            @update:model-value="(v) => set('level', v)"
                        >
                            <SelectTrigger
                                class="h-9 w-full"
                                aria-label="Nivel del título"
                                ><SelectValue
                            /></SelectTrigger>
                            <SelectContent>
                                <SelectItem value="h1"
                                    >H1 · principal</SelectItem
                                >
                                <SelectItem value="h2">H2 · sección</SelectItem>
                                <SelectItem value="h3"
                                    >H3 · subtítulo</SelectItem
                                >
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Tamaño</Label>
                        <ToggleGroup
                            type="single"
                            variant="outline"
                            size="sm"
                            :model-value="str('size', 'lg')"
                            aria-label="Tamaño del título"
                            @update:model-value="(v) => v && set('size', v)"
                        >
                            <ToggleGroupItem
                                value="xl"
                                aria-label="Extra grande"
                                >XL</ToggleGroupItem
                            >
                            <ToggleGroupItem value="lg" aria-label="Grande"
                                >L</ToggleGroupItem
                            >
                            <ToggleGroupItem value="md" aria-label="Mediano"
                                >M</ToggleGroupItem
                            >
                            <ToggleGroupItem value="sm" aria-label="Chico"
                                >S</ToggleGroupItem
                            >
                        </ToggleGroup>
                    </div>
                </div>
                <p
                    v-if="str('level') === 'h1' && editor.h1Count.value > 1"
                    class="rounded-md bg-warning-soft p-2 text-xs text-warning-ink"
                >
                    Ya hay otro título H1 en la página. Para buscadores conviene
                    uno solo: cambia este a H2.
                </p>
            </template>

            <template v-else-if="props.block.type === 'text'">
                <Button
                    variant="outline"
                    size="sm"
                    class="justify-start"
                    @click="editor.doubleClick(props.sectionKey, props.block)"
                >
                    <Pencil class="size-4" /> Editar el texto en el lienzo
                </Button>
                <p class="text-xs text-muted-foreground">
                    Doble clic sobre el texto para escribir. Usa Ctrl+B para
                    negritas y Ctrl+I para cursivas.
                </p>
                <div class="grid gap-1.5">
                    <Label>Tamaño</Label>
                    <ToggleGroup
                        type="single"
                        variant="outline"
                        size="sm"
                        :model-value="str('size', 'base')"
                        aria-label="Tamaño del texto"
                        @update:model-value="(v) => v && set('size', v)"
                    >
                        <ToggleGroupItem value="lead"
                            >Entradilla</ToggleGroupItem
                        >
                        <ToggleGroupItem value="base">Normal</ToggleGroupItem>
                        <ToggleGroupItem value="sm">Chico</ToggleGroupItem>
                    </ToggleGroup>
                </div>
            </template>

            <template v-else-if="props.block.type === 'button'">
                <div class="grid gap-1.5">
                    <Label :for="`bl-${props.block.id}`">Texto del botón</Label>
                    <Input
                        :id="`bl-${props.block.id}`"
                        :model-value="str('label')"
                        maxlength="60"
                        @update:model-value="(v) => setLive('label', String(v))"
                        @blur="settle"
                    />
                </div>
                <div class="grid gap-1.5">
                    <Label :for="`bh-${props.block.id}`">Enlace</Label>
                    <Input
                        :id="`bh-${props.block.id}`"
                        :model-value="str('href')"
                        maxlength="300"
                        placeholder="/solicitar o https://"
                        @update:model-value="(v) => setLive('href', String(v))"
                        @blur="settle"
                    />
                    <div class="flex flex-wrap gap-1">
                        <button
                            v-for="link in LINKS"
                            :key="link.value"
                            type="button"
                            class="rounded-full border border-border px-2 py-0.5 text-[11px] hover:border-primary/40 hover:bg-primary-soft"
                            :class="
                                str('href') === link.value &&
                                'border-primary bg-primary-soft text-primary'
                            "
                            @click="set('href', link.value)"
                        >
                            {{ link.label }}
                        </button>
                    </div>
                </div>
                <div class="grid gap-1.5">
                    <Label>Estilo</Label>
                    <Select
                        :model-value="str('variant', 'primary')"
                        @update:model-value="(v) => set('variant', v)"
                    >
                        <SelectTrigger
                            class="h-9 w-full"
                            aria-label="Estilo del botón"
                            ><SelectValue
                        /></SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="v in BUTTON_VARIANTS"
                                :key="v.value"
                                :value="v.value"
                                >{{ v.label }}</SelectItem
                            >
                        </SelectContent>
                    </Select>
                </div>
                <div class="flex items-center justify-between gap-3">
                    <ToggleGroup
                        type="single"
                        variant="outline"
                        size="sm"
                        :model-value="str('size', 'lg')"
                        aria-label="Tamaño del botón"
                        @update:model-value="(v) => v && set('size', v)"
                    >
                        <ToggleGroupItem value="md">Mediano</ToggleGroupItem>
                        <ToggleGroupItem value="lg">Grande</ToggleGroupItem>
                    </ToggleGroup>
                    <label class="flex items-center gap-2 text-sm">
                        <Switch
                            :model-value="p.icon !== false"
                            @update:model-value="(v: boolean) => set('icon', v)"
                        />
                        Flecha
                    </label>
                </div>
            </template>

            <template v-else-if="props.block.type === 'image'">
                <div class="flex items-center gap-3">
                    <span
                        class="grid size-16 shrink-0 place-items-center overflow-hidden rounded-lg bg-muted ring-1 ring-border"
                    >
                        <img
                            v-if="mediaRef?.thumbnailUrl || mediaRef?.url"
                            :src="mediaRef?.thumbnailUrl ?? mediaRef?.url ?? ''"
                            alt=""
                            class="h-full w-full object-cover"
                        />
                        <ImagePlus
                            v-else
                            class="size-5 text-muted-foreground"
                            aria-hidden="true"
                        />
                    </span>
                    <div class="grid flex-1 gap-1.5">
                        <Button
                            variant="outline"
                            size="sm"
                            @click="pickerOpen = true"
                        >
                            {{
                                typeof p.mediaId === 'number'
                                    ? 'Cambiar imagen'
                                    : 'Elegir imagen'
                            }}
                        </Button>
                        <Button
                            v-if="typeof p.mediaId === 'number'"
                            variant="ghost"
                            size="sm"
                            :disabled="!editor.canInteract.value"
                            @click="
                                editor.doubleClick(
                                    props.sectionKey,
                                    props.block,
                                )
                            "
                        >
                            <Crop class="size-4" /> Encuadrar en el lienzo
                        </Button>
                    </div>
                </div>
                <div class="grid gap-1.5">
                    <Label :for="`alt-${props.block.id}`"
                        >Texto alternativo</Label
                    >
                    <Input
                        :id="`alt-${props.block.id}`"
                        :model-value="str('alt')"
                        maxlength="200"
                        :placeholder="mediaRef?.altText ?? 'Describe la imagen'"
                        @update:model-value="(v) => setLive('alt', String(v))"
                        @blur="settle"
                    />
                    <p class="text-xs text-muted-foreground">
                        Si lo dejas vacío se usa el de la biblioteca.
                    </p>
                </div>
                <div class="grid gap-1.5">
                    <Label>Ajuste</Label>
                    <ToggleGroup
                        type="single"
                        variant="outline"
                        size="sm"
                        :model-value="str('fit', 'cover')"
                        aria-label="Ajuste de la imagen"
                        @update:model-value="(v) => v && set('fit', v)"
                    >
                        <ToggleGroupItem value="cover"
                            >Llenar marco</ToggleGroupItem
                        >
                        <ToggleGroupItem value="contain"
                            >Imagen completa</ToggleGroupItem
                        >
                    </ToggleGroup>
                </div>
                <div class="grid gap-2">
                    <div class="flex items-center justify-between text-sm">
                        <Label>Acercamiento</Label
                        ><span
                            class="text-xs text-muted-foreground tabular-nums"
                            >{{ num('zoom', 1).toFixed(2) }}×</span
                        >
                    </div>
                    <SbSlider
                        :model-value="num('zoom', 1)"
                        :min="1"
                        :max="3"
                        :step="0.05"
                        label="Acercamiento"
                        @update:model-value="(v) => setLive('zoom', v)"
                        @commit="settle"
                    />
                    <div class="flex items-center justify-between text-sm">
                        <Label>Punto focal horizontal</Label
                        ><span
                            class="text-xs text-muted-foreground tabular-nums"
                            >{{ num('focalX', 50) }} %</span
                        >
                    </div>
                    <SbSlider
                        :model-value="num('focalX', 50)"
                        label="Punto focal horizontal"
                        @update:model-value="(v) => setLive('focalX', v)"
                        @commit="settle"
                    />
                    <div class="flex items-center justify-between text-sm">
                        <Label>Punto focal vertical</Label
                        ><span
                            class="text-xs text-muted-foreground tabular-nums"
                            >{{ num('focalY', 50) }} %</span
                        >
                    </div>
                    <SbSlider
                        :model-value="num('focalY', 50)"
                        label="Punto focal vertical"
                        @update:model-value="(v) => setLive('focalY', v)"
                        @commit="settle"
                    />
                </div>
                <label class="flex items-center justify-between gap-2 text-sm">
                    Mostrar origen (render o proyecto real)
                    <Switch
                        :model-value="p.showOrigin !== false"
                        @update:model-value="
                            (v: boolean) => set('showOrigin', v)
                        "
                    />
                </label>
            </template>

            <template v-else-if="props.block.type === 'video'">
                <Button variant="outline" size="sm" @click="pickerOpen = true">
                    {{
                        typeof p.mediaId === 'number'
                            ? 'Cambiar video'
                            : 'Elegir video'
                    }}
                </Button>
                <label class="flex items-center justify-between text-sm"
                    >Reproducir solo (sin sonido)<Switch
                        :model-value="p.autoplay !== false"
                        @update:model-value="
                            (v: boolean) => set('autoplay', v)
                        "
                /></label>
                <label class="flex items-center justify-between text-sm"
                    >Repetir<Switch
                        :model-value="p.loop !== false"
                        @update:model-value="(v: boolean) => set('loop', v)"
                /></label>
                <label class="flex items-center justify-between text-sm"
                    >Mostrar controles<Switch
                        :model-value="p.controls === true"
                        @update:model-value="
                            (v: boolean) => set('controls', v)
                        "
                /></label>
            </template>

            <template v-else-if="props.block.type === 'badge'">
                <div class="grid gap-1.5">
                    <Label :for="`bt-${props.block.id}`">Texto</Label>
                    <Input
                        :id="`bt-${props.block.id}`"
                        :model-value="str('text')"
                        maxlength="80"
                        @update:model-value="(v) => setLive('text', String(v))"
                        @blur="settle"
                    />
                </div>
                <div class="grid gap-1.5">
                    <Label>Estilo</Label>
                    <ToggleGroup
                        type="single"
                        variant="outline"
                        size="sm"
                        :model-value="str('variant', 'eyebrow')"
                        aria-label="Estilo de la etiqueta"
                        @update:model-value="(v) => v && set('variant', v)"
                    >
                        <ToggleGroupItem value="eyebrow">Línea</ToggleGroupItem>
                        <ToggleGroupItem value="pill">Píldora</ToggleGroupItem>
                        <ToggleGroupItem value="glass">Vidrio</ToggleGroupItem>
                        <ToggleGroupItem value="soft">Suave</ToggleGroupItem>
                    </ToggleGroup>
                </div>
            </template>

            <template v-else-if="props.block.type === 'icon'">
                <div
                    class="grid grid-cols-7 gap-1"
                    role="radiogroup"
                    aria-label="Ícono"
                >
                    <button
                        v-for="(icon, name) in ICONS"
                        :key="name"
                        type="button"
                        role="radio"
                        :aria-checked="str('name') === name"
                        :aria-label="icon.label"
                        :title="icon.label"
                        class="grid aspect-square place-items-center rounded-md text-foreground/80 hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring/50 focus-visible:outline-none"
                        :class="
                            str('name') === name &&
                            'bg-primary-soft text-primary ring-1 ring-primary/40'
                        "
                        @click="set('name', name)"
                    >
                        <component
                            :is="icon.component"
                            class="size-4"
                            aria-hidden="true"
                        />
                    </button>
                </div>
                <div class="flex items-center justify-between gap-2">
                    <ToggleGroup
                        type="single"
                        variant="outline"
                        size="sm"
                        :model-value="str('size', 'md')"
                        aria-label="Tamaño del ícono"
                        @update:model-value="(v) => v && set('size', v)"
                    >
                        <ToggleGroupItem value="sm">S</ToggleGroupItem>
                        <ToggleGroupItem value="md">M</ToggleGroupItem>
                        <ToggleGroupItem value="lg">L</ToggleGroupItem>
                        <ToggleGroupItem value="xl">XL</ToggleGroupItem>
                    </ToggleGroup>
                    <label class="flex items-center gap-2 text-sm"
                        ><Switch
                            :model-value="p.framed !== false"
                            @update:model-value="
                                (v: boolean) => set('framed', v)
                            "
                        />
                        Círculo</label
                    >
                </div>
            </template>

            <template v-else-if="props.block.type === 'shape'">
                <div class="grid gap-1.5">
                    <Label>Forma</Label>
                    <Select
                        :model-value="str('kind', 'rect')"
                        @update:model-value="(v) => set('kind', v)"
                    >
                        <SelectTrigger class="h-9 w-full" aria-label="Forma"
                            ><SelectValue
                        /></SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="s in SHAPES"
                                :key="s.value"
                                :value="s.value"
                                >{{ s.label }}</SelectItem
                            >
                        </SelectContent>
                    </Select>
                </div>
                <div
                    v-if="!['swatches', 'elevation'].includes(str('kind'))"
                    class="grid gap-1.5"
                >
                    <Label>Color</Label>
                    <ColorField
                        :model-value="str('color', 'primary-soft')"
                        :tokens="SURFACE_TOKENS"
                        label="Color de la forma"
                        @update:model-value="(v) => set('color', v)"
                    />
                </div>
            </template>

            <template v-else-if="props.block.type === 'stat'">
                <div class="grid grid-cols-[6rem_1fr] gap-2">
                    <div class="grid gap-1.5">
                        <Label :for="`sv-${props.block.id}`">Cifra</Label>
                        <Input
                            :id="`sv-${props.block.id}`"
                            :model-value="str('value')"
                            maxlength="24"
                            placeholder="p. ej. 15"
                            @update:model-value="
                                (v) => setLive('value', String(v))
                            "
                            @blur="settle"
                        />
                    </div>
                    <div class="grid gap-1.5">
                        <Label :for="`sl-${props.block.id}`">Etiqueta</Label>
                        <Input
                            :id="`sl-${props.block.id}`"
                            :model-value="str('label')"
                            maxlength="80"
                            @update:model-value="
                                (v) => setLive('label', String(v))
                            "
                            @blur="settle"
                        />
                    </div>
                </div>
                <p class="text-xs text-muted-foreground">
                    Escribe solo cifras reales y comprobables.
                </p>
            </template>

            <template v-else-if="props.block.type === 'list'">
                <div class="grid gap-1.5">
                    <div
                        v-for="(item, i) in listItems"
                        :key="i"
                        class="flex gap-1.5"
                    >
                        <Input
                            :model-value="item"
                            maxlength="160"
                            :aria-label="`Punto ${i + 1}`"
                            @update:model-value="
                                (v) => setListItem(i, String(v), false)
                            "
                            @blur="settle"
                        />
                        <Button
                            variant="ghost"
                            size="icon"
                            class="size-9 shrink-0"
                            :aria-label="`Quitar punto ${i + 1}`"
                            @click="removeListItem(i)"
                            ><X class="size-4"
                        /></Button>
                    </div>
                    <Button
                        variant="outline"
                        size="sm"
                        :disabled="listItems.length >= 12"
                        @click="addListItem"
                        ><Plus class="size-4" /> Agregar punto</Button
                    >
                </div>
                <ToggleGroup
                    type="single"
                    variant="outline"
                    size="sm"
                    :model-value="str('marker', 'check')"
                    aria-label="Viñeta"
                    @update:model-value="(v) => v && set('marker', v)"
                >
                    <ToggleGroupItem value="check">Palomita</ToggleGroupItem>
                    <ToggleGroupItem value="dot">Punto</ToggleGroupItem>
                    <ToggleGroupItem value="number">Número</ToggleGroupItem>
                </ToggleGroup>
            </template>

            <template v-else-if="props.block.type === 'spacer'">
                <p class="text-sm text-muted-foreground">
                    Un espacio en blanco. Cambia su alto con las asas.
                </p>
            </template>

            <template v-else-if="dynamic">
                <p
                    v-if="props.block.type === 'home_visit'"
                    class="rounded-lg bg-info-soft p-3 text-xs text-info-ink"
                >
                    <template v-if="props.visitPolicy"
                        >Costo vigente:
                        <strong>{{ props.visitPolicy.feeFormatted }}</strong
                        >{{
                            props.visitPolicy.isCreditable
                                ? ' (acreditable)'
                                : ''
                        }}. Se toma de la política de visitas activa.</template
                    >
                    <template v-else
                        >No hay una política de visitas activa: el sitio no
                        muestra ningún costo.</template
                    >
                </p>
                <p
                    v-if="props.block.type === 'payment_options'"
                    class="rounded-lg bg-warning-soft p-3 text-xs text-warning-ink"
                >
                    Nunca prometas aprobaciones: las opciones de pago a plazos
                    siempre están sujetas a evaluación.
                </p>
                <p
                    v-if="dynamic.liveList"
                    class="text-xs text-muted-foreground"
                >
                    Los elementos se toman de lo publicado en el panel. Si no
                    hay ninguno, el bloque no se muestra en el sitio.
                </p>
                <SchemaFieldInput
                    v-for="field in dynamic.fields"
                    :key="field.key"
                    :model-value="p[field.key]"
                    :field="field"
                    :media="editor.library.value"
                    :errors="fieldErrors"
                    @update:model-value="(v) => set(field.key, v)"
                />
            </template>

            <div
                v-if="
                    [
                        'heading',
                        'text',
                        'badge',
                        'stat',
                        'list',
                        'icon',
                    ].includes(props.block.type)
                "
                class="grid gap-1.5"
            >
                <Label>Color del texto</Label>
                <ColorField
                    :model-value="
                        str(
                            'color',
                            props.block.type === 'icon' ? 'primary' : 'auto',
                        )
                    "
                    :tokens="INK_TOKENS"
                    label="Color del texto"
                    @update:model-value="(v) => set('color', v)"
                />
            </div>
        </InspectorGroup>

        <!-- Posición -->
        <InspectorGroup
            title="Posición y tamaño"
            description="En columnas (de 12) y filas de la retícula de escritorio."
        >
            <div class="grid grid-cols-2 gap-2">
                <div
                    v-for="key in ['x', 'y', 'w', 'h'] as const"
                    :key="key"
                    class="grid gap-1"
                >
                    <Label class="text-xs text-muted-foreground">{{
                        { x: 'Columna', y: 'Fila', w: 'Ancho', h: 'Alto' }[key]
                    }}</Label>
                    <NumberField
                        :model-value="
                            key === 'x' || key === 'y'
                                ? props.block[key] + 1
                                : props.block[key]
                        "
                        :min="1"
                        :max="
                            key === 'x'
                                ? COLUMNS
                                : key === 'w'
                                  ? COLUMNS - props.block.x
                                  : key === 'h'
                                    ? MAX_HEIGHT
                                    : MAX_ROW + 1
                        "
                        :disabled="props.block.locked || multiple"
                        @update:model-value="
                            (v: number | undefined) =>
                                v !== undefined &&
                                setGeometry(
                                    key,
                                    key === 'x' || key === 'y' ? v - 1 : v,
                                )
                        "
                    >
                        <NumberFieldContent>
                            <NumberFieldDecrement />
                            <NumberFieldInput class="h-9" />
                            <NumberFieldIncrement />
                        </NumberFieldContent>
                    </NumberField>
                </div>
            </div>
            <div class="flex items-center justify-between">
                <span class="text-xs text-muted-foreground">Capas</span>
                <div class="flex gap-1">
                    <Button
                        variant="outline"
                        size="icon"
                        class="size-8"
                        aria-label="Al frente"
                        title="Al frente (Ctrl+Shift+])"
                        @click="editor.arrange('front')"
                        ><ArrowUpToLine class="size-4"
                    /></Button>
                    <Button
                        variant="outline"
                        size="icon"
                        class="size-8"
                        aria-label="Adelante"
                        title="Adelante (Ctrl+])"
                        @click="editor.arrange('forward')"
                        ><ChevronUp class="size-4"
                    /></Button>
                    <Button
                        variant="outline"
                        size="icon"
                        class="size-8"
                        aria-label="Atrás"
                        title="Atrás (Ctrl+[)"
                        @click="editor.arrange('backward')"
                        ><ChevronDown class="size-4"
                    /></Button>
                    <Button
                        variant="outline"
                        size="icon"
                        class="size-8"
                        aria-label="Al fondo"
                        title="Al fondo (Ctrl+Shift+[)"
                        @click="editor.arrange('back')"
                        ><ArrowDownToLine class="size-4"
                    /></Button>
                </div>
            </div>
        </InspectorGroup>

        <!-- Estilo -->
        <InspectorGroup title="Estilo">
            <div class="flex items-center justify-between gap-2">
                <Label class="text-xs text-muted-foreground">Alineación</Label>
                <ToggleGroup
                    type="single"
                    variant="outline"
                    size="sm"
                    :model-value="props.block.style.align"
                    aria-label="Alineación"
                    @update:model-value="
                        (v) =>
                            v &&
                            editor.updateStyle(
                                props.sectionKey,
                                props.block.id,
                                { align: v as 'start' },
                            )
                    "
                >
                    <ToggleGroupItem value="start" aria-label="Izquierda"
                        ><AlignLeft class="size-4"
                    /></ToggleGroupItem>
                    <ToggleGroupItem value="center" aria-label="Centro"
                        ><AlignCenter class="size-4"
                    /></ToggleGroupItem>
                    <ToggleGroupItem value="end" aria-label="Derecha"
                        ><AlignRight class="size-4"
                    /></ToggleGroupItem>
                </ToggleGroup>
            </div>
            <div class="grid grid-cols-2 gap-2">
                <div class="grid gap-1">
                    <Label class="text-xs text-muted-foreground"
                        >Esquinas</Label
                    >
                    <Select
                        :model-value="props.block.style.radius"
                        @update:model-value="
                            (v) =>
                                editor.updateStyle(
                                    props.sectionKey,
                                    props.block.id,
                                    { radius: v as 'none' },
                                )
                        "
                    >
                        <SelectTrigger class="h-9 w-full" aria-label="Esquinas"
                            ><SelectValue
                        /></SelectTrigger>
                        <SelectContent>
                            <SelectItem value="none">Rectas</SelectItem>
                            <SelectItem value="md">Suaves</SelectItem>
                            <SelectItem value="xl">Redondeadas</SelectItem>
                            <SelectItem value="2xl">Muy redondeadas</SelectItem>
                            <SelectItem value="full">Píldora</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <div class="grid gap-1">
                    <Label class="text-xs text-muted-foreground">Sombra</Label>
                    <Select
                        :model-value="props.block.style.shadow"
                        @update:model-value="
                            (v) =>
                                editor.updateStyle(
                                    props.sectionKey,
                                    props.block.id,
                                    { shadow: v as 'none' },
                                )
                        "
                    >
                        <SelectTrigger class="h-9 w-full" aria-label="Sombra"
                            ><SelectValue
                        /></SelectTrigger>
                        <SelectContent>
                            <SelectItem value="none">Sin sombra</SelectItem>
                            <SelectItem value="sm">Sutil</SelectItem>
                            <SelectItem value="md">Media</SelectItem>
                            <SelectItem value="lg">Flotante</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
            </div>
            <div class="grid gap-2">
                <div class="flex items-center justify-between">
                    <Label class="text-xs text-muted-foreground">Opacidad</Label
                    ><span class="text-xs tabular-nums"
                        >{{ props.block.style.opacity }} %</span
                    >
                </div>
                <SbSlider
                    :model-value="props.block.style.opacity"
                    :min="5"
                    :max="100"
                    label="Opacidad"
                    @update:model-value="
                        (v) =>
                            editor.updateStyle(
                                props.sectionKey,
                                props.block.id,
                                { opacity: v },
                                false,
                            )
                    "
                    @commit="settle"
                />
                <div class="flex items-center justify-between">
                    <Label class="text-xs text-muted-foreground">Giro</Label
                    ><span class="text-xs tabular-nums"
                        >{{ props.block.style.rotate }}°</span
                    >
                </div>
                <SbSlider
                    :model-value="props.block.style.rotate"
                    :min="-180"
                    :max="180"
                    label="Giro"
                    @update:model-value="
                        (v) =>
                            editor.updateStyle(
                                props.sectionKey,
                                props.block.id,
                                { rotate: v },
                                false,
                            )
                    "
                    @commit="settle"
                />
            </div>
        </InspectorGroup>

        <!-- Movimiento -->
        <InspectorGroup title="Movimiento al aparecer">
            <template #action>
                <Button
                    variant="ghost"
                    size="sm"
                    class="h-7 px-2"
                    :disabled="props.block.motion.preset === 'none'"
                    @click="play"
                    ><Play class="size-3.5" /> Probar</Button
                >
            </template>
            <Select
                :model-value="props.block.motion.preset"
                @update:model-value="
                    (v) => (
                        editor.updateMotion(props.sectionKey, props.block.id, {
                            preset: v as 'none',
                        }),
                        play()
                    )
                "
            >
                <SelectTrigger class="h-9 w-full" aria-label="Movimiento"
                    ><SelectValue
                /></SelectTrigger>
                <SelectContent>
                    <SelectItem
                        v-for="m in MOTION_PRESETS"
                        :key="m.value"
                        :value="m.value"
                        >{{ m.label }}</SelectItem
                    >
                </SelectContent>
            </Select>
            <div class="grid gap-2">
                <div class="flex items-center justify-between">
                    <Label class="text-xs text-muted-foreground">Retraso</Label
                    ><span class="text-xs tabular-nums"
                        >{{ props.block.motion.delay }} ms</span
                    >
                </div>
                <SbSlider
                    :model-value="props.block.motion.delay"
                    :min="0"
                    :max="1200"
                    :step="40"
                    label="Retraso del movimiento"
                    :disabled="props.block.motion.preset === 'none'"
                    @update:model-value="
                        (v) =>
                            editor.updateMotion(
                                props.sectionKey,
                                props.block.id,
                                { delay: v },
                                false,
                            )
                    "
                    @commit="settle"
                />
            </div>
        </InspectorGroup>

        <!-- Visibilidad -->
        <InspectorGroup
            title="Visibilidad"
            description="Tableta y móvil se acomodan solos; aquí puedes ocultar este elemento en alguno."
        >
            <label class="flex items-center justify-between text-sm"
                >Ocultar en tableta<Switch
                    :model-value="props.block.hideOn.includes('tablet')"
                    @update:model-value="
                        (v: boolean) => toggleHide('tablet', v)
                    "
            /></label>
            <label class="flex items-center justify-between text-sm"
                >Ocultar en móvil<Switch
                    :model-value="props.block.hideOn.includes('mobile')"
                    @update:model-value="
                        (v: boolean) => toggleHide('mobile', v)
                    "
            /></label>
        </InspectorGroup>

        <MediaPicker
            v-model:open="pickerOpen"
            :type="pickerType"
            collection="sitio/editor"
            @select="onPick"
        />
    </div>
</template>
