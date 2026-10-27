<script setup lang="ts">
import { RotateCcw } from '@lucide/vue';
import { computed } from 'vue';
import { Switch } from '@/components/ui/switch';
import { TONES } from '@/studio/color';
import { effectiveStyle } from '@/studio/css';
import type { StyleDef } from '@/studio/schema';
import { schema } from '@/studio/schema';
import { locate } from '@/studio/tree';
import type { StudioNode, StyleValue } from '@/studio/types';
import ColorControl from './controls/ColorControl.vue';
import SegmentedControl from './controls/SegmentedControl.vue';
import SliderControl from './controls/SliderControl.vue';
import { useEditor } from './editorContext';

const props = defineProps<{ node: StudioNode; name: string; def: StyleDef }>();
const { store } = useEditor();

const bp = computed(() => store.state.bp);
const eff = computed(() => effectiveStyle(props.node, props.name, bp.value));
const own = computed(() => eff.value.source === bp.value);
const inherited = computed(() => eff.value.value !== undefined && !own.value);
const bpLabel = (b: string) => schema.breakpoints[b as keyof typeof schema.breakpoints]?.label ?? b;

const options = computed(() => Object.entries(props.def.options ?? {}).map(([value, label]) => ({ value, label })));

/** Fondo efectivo de la sección del nodo, para medir contraste. */
const background = computed(() => {
    const section = locate(store.state.doc, props.node.id)?.section;
    const ownBg = effectiveStyle(props.node, 'background', bp.value).value;
    if (typeof ownBg === 'string') return ownBg;
    if (!section) return 'ink';
    const sectionBg = effectiveStyle(section, 'sectionBackground', bp.value).value;
    if (typeof sectionBg === 'string') return sectionBg;

    return TONES[String(section.props.tone ?? 'ink')]?.bg ?? 'ink';
});
const isTextColor = computed(() => props.name === 'color' || props.name === 'textColor');
const largeText = computed(() => Number(effectiveStyle(props.node, 'fontSize', bp.value).value ?? 18) >= 24 || props.node.type === 'heading');

function set(value: StyleValue | undefined) {
    store.setStyle(props.node.id, props.name, value);
}
function reset() {
    store.setStyle(props.node.id, props.name, undefined, bp.value, false);
}
</script>

<template>
    <div class="flex flex-col gap-1.5">
        <div class="flex min-h-5 items-center justify-between gap-2">
            <span class="flex items-center gap-1.5 text-xs font-medium text-foreground/90">
                <span
                    v-if="own"
                    class="size-1.5 rounded-full bg-brand"
                    :title="`Definido en ${bpLabel(bp)}`"
                />
                {{ def.label }}
            </span>
            <span class="flex items-center gap-1">
                <span v-if="inherited" class="text-[10px] text-muted-foreground">
                    {{ eff.scaled ? 'auto · ' : '' }}de {{ bpLabel(eff.source!) }}
                </span>
                <button
                    v-if="own"
                    type="button"
                    class="grid size-6 place-items-center rounded text-muted-foreground hover:bg-muted hover:text-foreground"
                    :title="bp === 'base' ? 'Restablecer' : 'Volver a heredar'"
                    :aria-label="`Restablecer ${def.label}`"
                    @click="reset"
                >
                    <RotateCcw class="size-3" />
                </button>
            </span>
        </div>

        <SliderControl
            v-if="def.kind === 'number'"
            :model-value="eff.value === undefined ? undefined : Number(eff.value)"
            :min="def.min ?? 0"
            :max="def.max ?? 100"
            :step="def.step"
            :unit="def.unit"
            :label="def.label"
            :zero-label="def.zeroLabel"
            :inherited="inherited"
            :placeholder="def.zeroLabel ?? 'Auto'"
            @update:model-value="set"
        />

        <template v-else-if="def.kind === 'enum'">
            <SegmentedControl
                v-if="options.length <= 4"
                :model-value="eff.value === undefined ? undefined : String(eff.value)"
                :options="options"
                :label="def.label"
                :inherited="inherited"
                @update:model-value="set($event ?? undefined)"
            />
            <select
                v-else
                class="h-8 w-full rounded-md border border-input bg-background px-2 text-xs"
                :class="{ 'text-muted-foreground': inherited || eff.value === undefined }"
                :value="eff.value === undefined ? '' : String(eff.value)"
                :aria-label="def.label"
                @change="set(($event.target as HTMLSelectElement).value || undefined)"
            >
                <option value="">Automático</option>
                <option v-for="o in options" :key="o.value" :value="o.value">{{ o.label }}</option>
            </select>
        </template>

        <select
            v-else-if="def.kind === 'font'"
            class="h-8 w-full rounded-md border border-input bg-background px-2 text-xs"
            :class="{ 'text-muted-foreground': inherited || eff.value === undefined }"
            :value="eff.value === undefined ? '' : String(eff.value)"
            :aria-label="def.label"
            @change="set(($event.target as HTMLSelectElement).value || undefined)"
        >
            <option value="">Según el estilo del bloque</option>
            <option v-for="(font, key) in schema.fonts" :key="key" :value="key" :style="{ fontFamily: font.theme ? undefined : `'${key}'` }">
                {{ font.label }}
            </option>
        </select>

        <ColorControl
            v-else-if="def.kind === 'color'"
            :model-value="eff.value === undefined ? undefined : String(eff.value)"
            :label="def.label"
            :inherited="inherited"
            :against="isTextColor ? background : undefined"
            :large-text="largeText"
            @update:model-value="set"
        />

        <label v-else-if="def.kind === 'bool'" class="flex min-h-8 items-center justify-between text-xs text-muted-foreground">
            {{ eff.value ? 'Activado' : 'Desactivado' }}
            <Switch :model-value="Boolean(eff.value)" :aria-label="def.label" @update:model-value="set(Boolean($event))" />
        </label>
    </div>
</template>
