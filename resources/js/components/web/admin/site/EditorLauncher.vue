<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    ArrowRight,
    ExternalLink,
    Monitor,
    MousePointer2,
    Smartphone,
} from '@lucide/vue';
import DateText from '@/components/ds/DateText.vue';
import FinishSwatches from '@/components/ds/FinishSwatches.vue';
import StatusBadge from '@/components/ds/StatusBadge.vue';
import { Button } from '@/components/ui/button';

export type EditorSummary = {
    sections: number;
    hasChanges: boolean;
    draftSavedAt: string | null;
    lastVersion: {
        number: number;
        publishedAt: string;
        publishedBy: string | null;
    } | null;
};

/** Acceso al editor visual de la portada desde Sitio web → Portada. */
const props = defineProps<{ editor: EditorSummary }>();
</script>

<template>
    <div
        class="relative overflow-hidden rounded-2xl border border-border bg-card shadow-xs"
    >
        <div
            class="grid gap-8 p-6 sm:p-8 lg:grid-cols-[1fr_18rem] lg:items-center"
        >
            <div>
                <div class="flex flex-wrap items-center gap-2">
                    <StatusBadge
                        v-if="props.editor.hasChanges"
                        tone="warning"
                        label="Cambios sin publicar"
                        size="sm"
                    />
                    <StatusBadge
                        v-else
                        tone="success"
                        label="Al día"
                        size="sm"
                    />
                    <span class="text-sm text-muted-foreground">
                        {{ props.editor.sections }} secciones
                        <template v-if="props.editor.lastVersion">
                            · versión {{ props.editor.lastVersion.number }}
                            publicada
                            <DateText
                                :value="props.editor.lastVersion.publishedAt"
                                relative
                            />
                        </template>
                    </span>
                </div>
                <h2
                    class="mt-4 font-display text-2xl font-semibold tracking-tight"
                >
                    Editor visual de la portada
                </h2>
                <p class="mt-2 max-w-xl text-muted-foreground">
                    Arrastra, cambia de tamaño y acomoda títulos, fotos y
                    botones como en un lienzo. Tú organizas en escritorio; la
                    versión para tableta y celular se ajusta sola. Nada se ve en
                    el sitio hasta que publicas.
                </p>
                <ul
                    class="mt-5 flex flex-wrap gap-x-5 gap-y-2 text-sm text-muted-foreground"
                >
                    <li class="inline-flex items-center gap-1.5">
                        <MousePointer2
                            class="size-4 text-primary"
                            aria-hidden="true"
                        />
                        Arrastrar y soltar
                    </li>
                    <li class="inline-flex items-center gap-1.5">
                        <Monitor
                            class="size-4 text-primary"
                            aria-hidden="true"
                        />
                        Vista previa por dispositivo
                    </li>
                    <li class="inline-flex items-center gap-1.5">
                        <Smartphone
                            class="size-4 text-primary"
                            aria-hidden="true"
                        />
                        Responsivo automático
                    </li>
                </ul>
                <div class="mt-7 flex flex-wrap gap-3">
                    <Button as-child size="lg">
                        <Link href="/admin/sitio/editor">
                            Abrir el editor <ArrowRight class="size-4" />
                        </Link>
                    </Button>
                    <Button
                        as="a"
                        href="/"
                        target="_blank"
                        rel="noopener"
                        variant="outline"
                        size="lg"
                    >
                        <ExternalLink class="size-4" /> Ver portada publicada
                    </Button>
                </div>
            </div>
            <div
                class="hidden rounded-xl bg-gradient-to-br from-stone to-linen p-6 lg:block"
            >
                <FinishSwatches />
            </div>
        </div>
    </div>
</template>
