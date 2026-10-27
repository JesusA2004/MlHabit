<script setup lang="ts">
import {
    Copy,
    Eye,
    EyeOff,
    GripVertical,
    MoreHorizontal,
    Plus,
    Trash2,
} from '@lucide/vue';
import { computed, inject, ref } from 'vue';
import { VueDraggable } from 'vue-draggable-plus';
import ConfirmDialog from '@/components/ds/ConfirmDialog.vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import type { RenderSection } from '../types';
import { editorKey } from './keys';
import { templateIcon } from './templateIcons';

/**
 * Lista de secciones de la página: arrastrar para reordenar, mostrar u
 * ocultar, duplicar y eliminar (con confirmación).
 */
const emit = defineEmits<{ add: [index: number] }>();

const editor = inject(editorKey)!;

const list = computed({
    get: () => editor.sections.value,
    set: (value: RenderSection[]) =>
        editor.setSectionOrder(value.map((s) => s.key)),
});

const deleting = ref<RenderSection | null>(null);
const deleteOpen = ref(false);

function label(section: RenderSection): string {
    return section.layout.settings.name || 'Sección';
}

function summary(section: RenderSection): string {
    const heading = section.layout.blocks.find((b) => b.type === 'heading');
    const text = heading?.props.text;

    return typeof text === 'string' && text
        ? text
        : `${section.layout.blocks.length} elementos`;
}

function select(section: RenderSection) {
    editor.selectSection(section.key);
    document
        .querySelector(`[data-section-key="${section.key}"]`)
        ?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}
</script>

<template>
    <div class="flex min-h-0 flex-1 flex-col">
        <div class="flex items-center justify-between px-4 pt-4 pb-2">
            <p class="text-xs font-medium text-muted-foreground">
                {{ editor.sections.value.length }} secciones
            </p>
            <Button
                size="sm"
                variant="outline"
                class="h-8"
                @click="emit('add', editor.sections.value.length)"
            >
                <Plus class="size-4" /> Agregar
            </Button>
        </div>

        <VueDraggable
            v-model="list"
            :animation="160"
            handle="[data-handle]"
            tag="ol"
            class="flex min-h-0 flex-1 flex-col gap-1 overflow-y-auto px-2 pb-4"
            aria-label="Secciones de la página"
        >
            <li
                v-for="(section, index) in list"
                :key="section.key"
                class="group relative flex items-center gap-1 rounded-lg border border-transparent py-1.5 pr-1 pl-1 transition-colors"
                :class="[
                    editor.selection.section === section.key
                        ? 'border-primary/30 bg-primary-soft'
                        : 'hover:bg-muted',
                    !section.isVisible && 'opacity-60',
                ]"
            >
                <span
                    data-handle
                    class="grid h-8 w-5 shrink-0 cursor-grab touch-none place-items-center text-muted-foreground/60 hover:text-foreground"
                    aria-hidden="true"
                >
                    <GripVertical class="size-4" />
                </span>
                <button
                    type="button"
                    class="flex min-w-0 flex-1 items-center gap-2.5 rounded-md py-0.5 text-left focus-visible:ring-2 focus-visible:ring-ring/50 focus-visible:outline-none"
                    :aria-current="
                        editor.selection.section === section.key
                            ? 'true'
                            : undefined
                    "
                    @click="select(section)"
                >
                    <span
                        class="grid size-8 shrink-0 place-items-center rounded-md bg-card text-muted-foreground shadow-xs ring-1 ring-border"
                    >
                        <component
                            :is="templateIcon(section.type)"
                            class="size-4"
                            aria-hidden="true"
                        />
                    </span>
                    <span class="min-w-0">
                        <span class="block truncate text-sm font-medium">
                            <span
                                class="mr-1 text-muted-foreground tabular-nums"
                                >{{ index + 1 }}.</span
                            >{{ label(section) }}
                        </span>
                        <span
                            class="block truncate text-xs text-muted-foreground"
                            >{{ summary(section) }}</span
                        >
                    </span>
                </button>
                <button
                    type="button"
                    class="grid size-7 shrink-0 place-items-center rounded-md text-muted-foreground hover:bg-card hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring/50 focus-visible:outline-none"
                    :aria-label="
                        section.isVisible
                            ? `Ocultar ${label(section)}`
                            : `Mostrar ${label(section)}`
                    "
                    :aria-pressed="!section.isVisible"
                    @click="editor.toggleSectionVisible(section.key)"
                >
                    <EyeOff v-if="!section.isVisible" class="size-4" />
                    <Eye
                        v-else
                        class="size-4 opacity-0 group-hover:opacity-100 focus-visible:opacity-100"
                    />
                </button>
                <DropdownMenu>
                    <DropdownMenuTrigger as-child>
                        <button
                            type="button"
                            class="grid size-7 shrink-0 place-items-center rounded-md text-muted-foreground hover:bg-card hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring/50 focus-visible:outline-none"
                            :aria-label="`Más acciones de ${label(section)}`"
                        >
                            <MoreHorizontal class="size-4" />
                        </button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" class="w-48">
                        <DropdownMenuItem @select="emit('add', index + 1)">
                            <Plus class="size-4" /> Agregar debajo
                        </DropdownMenuItem>
                        <DropdownMenuItem
                            @select="editor.duplicateSection(section.key)"
                        >
                            <Copy class="size-4" /> Duplicar
                        </DropdownMenuItem>
                        <DropdownMenuItem
                            @select="editor.toggleSectionVisible(section.key)"
                        >
                            <EyeOff class="size-4" />
                            {{ section.isVisible ? 'Ocultar' : 'Mostrar' }}
                        </DropdownMenuItem>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem
                            class="text-danger-ink focus:text-danger-ink"
                            @select="
                                ((deleting = section), (deleteOpen = true))
                            "
                        >
                            <Trash2 class="size-4" /> Eliminar
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            </li>
        </VueDraggable>

        <ConfirmDialog
            v-model:open="deleteOpen"
            :title="`¿Eliminar «${deleting ? label(deleting) : ''}»?`"
            description="La sección se quita del borrador. Puedes deshacerlo con Ctrl+Z o restaurar una versión publicada desde el historial."
            confirm-label="Eliminar sección"
            tone="destructive"
            :confirm="() => deleting && editor.deleteSection(deleting.key)"
        />
    </div>
</template>
