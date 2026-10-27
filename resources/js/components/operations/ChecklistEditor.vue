<script setup lang="ts">
import { Check, Minus, X } from '@lucide/vue';
import { computed } from 'vue';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';
import type { ChecklistEntry } from '@/types/operations';

/**
 * Checklist de instalación/calidad (11 criterios). Cada criterio se marca
 * con botones grandes (Cumple / No cumple / No aplica); "No cumple" abre
 * una nota obligatoria. v-model: lista completa de entradas.
 */
const model = defineModel<ChecklistEntry[]>({ required: true });

const props = withDefaults(
    defineProps<{
        criteria: { value: string; label: string }[];
        disabled?: boolean;
        /** Errores de validación del backend (clave `checklist`). */
        error?: string | null;
    }>(),
    { disabled: false, error: null },
);

const RESULTS = [
    {
        value: 'pass',
        label: 'Cumple',
        icon: Check,
        active: 'bg-success text-success-foreground border-success',
    },
    {
        value: 'fail',
        label: 'No cumple',
        icon: X,
        active: 'bg-danger text-danger-foreground border-danger',
    },
    {
        value: 'na',
        label: 'No aplica',
        icon: Minus,
        active: 'bg-neutral text-neutral-foreground border-neutral',
    },
] as const;

const answered = computed(
    () => model.value.filter((e) => e.result !== '').length,
);

function entry(criterion: string): ChecklistEntry {
    return (
        model.value.find((e) => e.criterion === criterion) ?? {
            criterion,
            result: '',
            notes: '',
        }
    );
}

function setResult(criterion: string, result: ChecklistEntry['result']) {
    model.value = model.value.map((e) =>
        e.criterion === criterion ? { ...e, result } : e,
    );

    if (!model.value.some((e) => e.criterion === criterion)) {
        model.value = [...model.value, { criterion, result, notes: '' }];
    }
}

function setNotes(criterion: string, notes: string) {
    model.value = model.value.map((e) =>
        e.criterion === criterion ? { ...e, notes } : e,
    );
}

function markAllPass() {
    model.value = props.criteria.map((c) => ({
        criterion: c.value,
        result: 'pass',
        notes: entry(c.value).notes,
    }));
}
</script>

<template>
    <div class="flex flex-col gap-3">
        <div class="flex items-center justify-between gap-3">
            <p class="text-sm text-muted-foreground">
                <span class="font-semibold text-foreground tabular-nums">{{
                    answered
                }}</span>
                de {{ criteria.length }} criterios revisados
            </p>
            <button
                type="button"
                class="rounded-md px-2 py-1 text-sm font-medium text-primary hover:bg-primary-soft disabled:opacity-50"
                :disabled="disabled"
                @click="markAllPass"
            >
                Marcar todo como cumple
            </button>
        </div>

        <ul class="flex flex-col divide-y rounded-xl border bg-card">
            <li
                v-for="criterion in criteria"
                :key="criterion.value"
                class="flex flex-col gap-2 p-3"
            >
                <div
                    class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between"
                >
                    <span class="text-sm font-medium">{{
                        criterion.label
                    }}</span>
                    <div
                        class="grid grid-cols-3 gap-1.5 sm:w-auto"
                        role="radiogroup"
                        :aria-label="criterion.label"
                    >
                        <button
                            v-for="option in RESULTS"
                            :key="option.value"
                            type="button"
                            role="radio"
                            :aria-checked="
                                entry(criterion.value).result === option.value
                            "
                            :disabled="disabled"
                            :class="
                                cn(
                                    'flex h-11 items-center justify-center gap-1.5 rounded-lg border px-3 text-sm font-medium transition-colors sm:h-9',
                                    entry(criterion.value).result ===
                                        option.value
                                        ? option.active
                                        : 'bg-background hover:bg-muted',
                                )
                            "
                            @click="setResult(criterion.value, option.value)"
                        >
                            <component
                                :is="option.icon"
                                class="size-4"
                                aria-hidden="true"
                            />
                            {{ option.label }}
                        </button>
                    </div>
                </div>
                <Textarea
                    v-if="entry(criterion.value).result === 'fail'"
                    :model-value="entry(criterion.value).notes"
                    rows="2"
                    :disabled="disabled"
                    :placeholder="`¿Qué falla en ${criterion.label.toLowerCase()}?`"
                    @update:model-value="
                        setNotes(criterion.value, String($event))
                    "
                />
            </li>
        </ul>

        <p v-if="error" class="text-sm text-destructive" role="alert">
            {{ error }}
        </p>
    </div>
</template>
