<script setup lang="ts">
import { Copy, Crop, Eye, EyeOff, Laptop, Monitor, MousePointerClick, Play, RotateCcw, Smartphone, Tablet, Trash2 } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { Switch } from '@/components/ui/switch';
import { cn } from '@/lib/utils';
import { TONES } from '@/studio/color';
import { BP_ORDER, effectiveStyle } from '@/studio/css';
import type { PropDef } from '@/studio/schema';
import { nodeDef, propDefsFor, propVisible, schema, styleDefsFor } from '@/studio/schema';
import { suggestAspect } from '@/studio/media';
import { locate, nodeLabel } from '@/studio/tree';
import type { Breakpoint, MotionConfig, ResolvedMedia } from '@/studio/types';
import FocalPointControl from './controls/FocalPointControl.vue';
import LayoutPicker from './controls/LayoutPicker.vue';
import SegmentedControl from './controls/SegmentedControl.vue';
import SliderControl from './controls/SliderControl.vue';
import { useEditor } from './editorContext';
import PropField from './PropField.vue';
import StyleField from './StyleField.vue';

const emit = defineEmits<{ replay: [id: string] }>();
const { store, options } = useEditor();

type Tab = 'content' | 'layout' | 'style' | 'motion' | 'responsive' | 'advanced';
const tab = ref<Tab>('content');

const loc = computed(() => (store.state.selectedId ? locate(store.state.doc, store.state.selectedId) : null));
const node = computed(() => loc.value?.node ?? null);
const isSection = computed(() => !!node.value && loc.value?.slot === null);
const def = computed(() => (node.value ? nodeDef(node.value.type) : undefined));

const SHARED = new Set(Object.keys(schema.sharedSectionProps));
const GROUP_LABELS: Record<string, string> = {
    typography: 'Tipografía',
    box: 'Caja y espaciado',
    position: 'Posición',
    media: 'Imagen y encuadre',
    section: 'Distribución',
    grid: 'Cuadrícula',
    spacer: 'Espacio',
    visibility: 'Visibilidad',
};

const allProps = computed(() => (node.value ? propDefsFor(node.value.type) : {}));
const contentProps = computed(() =>
    Object.entries(allProps.value).filter(
        ([key, d]) => d.kind !== 'layout' && !SHARED.has(key) && d.tab !== 'advanced' && propVisible(d, node.value!.props),
    ),
);
const advancedProps = computed(() =>
    Object.entries(allProps.value).filter(([, d]) => d.tab === 'advanced' && propVisible(d, node.value!.props)),
);

function styleGroups(tabName: 'style' | 'layout') {
    if (!node.value) return [];
    const defs = styleDefsFor(node.value.type);
    const groups: Record<string, [string, (typeof defs)[string]][]> = {};
    for (const [key, d] of Object.entries(defs)) {
        if (schema.styleTabs[d.group] !== tabName) continue;
        (groups[d.group] ??= []).push([key, d]);
    }

    return Object.entries(groups);
}

const styleTabGroups = computed(() => styleGroups('style'));
const layoutTabGroups = computed(() => styleGroups('layout'));

const tabs = computed(() => {
    const list: { id: Tab; label: string }[] = [];
    if (!node.value) return list;
    if (contentProps.value.length) list.push({ id: 'content', label: 'Contenido' });
    list.push({ id: 'layout', label: 'Diseño' });
    if (styleTabGroups.value.length) list.push({ id: 'style', label: 'Estilo' });
    list.push({ id: 'motion', label: 'Movimiento' });
    list.push({ id: 'responsive', label: 'Responsive' });
    list.push({ id: 'advanced', label: 'Avanzado' });

    return list;
});

watch(
    () => node.value?.id,
    () => {
        if (!tabs.value.some((t) => t.id === tab.value)) tab.value = tabs.value[0]?.id ?? 'content';
    },
);

function setProp(key: string, value: unknown, coalesce = false) {
    if (node.value) store.setProp(node.value.id, key, value, coalesce);
}

/** Automatización: al elegir una foto, proponer su proporción real. */
function onMediaPicked(key: string, media: ResolvedMedia) {
    if (node.value && key === 'media') suggestAspect(store, node.value.id, media);
}

// ------------------------------ Imagen ----------------------------------
const imageMedia = computed(() => {
    if (node.value?.type !== 'image') return null;
    const id = node.value.props.bind === 'cover' ? null : node.value.props.media;

    return id ? (store.state.resolved.media[String(id)] ?? null) : null;
});
const focal = computed(() => ({
    x: Number(effectiveStyle(node.value!, 'focalX', store.state.bp).value ?? 50),
    y: Number(effectiveStyle(node.value!, 'focalY', store.state.bp).value ?? 50),
}));
function setFocal(x: number, y: number) {
    if (!node.value) return;
    store.setStyle(node.value.id, 'focalX', x);
    store.setStyle(node.value.id, 'focalY', y);
}

// ------------------------------ Movimiento ------------------------------
const motion = computed<MotionConfig>(
    () =>
        node.value?.motion ?? {
            preset: 'none',
            duration: schema.motion.fields.duration.default,
            delay: 0,
            distance: schema.motion.fields.distance.default,
            trigger: 'scroll',
            mobile: 'reduce',
            once: true,
        },
);
function setMotion(patch: Partial<MotionConfig>, coalesce = true) {
    if (!node.value) return;
    const next = { ...motion.value, ...patch };
    store.setMotion(node.value.id, next.preset === 'none' ? null : next, coalesce);
}

// ------------------------------ Responsive ------------------------------
const DEVICE_ICONS: Record<Breakpoint, typeof Monitor> = { base: Monitor, laptop: Laptop, tablet: Tablet, mobile: Smartphone };
const overridesHere = computed(() => {
    if (!node.value) return [];
    const map = node.value.style?.[store.state.bp] ?? {};

    return Object.keys(map).map((key) => ({ key, label: schema.styleProps[key]?.label ?? key, value: map[key] }));
});
function hiddenAt(bp: Breakpoint): boolean {
    return Boolean(node.value && effectiveStyle(node.value, 'hide', bp).value);
}
function toggleHideAt(bp: Breakpoint) {
    if (!node.value) return;
    store.setStyle(node.value.id, 'hide', !hiddenAt(bp), bp, false);
}

const toneOptions = Object.entries(schema.sharedSectionProps.tone.options ?? {});
const spacingOptions = Object.entries(schema.sharedSectionProps.spacing.options ?? {});
const widthOptions = Object.entries(schema.sharedSectionProps.width.options ?? {}).map(([value, label]) => ({ value, label }));
const sharedDef = (key: string) => schema.sharedSectionProps[key] as PropDef;
</script>

<template>
    <aside class="flex h-full min-h-0 flex-col" aria-label="Propiedades">
        <template v-if="node && def">
            <!-- Encabezado -->
            <div class="flex items-start gap-2 border-b px-4 py-3">
                <div class="min-w-0 flex-1">
                    <p class="text-[10px] font-semibold tracking-[0.14em] text-brand uppercase">
                        {{ isSection ? 'Sección' : 'Bloque' }} · {{ def.label }}
                    </p>
                    <p class="truncate text-sm font-medium">{{ nodeLabel(node) }}</p>
                    <button
                        v-if="!isSection && loc"
                        type="button"
                        class="mt-0.5 text-[11px] text-muted-foreground hover:text-foreground hover:underline"
                        @click="store.select(loc.section.id)"
                    >
                        En: {{ nodeDef(loc.section.type)?.label }} ↑
                    </button>
                </div>
                <div class="flex gap-0.5">
                    <button type="button" class="grid size-8 place-items-center rounded-md text-muted-foreground hover:bg-muted hover:text-foreground" :title="node.hidden ? 'Mostrar' : 'Ocultar'" @click="store.toggleHidden(node.id)">
                        <EyeOff v-if="!node.hidden" class="size-4" /><Eye v-else class="size-4" />
                    </button>
                    <button type="button" class="grid size-8 place-items-center rounded-md text-muted-foreground hover:bg-muted hover:text-foreground" title="Duplicar (Ctrl+D)" @click="store.duplicate(node.id)">
                        <Copy class="size-4" />
                    </button>
                    <button type="button" class="grid size-8 place-items-center rounded-md text-muted-foreground hover:bg-destructive/15 hover:text-destructive" title="Eliminar (Supr)" @click="store.remove(node.id)">
                        <Trash2 class="size-4" />
                    </button>
                </div>
            </div>

            <!-- Pestañas -->
            <div class="scrollbar-none flex gap-0.5 overflow-x-auto border-b px-2" role="tablist">
                <button
                    v-for="t in tabs"
                    :key="t.id"
                    type="button"
                    role="tab"
                    :aria-selected="tab === t.id"
                    :class="cn('relative h-10 shrink-0 px-2.5 text-xs font-medium text-muted-foreground transition-colors hover:text-foreground', tab === t.id && 'text-foreground after:absolute after:inset-x-2 after:bottom-0 after:h-0.5 after:rounded-full after:bg-brand')"
                    @click="tab = t.id"
                >
                    {{ t.label }}
                </button>
            </div>

            <div class="min-h-0 flex-1 overflow-y-auto px-4 py-4" role="tabpanel">
                <!-- CONTENIDO -->
                <div v-if="tab === 'content'" class="flex flex-col gap-4">
                    <p
                        v-if="Object.values(allProps).some((d) => d.inline)"
                        class="flex items-center gap-2 rounded-md bg-muted/60 px-3 py-2 text-[11px] text-muted-foreground"
                    >
                        <MousePointerClick class="size-3.5 shrink-0" />
                        Doble clic sobre el texto en el lienzo para editarlo ahí mismo.
                    </p>
                    <PropField
                        v-for="[key, d] in contentProps"
                        :key="`${node.id}-${key}`"
                        :def="d"
                        :model-value="node.props[key]"
                        :field-id="`f-${node.id}-${key}`"
                        :allow-current-page="key === 'bindPage' || (node.type === 'page-body' && key === 'page')"
                        @update:model-value="(v: unknown, c?: boolean) => setProp(key, v, c)"
                        @media-picked="(m: ResolvedMedia) => onMediaPicked(key, m)"
                    />
                </div>

                <!-- DISEÑO -->
                <div v-else-if="tab === 'layout'" class="flex flex-col gap-5">
                    <template v-if="isSection">
                        <section v-if="Object.keys(allProps).includes('layout')" class="flex flex-col gap-2">
                            <h3 class="text-[11px] font-semibold tracking-[0.12em] text-muted-foreground uppercase">Composición</h3>
                            <LayoutPicker :section-type="node.type" :model-value="String(node.props.layout)" @update:model-value="setProp('layout', $event)" />
                        </section>

                        <section class="flex flex-col gap-2">
                            <h3 class="text-[11px] font-semibold tracking-[0.12em] text-muted-foreground uppercase">Tono de fondo</h3>
                            <div class="grid grid-cols-4 gap-2" role="radiogroup" aria-label="Tono">
                                <button
                                    v-for="[value, label] in toneOptions"
                                    :key="value"
                                    type="button"
                                    role="radio"
                                    :aria-checked="node.props.tone === value"
                                    class="flex flex-col items-center gap-1 rounded-lg border p-1.5 text-[10px]"
                                    :class="node.props.tone === value ? 'border-brand ring-1 ring-brand' : 'border-border'"
                                    @click="setProp('tone', value)"
                                >
                                    <span class="grid h-8 w-full place-items-center rounded font-display text-sm" :style="{ background: `var(--color-${TONES[value].bg})`, color: `var(--color-${TONES[value].fg})` }">Aa</span>
                                    {{ label }}
                                </button>
                            </div>
                        </section>

                        <section class="flex flex-col gap-2">
                            <h3 class="text-[11px] font-semibold tracking-[0.12em] text-muted-foreground uppercase">Espaciado vertical</h3>
                            <div class="flex flex-wrap gap-1.5" role="radiogroup" aria-label="Espaciado">
                                <button
                                    v-for="[value, label] in spacingOptions"
                                    :key="value"
                                    type="button"
                                    role="radio"
                                    :aria-checked="node.props.spacing === value"
                                    class="h-8 rounded-full border px-3 text-[11px] font-medium"
                                    :class="node.props.spacing === value ? 'border-brand bg-brand/15 text-foreground' : 'border-border text-muted-foreground hover:text-foreground'"
                                    @click="setProp('spacing', value)"
                                >
                                    {{ label }}
                                </button>
                            </div>
                        </section>

                        <section class="flex flex-col gap-2">
                            <h3 class="text-[11px] font-semibold tracking-[0.12em] text-muted-foreground uppercase">Ancho</h3>
                            <SegmentedControl :model-value="String(node.props.width)" :options="widthOptions" label="Ancho" @update:model-value="setProp('width', $event)" />
                            <PropField :def="sharedDef('divider')" :model-value="node.props.divider" :field-id="`f-${node.id}-divider`" @update:model-value="setProp('divider', $event)" />
                        </section>
                    </template>

                    <section v-for="[group, fields] in layoutTabGroups" :key="group" class="flex flex-col gap-3">
                        <h3 class="text-[11px] font-semibold tracking-[0.12em] text-muted-foreground uppercase">{{ GROUP_LABELS[group] ?? group }}</h3>
                        <StyleField v-for="[key, d] in fields" :key="`${node.id}-${key}`" :node="node" :name="key" :def="d" />
                    </section>
                </div>

                <!-- ESTILO -->
                <div v-else-if="tab === 'style'" class="flex flex-col gap-5">
                    <section v-if="node.type === 'image' && imageMedia?.url" class="flex flex-col gap-2">
                        <h3 class="flex items-center gap-1.5 text-[11px] font-semibold tracking-[0.12em] text-muted-foreground uppercase"><Crop class="size-3.5" /> Encuadre</h3>
                        <FocalPointControl :url="imageMedia.url" :x="focal.x" :y="focal.y" @change="setFocal" />
                        <p class="text-[11px] text-muted-foreground">Toca el punto que siempre debe verse. Combínalo con Proporción y Zoom para recortar sin perder el original.</p>
                    </section>
                    <section v-for="[group, fields] in styleTabGroups" :key="group" class="flex flex-col gap-3">
                        <h3 class="text-[11px] font-semibold tracking-[0.12em] text-muted-foreground uppercase">{{ GROUP_LABELS[group] ?? group }}</h3>
                        <StyleField v-for="[key, d] in fields" :key="`${node.id}-${key}`" :node="node" :name="key" :def="d" />
                    </section>
                </div>

                <!-- MOVIMIENTO -->
                <div v-else-if="tab === 'motion'" class="flex flex-col gap-4">
                    <div class="grid grid-cols-2 gap-1.5" role="radiogroup" aria-label="Animación">
                        <button
                            v-for="(label, key) in schema.motion.presets"
                            :key="key"
                            type="button"
                            role="radio"
                            :aria-checked="motion.preset === key"
                            class="min-h-9 rounded-md border px-2 text-left text-[11px] font-medium"
                            :class="motion.preset === key ? 'border-brand bg-brand/15' : 'border-border text-muted-foreground hover:text-foreground'"
                            @click="setMotion({ preset: String(key) }, false)"
                        >
                            {{ label }}
                        </button>
                    </div>
                    <template v-if="motion.preset !== 'none'">
                        <div class="flex flex-col gap-1.5">
                            <span class="text-xs font-medium">Duración</span>
                            <SliderControl :model-value="motion.duration" v-bind="schema.motion.fields.duration" unit="ms" label="Duración" @update:model-value="setMotion({ duration: $event })" />
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <span class="text-xs font-medium">Retraso</span>
                            <SliderControl :model-value="motion.delay" v-bind="schema.motion.fields.delay" unit="ms" label="Retraso" @update:model-value="setMotion({ delay: $event })" />
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <span class="text-xs font-medium">Intensidad (distancia)</span>
                            <SliderControl :model-value="motion.distance" v-bind="schema.motion.fields.distance" unit="px" label="Intensidad" @update:model-value="setMotion({ distance: $event })" />
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <span class="text-xs font-medium">Activación</span>
                            <SegmentedControl :model-value="motion.trigger" :options="Object.entries(schema.motion.triggers).map(([value, label]) => ({ value, label }))" label="Activación" @update:model-value="setMotion({ trigger: $event as MotionConfig['trigger'] }, false)" />
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <span class="text-xs font-medium">En móvil</span>
                            <SegmentedControl :model-value="motion.mobile" :options="Object.entries(schema.motion.mobile).map(([value, label]) => ({ value, label }))" label="En móvil" @update:model-value="setMotion({ mobile: $event as MotionConfig['mobile'] }, false)" />
                        </div>
                        <label class="flex items-center justify-between text-xs font-medium">
                            Repetir al volver a entrar en pantalla
                            <Switch :model-value="!motion.once" @update:model-value="setMotion({ once: !$event }, false)" />
                        </label>
                        <button
                            type="button"
                            class="inline-flex h-9 items-center justify-center gap-2 rounded-md border text-xs font-semibold hover:border-brand"
                            @click="emit('replay', node.id)"
                        >
                            <Play class="size-3.5" /> Previsualizar animación
                        </button>
                        <p class="text-[11px] text-muted-foreground">
                            Si el visitante prefiere menos movimiento, las animaciones se desactivan automáticamente.
                        </p>
                    </template>
                </div>

                <!-- RESPONSIVE -->
                <div v-else-if="tab === 'responsive'" class="flex flex-col gap-5">
                    <section class="flex flex-col gap-2">
                        <h3 class="text-[11px] font-semibold tracking-[0.12em] text-muted-foreground uppercase">Mostrar en</h3>
                        <div class="grid grid-cols-4 gap-1.5">
                            <button
                                v-for="bp in BP_ORDER"
                                :key="bp"
                                type="button"
                                :aria-pressed="!hiddenAt(bp)"
                                class="flex flex-col items-center gap-1 rounded-lg border py-2 text-[10px] font-medium"
                                :class="hiddenAt(bp) ? 'border-dashed text-muted-foreground line-through' : 'border-brand/60 bg-brand/10'"
                                @click="toggleHideAt(bp)"
                            >
                                <component :is="DEVICE_ICONS[bp]" class="size-4" />
                                {{ schema.breakpoints[bp].label }}
                            </button>
                        </div>
                    </section>

                    <section class="flex flex-col gap-2">
                        <h3 class="text-[11px] font-semibold tracking-[0.12em] text-muted-foreground uppercase">
                            Ajustes propios de {{ schema.breakpoints[store.state.bp].label }}
                        </h3>
                        <p class="text-[11px] text-muted-foreground">
                            Lo que cambies con este dispositivo activo solo afecta a {{ schema.breakpoints[store.state.bp].label.toLowerCase() }}{{ store.state.bp === 'base' ? ' y lo heredan los demás' : ' y a los más pequeños' }}. Los tamaños heredados se reducen automáticamente.
                        </p>
                        <ul v-if="overridesHere.length" class="flex flex-col divide-y rounded-lg border">
                            <li v-for="o in overridesHere" :key="o.key" class="flex items-center justify-between gap-2 px-3 py-2 text-xs">
                                <span>{{ o.label }}</span>
                                <span class="flex items-center gap-2">
                                    <span class="font-mono text-[11px] text-muted-foreground">{{ o.value }}</span>
                                    <button type="button" class="grid size-6 place-items-center rounded hover:bg-muted" :aria-label="`Quitar ${o.label}`" @click="store.setStyle(node.id, o.key, undefined, store.state.bp, false)">
                                        <RotateCcw class="size-3" />
                                    </button>
                                </span>
                            </li>
                        </ul>
                        <p v-else class="rounded-lg border border-dashed px-3 py-3 text-center text-[11px] text-muted-foreground">Sin ajustes propios: hereda todo.</p>
                        <button
                            v-if="overridesHere.length"
                            type="button"
                            class="h-8 rounded-md text-xs text-muted-foreground hover:bg-muted hover:text-foreground"
                            @click="store.resetStyles(node.id, store.state.bp)"
                        >
                            Restablecer {{ schema.breakpoints[store.state.bp].label.toLowerCase() }}
                        </button>
                    </section>
                </div>

                <!-- AVANZADO -->
                <div v-else-if="tab === 'advanced'" class="flex flex-col gap-4">
                    <PropField
                        v-if="isSection"
                        :def="sharedDef('anchor')"
                        :model-value="node.props.anchor"
                        :field-id="`f-${node.id}-anchor`"
                        @update:model-value="(v: unknown, c?: boolean) => setProp('anchor', v, c)"
                    />
                    <PropField
                        v-for="[key, d] in advancedProps"
                        :key="key"
                        :def="d"
                        :model-value="node.props[key]"
                        :field-id="`f-${node.id}-${key}`"
                        @update:model-value="(v: unknown) => setProp(key, v)"
                    />
                    <dl class="grid grid-cols-[auto_1fr] gap-x-3 gap-y-1 rounded-lg border p-3 text-[11px]">
                        <dt class="text-muted-foreground">Tipo</dt><dd class="font-mono">{{ node.type }} v{{ node.v }}</dd>
                        <dt class="text-muted-foreground">ID</dt><dd class="font-mono">{{ node.id }}</dd>
                    </dl>
                    <button type="button" class="h-9 rounded-md border text-xs font-medium hover:border-destructive hover:text-destructive" @click="store.resetStyles(node.id)">
                        Restablecer todos los estilos
                    </button>
                </div>
            </div>
        </template>

        <div v-else class="flex flex-1 flex-col gap-5 overflow-y-auto px-5 py-6">
            <div>
                <p class="text-[10px] font-semibold tracking-[0.14em] text-brand uppercase">Propiedades</p>
                <p class="mt-1 text-sm font-medium">Selecciona un elemento</p>
                <p class="mt-1 text-xs text-muted-foreground">
                    Haz clic en cualquier título, foto, video o sección del lienzo (o en la estructura) para ver solo los ajustes que le corresponden.
                </p>
            </div>
            <div class="rounded-lg border p-3">
                <p class="mb-2 text-xs font-semibold">Atajos</p>
                <dl class="grid grid-cols-[1fr_auto] gap-y-1.5 text-[11px] text-muted-foreground">
                    <dt>Editar texto</dt><dd>Doble clic</dd>
                    <dt>Seleccionar sección</dt><dd>Alt + clic</dd>
                    <dt>Deshacer / Rehacer</dt><dd>Ctrl Z / Ctrl ⇧ Z</dd>
                    <dt>Duplicar</dt><dd>Ctrl D</dd>
                    <dt>Mover</dt><dd>Alt ↑ / Alt ↓</dd>
                    <dt>Eliminar</dt><dd>Supr</dd>
                    <dt>Guardar ahora</dt><dd>Ctrl S</dd>
                </dl>
            </div>
            <p class="text-[11px] text-muted-foreground">
                Los cambios se guardan como borrador automáticamente. El sitio público solo cambia al pulsar <strong>Publicar</strong>.
            </p>
        </div>
    </aside>
</template>
