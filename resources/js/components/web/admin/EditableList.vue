<script setup lang="ts">
import { ArrowDown, ArrowUp, GripVertical, Plus, Trash2 } from '@lucide/vue';
import { useId } from 'vue';
import { VueDraggable } from 'vue-draggable-plus';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';

/**
 * Lista editable y ordenable de elementos cortos (beneficios,
 * características, pasos). Cada campo se define con `fields`.
 */
type Row = Record<string, string | null>;
export type ListField = {
    key: string;
    label: string;
    kind?: 'text' | 'textarea';
    max?: number;
    required?: boolean;
};

const model = defineModel<Row[]>({ default: () => [] });

const props = withDefaults(
    defineProps<{
        fields: ListField[];
        addLabel?: string;
        max?: number;
        min?: number;
        errors?: Record<string, string | undefined>;
        errorPrefix?: string;
    }>(),
    {
        addLabel: 'Agregar',
        max: 12,
        min: 0,
        errors: () => ({}),
        errorPrefix: '',
    },
);

const uid = useId();

function blank(): Row {
    return Object.fromEntries(props.fields.map((f) => [f.key, '']));
}

function add() {
    model.value = [...model.value, blank()];
}

function update(index: number, key: string, value: string | number) {
    const next = [...model.value];
    next[index] = { ...next[index], [key]: String(value) };
    model.value = next;
}

function move(index: number, delta: number) {
    const target = index + delta;
    if (target < 0 || target >= model.value.length) return;
    const next = [...model.value];
    [next[index], next[target]] = [next[target], next[index]];
    model.value = next;
}

function remove(index: number) {
    model.value = model.value.filter((_, i) => i !== index);
}

function value(row: Row, key: string): string {
    return row[key] ?? '';
}

function errorFor(index: number, key: string): string | undefined {
    return props.errors[`${props.errorPrefix}${index}.${key}`];
}
</script>

<template>
    <div class="flex flex-col gap-3">
        <VueDraggable
            v-model="model"
            :animation="150"
            handle="[data-handle]"
            tag="ol"
            class="flex flex-col gap-2"
        >
            <li
                v-for="(row, index) in model"
                :key="index"
                class="flex gap-2 rounded-lg border border-border bg-card p-3"
            >
                <div class="flex flex-col items-center gap-1 pt-1">
                    <span
                        data-handle
                        class="hidden cursor-grab touch-none text-muted-foreground sm:block"
                        aria-hidden="true"
                        ><GripVertical class="size-4"
                    /></span>
                    <span
                        class="text-xs font-medium text-muted-foreground tabular-nums"
                        >{{ index + 1 }}</span
                    >
                </div>
                <div class="grid min-w-0 flex-1 gap-2">
                    <div
                        v-for="field in props.fields"
                        :key="field.key"
                        class="grid gap-1"
                    >
                        <Label
                            :for="`${uid}-${index}-${field.key}`"
                            class="text-xs text-muted-foreground"
                            >{{ field.label }}</Label
                        >
                        <Textarea
                            v-if="field.kind === 'textarea'"
                            :id="`${uid}-${index}-${field.key}`"
                            :model-value="value(row, field.key)"
                            :maxlength="field.max"
                            rows="2"
                            :aria-invalid="
                                errorFor(index, field.key) ? 'true' : undefined
                            "
                            @update:model-value="
                                (v) => update(index, field.key, v)
                            "
                        />
                        <Input
                            v-else
                            :id="`${uid}-${index}-${field.key}`"
                            :model-value="value(row, field.key)"
                            :maxlength="field.max"
                            :aria-invalid="
                                errorFor(index, field.key) ? 'true' : undefined
                            "
                            @update:model-value="
                                (v) => update(index, field.key, v)
                            "
                        />
                        <p
                            v-if="errorFor(index, field.key)"
                            class="text-xs text-danger-ink"
                        >
                            {{ errorFor(index, field.key) }}
                        </p>
                    </div>
                </div>
                <div class="flex flex-col gap-1">
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        class="size-8"
                        :disabled="index === 0"
                        aria-label="Mover arriba"
                        @click="move(index, -1)"
                        ><ArrowUp class="size-4"
                    /></Button>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        class="size-8"
                        :disabled="index === model.length - 1"
                        aria-label="Mover abajo"
                        @click="move(index, 1)"
                        ><ArrowDown class="size-4"
                    /></Button>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        class="size-8 text-danger-ink"
                        :disabled="model.length <= props.min"
                        aria-label="Quitar"
                        @click="remove(index)"
                        ><Trash2 class="size-4"
                    /></Button>
                </div>
            </li>
        </VueDraggable>
        <Button
            type="button"
            variant="outline"
            size="sm"
            class="self-start"
            :disabled="model.length >= props.max"
            @click="add"
        >
            <Plus class="size-4" /> {{ props.addLabel }}
        </Button>
    </div>
</template>
