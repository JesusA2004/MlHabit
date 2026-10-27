<script setup lang="ts">
import { AlertTriangle, Globe } from '@lucide/vue';
import { computed, inject, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import {
    AlertDialog,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { editorKey } from './keys';

/** Confirmación de publicación con nota opcional para el historial. */
const open = defineModel<boolean>('open', { default: false });

const editor = inject(editorKey)!;

const note = ref('');
const loading = ref(false);
const error = ref<string | null>(null);

watch(open, (value) => {
    if (value) {
        error.value = null;
        note.value = '';
    }
});

const hidden = computed(
    () => editor.sections.value.filter((s) => !s.isVisible).length,
);
const blocked = computed(() => editor.h1Count.value > 1);

async function publish() {
    if (loading.value) return;
    loading.value = true;
    error.value = null;
    try {
        const message = await editor.publish(note.value);
        toast.success(message);
        open.value = false;
    } catch (e) {
        error.value = e instanceof Error ? e.message : 'No se pudo publicar.';
    } finally {
        loading.value = false;
    }
}
</script>

<template>
    <AlertDialog :open="open" @update:open="(v) => !loading && (open = v)">
        <AlertDialogContent class="gap-5 rounded-xl sm:max-w-md">
            <AlertDialogHeader>
                <span
                    class="mb-1 grid size-10 place-items-center rounded-full bg-primary-soft text-primary"
                    aria-hidden="true"
                >
                    <Globe class="size-5" />
                </span>
                <AlertDialogTitle
                    class="font-display text-lg font-semibold tracking-tight"
                >
                    ¿Publicar los cambios?
                </AlertDialogTitle>
                <AlertDialogDescription class="text-sm leading-relaxed">
                    La portada del sitio se actualiza al instante con lo que ves
                    en el editor. La versión anterior queda en el historial.
                </AlertDialogDescription>
            </AlertDialogHeader>

            <div class="grid gap-1.5">
                <Label for="publish-note"
                    >Nota para el historial (opcional)</Label
                >
                <Textarea
                    id="publish-note"
                    v-model="note"
                    rows="2"
                    maxlength="300"
                    placeholder="p. ej. Nueva foto de portada y orden de secciones"
                />
            </div>

            <ul class="grid gap-1.5 text-xs text-muted-foreground">
                <li v-if="hidden">
                    · {{ hidden }}
                    {{
                        hidden === 1
                            ? 'sección oculta no se publicará'
                            : 'secciones ocultas no se publicarán'
                    }}.
                </li>
                <li v-if="editor.h1Count.value === 0" class="text-warning-ink">
                    · La página no tiene título principal (H1).
                </li>
            </ul>

            <p
                v-if="blocked"
                class="flex gap-2 rounded-md bg-warning-soft px-3 py-2 text-sm text-warning-ink"
            >
                <AlertTriangle
                    class="mt-0.5 size-4 shrink-0"
                    aria-hidden="true"
                />
                Hay {{ editor.h1Count.value }} títulos H1. Cambia los demás a H2
                antes de publicar.
            </p>
            <p
                v-if="error"
                role="alert"
                class="rounded-md bg-danger-soft px-3 py-2 text-sm text-danger-ink"
            >
                {{ error }}
            </p>

            <AlertDialogFooter class="gap-2 sm:gap-2">
                <AlertDialogCancel :disabled="loading" class="h-11 sm:h-9"
                    >Cancelar</AlertDialogCancel
                >
                <Button
                    class="h-11 sm:h-9"
                    :disabled="loading || blocked"
                    :aria-busy="loading"
                    @click="publish"
                >
                    <Spinner v-if="loading" />
                    {{ loading ? 'Publicando…' : 'Publicar ahora' }}
                </Button>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>
</template>
