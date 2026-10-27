<script setup lang="ts">
import { ref } from 'vue';
import {
    AlertDialog,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
    AlertDialogTrigger,
} from '@/components/ui/alert-dialog';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';

/**
 * Confirmación accesible basada en AlertDialog. `confirm` puede ser async:
 * el botón muestra "cargando" y el diálogo solo se cierra al resolver. Si
 * la promesa falla, el diálogo queda abierto para reintentar.
 * Nunca uses window.confirm.
 */
const open = defineModel<boolean>('open', { default: false });

const props = withDefaults(
    defineProps<{
        title: string;
        description?: string;
        confirmLabel?: string;
        /** Texto del botón mientras se ejecuta, p. ej. "Eliminando…". */
        loadingLabel?: string;
        cancelLabel?: string;
        tone?: 'default' | 'destructive';
        confirm?: () => unknown | Promise<unknown>;
    }>(),
    { confirmLabel: 'Confirmar', cancelLabel: 'Cancelar', tone: 'default' },
);

const emit = defineEmits<{ confirmed: []; cancelled: [] }>();

const loading = ref(false);
const error = ref<string | null>(null);

async function onConfirm() {
    if (loading.value) {
        return;
    }

    error.value = null;
    loading.value = true;

    try {
        await props.confirm?.();
        emit('confirmed');
        open.value = false;
    } catch (e) {
        error.value =
            e instanceof Error && e.message
                ? e.message
                : 'No se pudo completar la acción. Inténtalo de nuevo.';
    } finally {
        loading.value = false;
    }
}

function onOpenChange(value: boolean) {
    if (loading.value) {
        return;
    }

    open.value = value;

    if (!value) {
        error.value = null;
        emit('cancelled');
    }
}
</script>

<template>
    <AlertDialog :open="open" @update:open="onOpenChange">
        <AlertDialogTrigger v-if="$slots.trigger" as-child>
            <slot name="trigger" />
        </AlertDialogTrigger>
        <AlertDialogContent class="gap-5 rounded-xl sm:max-w-md">
            <AlertDialogHeader>
                <AlertDialogTitle
                    class="font-display text-lg font-semibold tracking-tight"
                >
                    {{ props.title }}
                </AlertDialogTitle>
                <AlertDialogDescription
                    v-if="props.description || $slots.default"
                    class="text-sm leading-relaxed"
                >
                    <slot>{{ props.description }}</slot>
                </AlertDialogDescription>
            </AlertDialogHeader>

            <p
                v-if="error"
                role="alert"
                class="rounded-md bg-danger-soft px-3 py-2 text-sm text-danger-ink"
            >
                {{ error }}
            </p>

            <AlertDialogFooter class="gap-2 sm:gap-2">
                <AlertDialogCancel :disabled="loading" class="h-11 sm:h-9">
                    {{ props.cancelLabel }}
                </AlertDialogCancel>
                <Button
                    :variant="
                        props.tone === 'destructive' ? 'destructive' : 'default'
                    "
                    class="h-11 sm:h-9"
                    :disabled="loading"
                    :aria-busy="loading"
                    data-test="confirm-dialog-confirm"
                    @click="onConfirm"
                >
                    <Spinner v-if="loading" />
                    {{
                        loading && props.loadingLabel
                            ? props.loadingLabel
                            : props.confirmLabel
                    }}
                </Button>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>
</template>
