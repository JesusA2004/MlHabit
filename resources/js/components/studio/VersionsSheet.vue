<script setup lang="ts">
import { Eye, History, Loader2, RotateCcw } from '@lucide/vue';
import { ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet';

export interface VersionRow {
    id: number;
    number: number;
    kind: 'publish' | 'snapshot';
    summary: string | null;
    author: string | null;
    createdAt: string | null;
    isCurrent: boolean;
}

const open = defineModel<boolean>('open', { default: false });
defineProps<{ versions: VersionRow[]; draftUpdatedAt: string | null; draftUpdatedBy: string | null; hasChanges: boolean }>();
const emit = defineEmits<{ view: [version: VersionRow]; restore: [version: VersionRow] }>();

const busy = ref<number | null>(null);

function fmt(iso: string | null): string {
    if (!iso) return '';

    return new Intl.DateTimeFormat('es-MX', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(iso));
}

async function restore(v: VersionRow) {
    busy.value = v.id;
    emit('restore', v);
    setTimeout(() => (busy.value = null), 800);
}
</script>

<template>
    <Sheet v-model:open="open">
        <SheetContent side="right" class="flex w-full flex-col gap-0 sm:max-w-md">
            <SheetHeader class="border-b">
                <SheetTitle class="flex items-center gap-2"><History class="size-4" /> Historial de versiones</SheetTitle>
                <SheetDescription>
                    Restaurar copia la versión al borrador; no cambia el sitio hasta que publiques. Tu borrador actual se respalda antes.
                </SheetDescription>
            </SheetHeader>
            <ol class="flex min-h-0 flex-1 flex-col gap-2 overflow-y-auto p-4">
                <li class="rounded-lg border border-dashed p-3">
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-sm font-semibold">Borrador actual</span>
                        <Badge :variant="hasChanges ? 'default' : 'outline'">{{ hasChanges ? 'Cambios sin publicar' : 'Igual a lo publicado' }}</Badge>
                    </div>
                    <p class="mt-1 text-xs text-muted-foreground">{{ fmt(draftUpdatedAt) }}{{ draftUpdatedBy ? ` · ${draftUpdatedBy}` : '' }}</p>
                </li>
                <li v-for="v in versions" :key="v.id" class="rounded-lg border p-3" :class="{ 'border-brand/60 bg-brand/5': v.isCurrent }">
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-sm font-semibold">
                            {{ v.kind === 'snapshot' ? 'Respaldo' : 'Versión' }} {{ v.number }}
                        </span>
                        <Badge v-if="v.isCurrent" class="bg-success text-success-foreground">Publicada</Badge>
                        <Badge v-else-if="v.kind === 'snapshot'" variant="outline">Respaldo automático</Badge>
                    </div>
                    <p v-if="v.summary" class="mt-1 text-sm">{{ v.summary }}</p>
                    <p class="mt-1 text-xs text-muted-foreground">{{ fmt(v.createdAt) }}{{ v.author ? ` · ${v.author}` : '' }}</p>
                    <div class="mt-2 flex gap-2">
                        <button type="button" class="inline-flex h-8 items-center gap-1.5 rounded-md border px-2.5 text-xs font-medium hover:bg-muted" @click="emit('view', v)">
                            <Eye class="size-3.5" /> Ver
                        </button>
                        <button type="button" class="inline-flex h-8 items-center gap-1.5 rounded-md border px-2.5 text-xs font-medium hover:border-brand hover:bg-brand/10" @click="restore(v)">
                            <Loader2 v-if="busy === v.id" class="size-3.5 animate-spin" />
                            <RotateCcw v-else class="size-3.5" />
                            Restaurar en borrador
                        </button>
                    </div>
                </li>
                <li v-if="!versions.length" class="py-8 text-center text-sm text-muted-foreground">Aún no hay versiones publicadas.</li>
            </ol>
        </SheetContent>
    </Sheet>
</template>
