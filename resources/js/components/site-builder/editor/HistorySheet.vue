<script setup lang="ts">
import { History, RotateCcw, Undo } from '@lucide/vue';
import { inject, ref } from 'vue';
import { toast } from 'vue-sonner';
import ConfirmDialog from '@/components/ds/ConfirmDialog.vue';
import DateText from '@/components/ds/DateText.vue';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import type { VersionRow } from '../types';
import { editorKey } from './keys';

/** Historial de versiones publicadas con restauración al borrador. */
const open = defineModel<boolean>('open', { default: false });

const editor = inject(editorKey)!;

const restoring = ref<VersionRow | null>(null);
const restoreOpen = ref(false);
const discardOpen = ref(false);

async function restore() {
    if (!restoring.value) return;
    const message = await editor.restoreVersion(restoring.value.id);
    toast.success(message);
    open.value = false;
}

async function discard() {
    const message = await editor.discard();
    toast.success(message);
    open.value = false;
}
</script>

<template>
    <Sheet v-model:open="open">
        <SheetContent
            side="right"
            class="flex w-full flex-col gap-0 p-0 sm:max-w-md"
        >
            <SheetHeader class="border-b border-border p-5">
                <SheetTitle class="font-display text-lg tracking-tight"
                    >Historial de versiones</SheetTitle
                >
                <SheetDescription>
                    Cada vez que publicas se guarda una versión. Restaurar la
                    copia al borrador; se ve en el sitio cuando vuelvas a
                    publicar.
                </SheetDescription>
            </SheetHeader>

            <div class="min-h-0 flex-1 overflow-y-auto p-3">
                <div
                    v-if="editor.draft.hasChanges"
                    class="mb-3 flex items-center justify-between gap-3 rounded-xl bg-warning-soft p-3 text-sm text-warning-ink"
                >
                    <span>Tienes cambios sin publicar.</span>
                    <Button
                        size="sm"
                        variant="outline"
                        class="h-8 bg-card"
                        @click="discardOpen = true"
                    >
                        <Undo class="size-4" /> Descartar
                    </Button>
                </div>

                <div
                    v-if="!editor.versions.value.length"
                    class="flex flex-col items-center gap-2 px-6 py-16 text-center text-sm text-muted-foreground"
                >
                    <History class="size-6" aria-hidden="true" />
                    Aún no has publicado desde el editor. Tu primera publicación
                    aparecerá aquí.
                </div>

                <ol v-else class="relative grid gap-1">
                    <li
                        v-for="(version, i) in editor.versions.value"
                        :key="version.id"
                        class="group flex items-start gap-3 rounded-xl p-3 hover:bg-muted"
                    >
                        <span
                            class="mt-0.5 grid size-8 shrink-0 place-items-center rounded-full text-xs font-semibold tabular-nums"
                            :class="
                                i === 0
                                    ? 'bg-primary text-primary-foreground'
                                    : 'bg-muted text-muted-foreground ring-1 ring-border'
                            "
                        >
                            v{{ version.number }}
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-medium">
                                <DateText
                                    :value="version.publishedAt"
                                    relative
                                />
                                <span
                                    v-if="i === 0"
                                    class="ml-1.5 rounded-full bg-success-soft px-2 py-0.5 text-[11px] font-medium text-success-ink"
                                    >En el sitio</span
                                >
                            </p>
                            <p class="text-xs text-muted-foreground">
                                {{ version.publishedBy ?? 'Sin autor' }} ·
                                {{ version.sections }} secciones
                            </p>
                            <p
                                v-if="version.note"
                                class="mt-1 text-sm text-foreground/80"
                            >
                                “{{ version.note }}”
                            </p>
                        </div>
                        <Button
                            variant="ghost"
                            size="sm"
                            class="h-8 opacity-70 group-hover:opacity-100"
                            @click="
                                ((restoring = version), (restoreOpen = true))
                            "
                        >
                            <RotateCcw class="size-4" /> Restaurar
                        </Button>
                    </li>
                </ol>
            </div>
        </SheetContent>
    </Sheet>

    <ConfirmDialog
        v-model:open="restoreOpen"
        :title="`¿Restaurar la versión ${restoring?.number ?? ''}?`"
        description="Reemplaza el borrador actual con esa versión. El sitio no cambia hasta que publiques. Puedes deshacerlo con Ctrl+Z."
        confirm-label="Restaurar en el borrador"
        loading-label="Restaurando…"
        :confirm="restore"
    />
    <ConfirmDialog
        v-model:open="discardOpen"
        title="¿Descartar los cambios sin publicar?"
        description="El borrador vuelve a ser igual a lo que hoy se ve en el sitio."
        confirm-label="Descartar cambios"
        loading-label="Descartando…"
        tone="destructive"
        :confirm="discard"
    />
</template>
