<script setup lang="ts">
import { ArrowDown, ArrowUp, ChevronRight, Eye, EyeOff, GripVertical, Plus, TriangleAlert } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { VueDraggable } from 'vue-draggable-plus';
import { cn } from '@/lib/utils';
import { activeSlots, nodeDef, schema, sectionDef, slotAccepts } from '@/studio/schema';
import { locate, nodeLabel } from '@/studio/tree';
import { useEditor } from './editorContext';
import { iconFor } from './icons';

/**
 * Árbol de la página: secciones y bloques. Reordenar con arrastrar y soltar
 * (también entre columnas compatibles), con botones o con Alt+↑/↓.
 */
const emit = defineEmits<{ 'add-section': [index: number] }>();
const { store, openAdd } = useEditor();

const tab = ref<'tree' | 'add'>('tree');
const expanded = ref(new Set<string>());

// Al seleccionar algo, abrir su sección en el árbol.
watch(
    () => store.state.selectedId,
    (id) => {
        if (!id) return;
        const loc = locate(store.state.doc, id);
        if (loc && !expanded.value.has(loc.section.id)) {
            expanded.value = new Set([...expanded.value, loc.section.id]);
        }
    },
);

function toggle(id: string) {
    const next = new Set(expanded.value);
    if (next.has(id)) next.delete(id);
    else next.add(id);
    expanded.value = next;
}

function slotGroup(sectionType: string, slot: string) {
    const accepts = slotAccepts(sectionType, slot);

    return {
        name: 'studio-elements',
        pull: true,
        put: (_to: unknown, _from: unknown, dragEl: HTMLElement) => accepts.includes(dragEl.dataset.type ?? ''),
    };
}

const selectedLoc = computed(() => (store.state.selectedId ? locate(store.state.doc, store.state.selectedId) : null));

/** Destino de "Agregar bloque" según la selección actual. */
const addTarget = computed(() => {
    const loc = selectedLoc.value;
    if (!loc) return null;
    if (loc.slot) return { sectionId: loc.section.id, slot: loc.slot, index: loc.index + 1 };
    const slots = activeSlots(loc.section);
    const slot = slots[0];

    return slot ? { sectionId: loc.section.id, slot } : null;
});

const elementTypes = computed(() => {
    const target = addTarget.value;
    const section = target ? locate(store.state.doc, target.sectionId)?.section : null;
    const accepted = section && target ? slotAccepts(section.type, target.slot) : [];

    return Object.entries(schema.elements).map(([type, def]) => ({ type, def, allowed: accepted.includes(type) }));
});

function insert(type: string) {
    const t = addTarget.value;
    if (!t) return;
    store.insertElement(type, t.sectionId, t.slot, t.index);
}

function sectionIndex(): number {
    const loc = selectedLoc.value;
    if (!loc) return store.state.doc.sections.length;

    return store.state.doc.sections.findIndex((s) => s.id === loc.section.id) + 1;
}
</script>

<template>
    <aside class="flex h-full min-h-0 flex-col" aria-label="Estructura de la página">
        <div class="flex gap-0.5 border-b px-2" role="tablist">
            <button
                v-for="t in [{ id: 'tree', label: 'Estructura' }, { id: 'add', label: 'Agregar' }] as const"
                :key="t.id"
                type="button"
                role="tab"
                :aria-selected="tab === t.id"
                :class="cn('relative h-10 px-3 text-xs font-medium text-muted-foreground hover:text-foreground', tab === t.id && 'text-foreground after:absolute after:inset-x-2 after:bottom-0 after:h-0.5 after:rounded-full after:bg-brand')"
                @click="tab = t.id"
            >
                {{ t.label }}
            </button>
        </div>

        <!-- ÁRBOL -->
        <div v-if="tab === 'tree'" class="min-h-0 flex-1 overflow-y-auto p-2">
            <VueDraggable
                v-model="store.state.doc.sections"
                handle=".drag-section"
                :animation="160"
                ghost-class="studio-ghost"
                class="flex flex-col gap-1"
                @start="store.beginExternal()"
                @end="store.endExternal()"
            >
                <div v-for="(section, si) in store.state.doc.sections" :key="section.id" class="rounded-lg" :class="{ 'bg-muted/40': expanded.has(section.id) }">
                    <div
                        class="group flex min-h-10 items-center gap-1 rounded-lg pr-1 transition-colors"
                        :class="store.state.selectedId === section.id ? 'bg-brand/15 ring-1 ring-brand/60' : 'hover:bg-muted'"
                    >
                        <span class="drag-section grid h-10 w-6 shrink-0 cursor-grab place-items-center text-muted-foreground active:cursor-grabbing" aria-hidden="true">
                            <GripVertical class="size-3.5" />
                        </span>
                        <button type="button" class="grid size-6 place-items-center rounded text-muted-foreground" :aria-label="expanded.has(section.id) ? 'Contraer' : 'Expandir'" :aria-expanded="expanded.has(section.id)" @click="toggle(section.id)">
                            <ChevronRight class="size-3.5 transition-transform" :class="{ 'rotate-90': expanded.has(section.id) }" />
                        </button>
                        <button
                            type="button"
                            class="flex min-w-0 flex-1 items-center gap-2 py-2 text-left text-xs font-medium"
                            :class="{ 'opacity-50': section.hidden }"
                            @click="store.select(section.id)"
                        >
                            <TriangleAlert v-if="!nodeDef(section.type)" class="size-3.5 shrink-0 text-warning" />
                            <component :is="iconFor(section.type)" v-else class="size-3.5 shrink-0 text-brand" />
                            <span class="truncate">{{ nodeLabel(section) }}</span>
                        </button>
                        <div class="flex opacity-0 transition-opacity group-focus-within:opacity-100 group-hover:opacity-100 [@media(pointer:coarse)]:opacity-100">
                            <button type="button" class="grid size-7 place-items-center rounded text-muted-foreground hover:bg-background" :disabled="si === 0" aria-label="Subir sección" @click="store.move(section.id, -1)"><ArrowUp class="size-3.5" /></button>
                            <button type="button" class="grid size-7 place-items-center rounded text-muted-foreground hover:bg-background" :disabled="si === store.state.doc.sections.length - 1" aria-label="Bajar sección" @click="store.move(section.id, 1)"><ArrowDown class="size-3.5" /></button>
                        </div>
                        <button type="button" class="grid size-7 place-items-center rounded text-muted-foreground hover:bg-background" :aria-label="section.hidden ? 'Mostrar sección' : 'Ocultar sección'" @click="store.toggleHidden(section.id)">
                            <EyeOff v-if="section.hidden" class="size-3.5" /><Eye v-else class="size-3.5 opacity-0 group-hover:opacity-100" />
                        </button>
                    </div>

                    <div v-if="expanded.has(section.id) && section.slots" class="flex flex-col gap-2 pt-1 pr-1 pb-2 pl-8">
                        <div v-for="slot in activeSlots(section)" :key="slot">
                            <div class="flex items-center justify-between px-1 pb-1">
                                <span class="text-[10px] font-semibold tracking-[0.12em] text-muted-foreground uppercase">{{ sectionDef(section.type)?.slots[slot]?.label ?? slot }}</span>
                                <button type="button" class="grid size-6 place-items-center rounded text-muted-foreground hover:bg-background hover:text-foreground" :aria-label="`Agregar bloque en ${slot}`" @click="openAdd({ sectionId: section.id, slot })">
                                    <Plus class="size-3.5" />
                                </button>
                            </div>
                            <VueDraggable
                                v-model="section.slots[slot]"
                                :group="slotGroup(section.type, slot)"
                                handle=".drag-el"
                                :animation="160"
                                ghost-class="studio-ghost"
                                class="flex min-h-8 flex-col gap-0.5 rounded-md"
                                :class="{ 'border border-dashed border-border': !section.slots[slot].length }"
                                @start="store.beginExternal()"
                                @end="store.endExternal()"
                            >
                                <div
                                    v-for="(child, ci) in section.slots[slot]"
                                    :key="child.id"
                                    :data-type="child.type"
                                    class="group flex min-h-9 items-center gap-1 rounded-md pr-1"
                                    :class="store.state.selectedId === child.id ? 'bg-brand/15 ring-1 ring-brand/60' : 'hover:bg-background'"
                                >
                                    <span class="drag-el grid h-9 w-5 shrink-0 cursor-grab place-items-center text-muted-foreground" aria-hidden="true"><GripVertical class="size-3" /></span>
                                    <button
                                        type="button"
                                        class="flex min-w-0 flex-1 items-center gap-2 py-1.5 text-left text-xs"
                                        :class="{ 'opacity-50': child.hidden }"
                                        @click="store.select(child.id)"
                                    >
                                        <TriangleAlert v-if="!nodeDef(child.type)" class="size-3.5 shrink-0 text-warning" />
                                        <component :is="iconFor(child.type)" v-else class="size-3.5 shrink-0 text-muted-foreground" />
                                        <span class="truncate">{{ nodeLabel(child) }}</span>
                                    </button>
                                    <div class="flex opacity-0 transition-opacity group-focus-within:opacity-100 group-hover:opacity-100 [@media(pointer:coarse)]:opacity-100">
                                        <button type="button" class="grid size-7 place-items-center rounded text-muted-foreground" :disabled="ci === 0" aria-label="Subir" @click="store.move(child.id, -1)"><ArrowUp class="size-3" /></button>
                                        <button type="button" class="grid size-7 place-items-center rounded text-muted-foreground" :disabled="ci === section.slots[slot].length - 1" aria-label="Bajar" @click="store.move(child.id, 1)"><ArrowDown class="size-3" /></button>
                                    </div>
                                    <button type="button" class="grid size-7 place-items-center rounded text-muted-foreground" :aria-label="child.hidden ? 'Mostrar' : 'Ocultar'" @click="store.toggleHidden(child.id)">
                                        <EyeOff v-if="child.hidden" class="size-3" /><Eye v-else class="size-3 opacity-0 group-hover:opacity-100" />
                                    </button>
                                </div>
                            </VueDraggable>
                        </div>
                    </div>
                </div>
            </VueDraggable>

            <button
                type="button"
                class="mt-2 flex min-h-11 w-full items-center justify-center gap-2 rounded-lg border border-dashed text-xs font-semibold text-muted-foreground hover:border-brand hover:text-foreground"
                @click="emit('add-section', store.state.doc.sections.length)"
            >
                <Plus class="size-4" /> Agregar sección
            </button>
        </div>

        <!-- AGREGAR -->
        <div v-else class="min-h-0 flex-1 overflow-y-auto p-3">
            <button
                type="button"
                class="flex min-h-12 w-full items-center justify-center gap-2 rounded-lg bg-brand text-sm font-semibold text-brand-foreground hover:bg-brand/90"
                @click="emit('add-section', sectionIndex())"
            >
                <Plus class="size-4" /> Agregar sección
            </button>
            <p class="mt-4 mb-2 text-[11px] font-semibold tracking-[0.12em] text-muted-foreground uppercase">Bloques</p>
            <p v-if="!addTarget" class="mb-3 rounded-md bg-muted/60 px-3 py-2 text-[11px] text-muted-foreground">
                Selecciona una sección o un bloque en el lienzo para saber dónde insertar.
            </p>
            <div class="grid grid-cols-2 gap-1.5">
                <button
                    v-for="item in elementTypes"
                    :key="item.type"
                    type="button"
                    :disabled="!item.allowed"
                    class="flex min-h-16 flex-col items-center justify-center gap-1.5 rounded-lg border px-2 text-center text-[11px] font-medium transition-colors enabled:hover:border-brand enabled:hover:bg-brand/10 disabled:opacity-35"
                    :title="item.allowed ? `Insertar ${item.def.label}` : 'No se puede colocar en esta zona'"
                    @click="insert(item.type)"
                >
                    <component :is="iconFor(item.type)" class="size-4 text-brand" />
                    {{ item.def.label }}
                </button>
            </div>
        </div>
    </aside>
</template>

<style>
.studio-ghost {
    opacity: 0.45;
    outline: 1px dashed var(--color-brand);
}
</style>
