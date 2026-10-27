<script setup lang="ts">
import { computed, ref } from 'vue';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { cn } from '@/lib/utils';
import { PRESET_GROUPS, PRESETS } from '@/studio/presets';
import { schema, sectionDef, slotAccepts } from '@/studio/schema';
import { locate } from '@/studio/tree';
import PresetThumb from './controls/PresetThumb.vue';
import { useEditor } from './editorContext';
import { iconFor } from './icons';

/**
 * Agregar contenido:
 * - modo sección: galería de composiciones premium agrupadas;
 * - modo bloque: solo los bloques que acepta la zona elegida.
 */
export type AddTarget =
    | { mode: 'section'; index: number }
    | { mode: 'element'; sectionId: string; slot: string; index?: number };

const open = defineModel<boolean>('open', { default: false });
const props = defineProps<{ target: AddTarget | null }>();

const { store } = useEditor();
const group = ref('hero');

const presets = computed(() => PRESETS.filter((p) => p.group === group.value));

const elementChoices = computed(() => {
    const t = props.target;
    if (!t || t.mode !== 'element') return [];
    const section = locate(store.state.doc, t.sectionId)?.node;
    if (!section) return [];
    const accepts = slotAccepts(section.type, t.slot);

    return accepts.map((type) => ({ type, def: schema.elements[type] }));
});

const zoneLabel = computed(() => {
    const t = props.target;
    if (!t || t.mode !== 'element') return '';
    const section = locate(store.state.doc, t.sectionId)?.node;

    return section ? `${sectionDef(section.type)?.label} · ${sectionDef(section.type)?.slots[t.slot]?.label ?? t.slot}` : '';
});

function addPreset(id: string) {
    const preset = PRESETS.find((p) => p.id === id);
    if (!preset || props.target?.mode !== 'section') return;
    store.insertSection(preset.build(), props.target.index);
    open.value = false;
}

function addElement(type: string) {
    const t = props.target;
    if (!t || t.mode !== 'element') return;
    store.insertElement(type, t.sectionId, t.slot, t.index);
    open.value = false;
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="flex max-h-[88vh] flex-col gap-4 sm:max-w-5xl">
            <template v-if="target?.mode === 'section'">
                <DialogHeader>
                    <DialogTitle>Agregar sección</DialogTitle>
                    <DialogDescription>
                        Composiciones editoriales listas. Después puedes cambiar textos, fotos, colores y distribución.
                    </DialogDescription>
                </DialogHeader>
                <div class="flex min-h-0 flex-1 flex-col gap-4 md:flex-row">
                    <nav class="scrollbar-none flex shrink-0 gap-1 overflow-x-auto md:w-44 md:flex-col" aria-label="Categorías de composición">
                        <button
                            v-for="(label, key) in PRESET_GROUPS"
                            :key="key"
                            type="button"
                            :aria-current="group === key ? 'true' : undefined"
                            :class="cn('h-9 shrink-0 rounded-md px-3 text-left text-sm font-medium text-muted-foreground hover:bg-muted hover:text-foreground', group === key && 'bg-muted text-foreground')"
                            @click="group = String(key)"
                        >
                            {{ label }}
                        </button>
                    </nav>
                    <div class="grid min-h-0 flex-1 grid-cols-1 content-start gap-3 overflow-y-auto pr-1 sm:grid-cols-2 lg:grid-cols-3">
                        <button
                            v-for="preset in presets"
                            :key="preset.id"
                            type="button"
                            class="group flex flex-col gap-2 rounded-xl border p-2 text-left transition-all hover:-translate-y-0.5 hover:border-brand hover:shadow-lg focus-visible:ring-2 focus-visible:ring-ring"
                            @click="addPreset(preset.id)"
                        >
                            <span class="overflow-hidden rounded-lg"><PresetThumb :rects="preset.thumb" /></span>
                            <span class="px-1">
                                <span class="block text-sm font-semibold">{{ preset.label }}</span>
                                <span class="block text-xs text-muted-foreground">{{ preset.description }}</span>
                            </span>
                        </button>
                    </div>
                </div>
            </template>

            <template v-else-if="target?.mode === 'element'">
                <DialogHeader>
                    <DialogTitle>Agregar bloque</DialogTitle>
                    <DialogDescription>En: {{ zoneLabel }}</DialogDescription>
                </DialogHeader>
                <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                    <button
                        v-for="item in elementChoices"
                        :key="item.type"
                        type="button"
                        class="flex min-h-20 flex-col items-center justify-center gap-2 rounded-xl border p-3 text-sm font-medium transition-colors hover:border-brand hover:bg-brand/10"
                        @click="addElement(item.type)"
                    >
                        <component :is="iconFor(item.type)" class="size-5 text-brand" />
                        {{ item.def?.label ?? item.type }}
                    </button>
                </div>
            </template>
        </DialogContent>
    </Dialog>
</template>
