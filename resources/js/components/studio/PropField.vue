<script setup lang="ts">
import { ArrowDown, ArrowUp, ChevronRight, Plus, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import { Input } from '@/components/ui/input';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import type { PropDef } from '@/studio/schema';
import { defaultProps, propVisible } from '@/studio/schema';
import type { ResolvedMedia, StudioLink } from '@/studio/types';
import LinkControl from './controls/LinkControl.vue';
import MediaControl from './controls/MediaControl.vue';
import SegmentedControl from './controls/SegmentedControl.vue';
import SliderControl from './controls/SliderControl.vue';
import { useEditor } from './editorContext';

/** Campo de contenido genérico, construido a partir de schema.json. */
const props = defineProps<{
    def: PropDef;
    modelValue: unknown;
    fieldId: string;
    /** Para "página actual" en selectores de página. */
    allowCurrentPage?: boolean;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: unknown, coalesce?: boolean];
    'media-picked': [media: ResolvedMedia];
}>();

const { store, options } = useEditor();

const enumOptions = computed(() => Object.entries(props.def.options ?? {}).map(([value, label]) => ({ value, label })));
const media = computed(() => {
    const id = props.modelValue;

    return id ? (store.state.resolved.media[String(id)] ?? null) : null;
});

function onPicked(m: ResolvedMedia) {
    store.registerMedia(m);
    emit('media-picked', m);
}

// ------------------------------- Listas --------------------------------
const items = computed(() => (Array.isArray(props.modelValue) ? (props.modelValue as Record<string, unknown>[]) : []));
const openItem = ref<number | null>(null);

function updateItem(index: number, key: string, value: unknown, coalesce = false) {
    const next = items.value.map((item, i) => (i === index ? { ...item, [key]: value } : item));
    emit('update:modelValue', next, coalesce);
}
function addItem() {
    const next = [...items.value, defaultProps(props.def.item ?? {})];
    emit('update:modelValue', next);
    openItem.value = next.length - 1;
}
function removeItem(index: number) {
    emit('update:modelValue', items.value.filter((_, i) => i !== index));
    openItem.value = null;
}
function moveItem(index: number, dir: -1 | 1) {
    const target = index + dir;
    if (target < 0 || target >= items.value.length) return;
    const next = [...items.value];
    [next[index], next[target]] = [next[target], next[index]];
    emit('update:modelValue', next);
    openItem.value = target;
}
function itemTitle(item: Record<string, unknown>, i: number): string {
    const text = item.label || item.title || item.caption;
    if (text) return String(text);
    if (item.media && store.state.resolved.media[String(item.media)]) return store.state.resolved.media[String(item.media)].filename;

    return `${props.def.itemLabel ?? 'Elemento'} ${i + 1}`;
}
</script>

<template>
    <div class="flex flex-col gap-1.5">
        <div v-if="def.kind !== 'bool'" class="flex items-center justify-between">
            <label :for="fieldId" class="text-xs font-medium text-foreground/90">{{ def.label }}</label>
            <span v-if="(def.kind === 'string' || def.kind === 'text') && def.max" class="text-[10px] text-muted-foreground tabular-nums">
                {{ String(modelValue ?? '').length }}/{{ def.max }}
            </span>
        </div>

        <Input
            v-if="def.kind === 'string'"
            :id="fieldId"
            :model-value="String(modelValue ?? '')"
            :maxlength="def.max"
            class="h-9 text-sm"
            @update:model-value="emit('update:modelValue', $event, true)"
        />

        <Textarea
            v-else-if="def.kind === 'text'"
            :id="fieldId"
            :model-value="String(modelValue ?? '')"
            :maxlength="def.max"
            rows="3"
            class="min-h-[72px] resize-y text-sm"
            @update:model-value="emit('update:modelValue', $event, true)"
        />

        <template v-else-if="def.kind === 'enum'">
            <SegmentedControl
                v-if="enumOptions.length <= 4"
                :model-value="String(modelValue)"
                :options="enumOptions"
                :label="def.label"
                @update:model-value="emit('update:modelValue', $event)"
            />
            <select
                v-else
                :id="fieldId"
                class="h-9 w-full rounded-md border border-input bg-background px-2 text-sm"
                :value="String(modelValue)"
                @change="emit('update:modelValue', ($event.target as HTMLSelectElement).value)"
            >
                <option v-for="o in enumOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
            </select>
        </template>

        <label v-else-if="def.kind === 'bool'" :for="fieldId" class="flex min-h-9 items-center justify-between gap-3 text-xs font-medium">
            {{ def.label }}
            <Switch :id="fieldId" :model-value="Boolean(modelValue)" @update:model-value="emit('update:modelValue', $event)" />
        </label>

        <SliderControl
            v-else-if="def.kind === 'int' || def.kind === 'number'"
            :model-value="Number(modelValue ?? def.default ?? def.min)"
            :min="def.min ?? 0"
            :max="def.max ?? 100"
            :step="def.step ?? 1"
            :label="def.label"
            @update:model-value="emit('update:modelValue', $event, true)"
        />

        <MediaControl
            v-else-if="def.kind === 'media'"
            :model-value="(modelValue as number | null)"
            :media-type="def.mediaType"
            :label="def.label"
            :media="media"
            @update:model-value="emit('update:modelValue', $event)"
            @picked="onPicked"
        />

        <select
            v-else-if="def.kind === 'category'"
            :id="fieldId"
            class="h-9 w-full rounded-md border border-input bg-background px-2 text-sm"
            :value="String(modelValue ?? '')"
            @change="emit('update:modelValue', ($event.target as HTMLSelectElement).value)"
        >
            <option value="">Elige una categoría…</option>
            <option v-for="o in options.categories" :key="o.value" :value="o.value">{{ o.label }}</option>
        </select>

        <select
            v-else-if="def.kind === 'page'"
            :id="fieldId"
            class="h-9 w-full rounded-md border border-input bg-background px-2 text-sm"
            :value="String(modelValue ?? '')"
            @change="emit('update:modelValue', ($event.target as HTMLSelectElement).value)"
        >
            <option v-if="allowCurrentPage" value="">
                Esta página{{ options.currentCmsPage ? '' : ' (sin página del CMS)' }}
            </option>
            <option v-for="o in options.cmsPages" :key="o.value" :value="o.value">{{ o.label }}</option>
        </select>

        <LinkControl
            v-else-if="def.kind === 'link'"
            :model-value="(modelValue as StudioLink | null)"
            :label="def.label"
            :nullable="def.nullable"
            :links="options.links"
            :social="options.social"
            @update:model-value="emit('update:modelValue', $event)"
        />

        <div v-else-if="def.kind === 'list'" class="flex flex-col gap-1.5">
            <div v-for="(item, i) in items" :key="i" class="overflow-hidden rounded-lg border bg-muted/20">
                <div class="flex items-center gap-1 pr-1">
                    <button
                        type="button"
                        class="flex min-h-10 min-w-0 flex-1 items-center gap-2 px-2.5 text-left text-xs font-medium"
                        :aria-expanded="openItem === i"
                        @click="openItem = openItem === i ? null : i"
                    >
                        <ChevronRight class="size-3.5 shrink-0 transition-transform" :class="{ 'rotate-90': openItem === i }" />
                        <span class="truncate">{{ itemTitle(item, i) }}</span>
                    </button>
                    <button type="button" class="grid size-8 place-items-center rounded text-muted-foreground hover:bg-muted" :disabled="i === 0" aria-label="Subir" @click="moveItem(i, -1)"><ArrowUp class="size-3.5" /></button>
                    <button type="button" class="grid size-8 place-items-center rounded text-muted-foreground hover:bg-muted" :disabled="i === items.length - 1" aria-label="Bajar" @click="moveItem(i, 1)"><ArrowDown class="size-3.5" /></button>
                    <button type="button" class="grid size-8 place-items-center rounded text-muted-foreground hover:bg-destructive/15 hover:text-destructive" aria-label="Eliminar" @click="removeItem(i)"><Trash2 class="size-3.5" /></button>
                </div>
                <div v-if="openItem === i" class="flex flex-col gap-3 border-t bg-background/40 p-2.5">
                    <template v-for="(itemDef, key) in def.item" :key="key">
                        <PropField
                            v-if="propVisible(itemDef, item)"
                            :def="itemDef"
                            :model-value="item[key]"
                            :field-id="`${fieldId}-${i}-${key}`"
                            @update:model-value="(v: unknown, c?: boolean) => updateItem(i, String(key), v, c)"
                        />
                    </template>
                </div>
            </div>
            <button
                v-if="items.length < (def.max ?? 99)"
                type="button"
                class="flex min-h-9 items-center justify-center gap-1.5 rounded-lg border border-dashed text-xs font-medium text-muted-foreground hover:border-brand hover:text-foreground"
                @click="addItem"
            >
                <Plus class="size-3.5" /> Agregar {{ (def.itemLabel ?? 'elemento').toLowerCase() }}
            </button>
        </div>
    </div>
</template>
