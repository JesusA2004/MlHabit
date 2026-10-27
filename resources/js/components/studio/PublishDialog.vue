<script setup lang="ts">
import { CircleCheck, CircleX, Loader2, Rocket, TriangleAlert } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import type { CheckIssue } from '@/studio/checks';

/** Revisión previa + resumen del cambio antes de publicar. */
const open = defineModel<boolean>('open', { default: false });
const props = defineProps<{
    issues: CheckIssue[];
    serverProblems: string[];
    publishing: boolean;
    pageTitle: string;
}>();
const emit = defineEmits<{ publish: [summary: string]; focus: [id: string] }>();

const summary = ref('');
watch(open, (v) => {
    if (v) summary.value = '';
});

const errors = computed(() => props.issues.filter((i) => i.level === 'error'));
const warnings = computed(() => props.issues.filter((i) => i.level === 'warning'));
const blocked = computed(() => errors.value.length > 0 || props.serverProblems.length > 0);
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>Publicar «{{ pageTitle }}»</DialogTitle>
                <DialogDescription>
                    Los visitantes verán esta versión en cuanto confirmes. Podrás restaurar la anterior desde el historial.
                </DialogDescription>
            </DialogHeader>

            <div class="flex max-h-[45vh] flex-col gap-2 overflow-y-auto">
                <div v-if="!issues.length && !serverProblems.length" class="flex items-center gap-2 rounded-lg bg-success/10 px-3 py-2.5 text-sm text-success">
                    <CircleCheck class="size-4" /> Todo listo: sin problemas detectados.
                </div>
                <button
                    v-for="(p, i) in serverProblems"
                    :key="`s${i}`"
                    type="button"
                    class="flex items-start gap-2 rounded-lg bg-destructive/10 px-3 py-2 text-left text-sm text-destructive"
                >
                    <CircleX class="mt-0.5 size-4 shrink-0" /> {{ p }}
                </button>
                <button
                    v-for="issue in errors"
                    :key="`e${issue.id}${issue.message}`"
                    type="button"
                    class="flex items-start gap-2 rounded-lg bg-destructive/10 px-3 py-2 text-left text-sm text-destructive hover:bg-destructive/15"
                    @click="issue.id && emit('focus', issue.id)"
                >
                    <CircleX class="mt-0.5 size-4 shrink-0" /> {{ issue.message }}
                </button>
                <button
                    v-for="issue in warnings"
                    :key="`w${issue.id}${issue.message}`"
                    type="button"
                    class="flex items-start gap-2 rounded-lg bg-warning/12 px-3 py-2 text-left text-sm text-warning hover:bg-warning/20"
                    @click="issue.id && emit('focus', issue.id)"
                >
                    <TriangleAlert class="mt-0.5 size-4 shrink-0" /> {{ issue.message }}
                </button>
            </div>

            <div class="flex flex-col gap-1.5">
                <label for="publish-summary" class="text-xs font-medium">Resumen del cambio (opcional)</label>
                <Input id="publish-summary" v-model="summary" maxlength="255" placeholder="Ej.: nueva foto de portada y título más grande" />
            </div>

            <DialogFooter>
                <Button variant="ghost" @click="open = false">Cancelar</Button>
                <Button :disabled="blocked || publishing" @click="emit('publish', summary)">
                    <Loader2 v-if="publishing" class="size-4 animate-spin" />
                    <Rocket v-else class="size-4" />
                    {{ warnings.length && !blocked ? 'Publicar de todos modos' : 'Publicar ahora' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
