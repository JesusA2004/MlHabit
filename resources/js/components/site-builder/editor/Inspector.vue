<script setup lang="ts">
import {
    AlertTriangle,
    CheckCircle2,
    Keyboard,
    MousePointer2,
} from '@lucide/vue';
import { computed, inject } from 'vue';
import type { VisitPolicyData } from '@/types/web';
import BlockInspector from './BlockInspector.vue';
import { editorKey } from './keys';
import SectionInspector from './SectionInspector.vue';

/** Panel derecho: propiedades del elemento o de la sección seleccionada. */
const props = defineProps<{ visitPolicy: VisitPolicyData | null }>();

const editor = inject(editorKey)!;

const sectionIndex = computed(() =>
    editor.sections.value.findIndex((s) => s.key === editor.selection.section),
);
const pageErrors = computed(() =>
    Object.entries(editor.errors.value)
        .filter(([path]) => !path.includes('.layout.blocks.'))
        .map(([, m]) => m[0]),
);
const shortcuts = [
    ['Arrastrar', 'Mover'],
    ['Flechas', 'Mover 1 fila o columna'],
    ['Shift + flechas', 'Mover 4'],
    ['Alt + flechas', 'Cambiar tamaño'],
    ['Shift + clic', 'Seleccionar varios'],
    ['Doble clic', 'Editar texto o encuadrar foto'],
    ['Ctrl + D', 'Duplicar'],
    ['Supr', 'Eliminar'],
    ['Ctrl + Z', 'Deshacer'],
];
</script>

<template>
    <div class="min-h-0 flex-1 overflow-y-auto">
        <div
            v-if="pageErrors.length"
            role="alert"
            class="m-4 rounded-lg bg-danger-soft p-3 text-xs text-danger-ink"
        >
            <p v-for="(e, i) in pageErrors" :key="i">{{ e }}</p>
        </div>

        <BlockInspector
            v-if="editor.primaryBlock.value && editor.selection.section"
            :key="`${editor.selection.section}-${editor.primaryBlock.value.id}`"
            :block="editor.primaryBlock.value"
            :section-key="editor.selection.section"
            :visit-policy="props.visitPolicy"
        />
        <SectionInspector
            v-else-if="editor.currentSection.value"
            :key="editor.currentSection.value.key"
            :section="editor.currentSection.value"
            :index="sectionIndex"
        />
        <div v-else class="grid gap-6 p-5 text-sm">
            <div
                class="flex flex-col items-center gap-2 rounded-xl border border-dashed border-border p-6 text-center text-muted-foreground"
            >
                <MousePointer2 class="size-5" aria-hidden="true" />
                Selecciona un elemento o una sección para ver sus opciones.
            </div>
        </div>

        <div
            v-if="!editor.primaryBlock.value"
            class="grid gap-4 border-t border-border p-4 text-sm"
        >
            <div
                class="flex items-start gap-2 rounded-lg p-3 text-xs"
                :class="
                    editor.h1Count.value === 1
                        ? 'bg-success-soft text-success-ink'
                        : 'bg-warning-soft text-warning-ink'
                "
            >
                <CheckCircle2
                    v-if="editor.h1Count.value === 1"
                    class="size-4 shrink-0"
                    aria-hidden="true"
                />
                <AlertTriangle
                    v-else
                    class="size-4 shrink-0"
                    aria-hidden="true"
                />
                <span v-if="editor.h1Count.value === 1"
                    >La página tiene un solo título principal (H1). Bien para
                    buscadores.</span
                >
                <span v-else-if="editor.h1Count.value === 0"
                    >La página no tiene título principal (H1). Cambia el título
                    de la portada a H1.</span
                >
                <span v-else
                    >Hay {{ editor.h1Count.value }} títulos H1. Deja uno solo;
                    no podrás publicar así.</span
                >
            </div>
            <div>
                <p class="mb-2 flex items-center gap-1.5 text-xs font-semibold">
                    <Keyboard class="size-3.5" aria-hidden="true" /> Atajos
                </p>
                <dl class="grid grid-cols-[auto_1fr] gap-x-3 gap-y-1.5 text-xs">
                    <template v-for="[key, label] in shortcuts" :key="key">
                        <dt>
                            <kbd
                                class="rounded border border-border bg-muted px-1.5 py-0.5 font-sans text-[11px]"
                                >{{ key }}</kbd
                            >
                        </dt>
                        <dd class="text-muted-foreground">{{ label }}</dd>
                    </template>
                </dl>
            </div>
        </div>
    </div>
</template>
