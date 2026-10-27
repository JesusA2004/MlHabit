<script setup lang="ts">
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import type { SectionTemplate } from '../types';
import { templateIcon } from './templateIcons';

/** Galería de plantillas para agregar una sección. */
const open = defineModel<boolean>('open', { default: false });

const props = defineProps<{ templates: SectionTemplate[] }>();
const emit = defineEmits<{ pick: [template: SectionTemplate] }>();

const FREE = ['text_image', 'gallery', 'blank'];

function pick(template: SectionTemplate) {
    emit('pick', template);
    open.value = false;
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="max-h-[88vh] overflow-y-auto sm:max-w-3xl">
            <DialogHeader>
                <DialogTitle class="font-display text-xl tracking-tight"
                    >Agregar sección</DialogTitle
                >
                <DialogDescription>
                    Elige un punto de partida. Después mueve, cambia de tamaño o
                    borra lo que quieras: todo se ajusta solo a tableta y móvil.
                </DialogDescription>
            </DialogHeader>

            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <button
                    v-for="template in props.templates"
                    :key="template.id"
                    type="button"
                    class="group flex flex-col overflow-hidden rounded-xl border border-border bg-card text-left shadow-xs transition-[box-shadow,border-color,transform] duration-150 hover:-translate-y-0.5 hover:border-primary/40 hover:shadow-md focus-visible:ring-2 focus-visible:ring-ring/50 focus-visible:outline-none"
                    @click="pick(template)"
                >
                    <span
                        class="relative grid h-24 place-items-center overflow-hidden"
                        :class="
                            template.layout.settings.background.kind ===
                                'color' &&
                            ['espresso', 'primary'].includes(
                                template.layout.settings.background.color,
                            )
                                ? 'bg-espresso text-white/80'
                                : FREE.includes(template.id)
                                  ? 'bg-[linear-gradient(135deg,var(--color-linen),var(--color-stone))] text-oak-ink'
                                  : 'bg-muted text-primary'
                        "
                    >
                        <span
                            class="absolute inset-3 rounded-lg border border-dashed border-current opacity-20"
                            aria-hidden="true"
                        />
                        <component
                            :is="templateIcon(template.icon)"
                            class="size-7 transition-transform duration-200 group-hover:scale-110"
                            aria-hidden="true"
                        />
                    </span>
                    <span class="flex flex-1 flex-col gap-1 p-3.5">
                        <span class="text-sm font-semibold">{{
                            template.label
                        }}</span>
                        <span
                            class="line-clamp-2 text-xs text-muted-foreground"
                            >{{ template.description }}</span
                        >
                    </span>
                </button>
            </div>
        </DialogContent>
    </Dialog>
</template>
