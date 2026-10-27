<script setup lang="ts">
import { computed, useId } from 'vue';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import type { MediaRef, SchemaField } from '@/types/web';
import EditableList from './EditableList.vue';
import MediaField from './MediaField.vue';

/**
 * Control de formulario para un campo del esquema de una sección del CMS
 * (el esquema y la validación viven en App\Cms\Sections).
 */
const model = defineModel<unknown>();

const props = defineProps<{
    field: SchemaField;
    media?: Record<string, MediaRef | null>;
    errors?: Record<string, string | undefined>;
}>();

const id = useId();
const error = computed(() => props.errors?.[`content.${props.field.key}`]);

const textValue = computed({
    get: () =>
        typeof model.value === 'string'
            ? model.value
            : model.value == null
              ? ''
              : String(model.value),
    set: (v: string) => (model.value = v),
});
const numberValue = computed({
    get: () =>
        typeof model.value === 'number'
            ? model.value
            : Number(model.value ?? 0),
    set: (v: number) => (model.value = Number(v)),
});
const boolValue = computed({
    get: () => model.value === true,
    set: (v: boolean) => (model.value = v),
});
const mediaValue = computed({
    get: () => (typeof model.value === 'number' ? model.value : null),
    set: (v: number | null) => (model.value = v),
});
const listValue = computed({
    get: () =>
        Array.isArray(model.value)
            ? (model.value as Record<string, string | null>[])
            : [],
    set: (v: Record<string, string | null>[]) => (model.value = v),
});
const topics = computed(() =>
    Array.isArray(model.value) ? (model.value as string[]) : [],
);

function toggleTopic(value: string, checked: boolean | 'indeterminate') {
    const set = new Set(topics.value);
    if (checked === true) set.add(value);
    else set.delete(value);
    model.value = [...set];
}

const listErrors = computed(() => {
    const out: Record<string, string | undefined> = {};
    for (const [key, message] of Object.entries(props.errors ?? {})) {
        const prefix = `content.${props.field.key}.`;
        if (key.startsWith(prefix)) out[key.slice(prefix.length)] = message;
    }

    return out;
});
</script>

<template>
    <div class="grid gap-1.5">
        <div
            v-if="props.field.kind === 'toggle'"
            class="flex items-start justify-between gap-4 rounded-lg border border-border p-3"
        >
            <div>
                <Label :for="id">{{ props.field.label }}</Label>
                <p
                    v-if="props.field.help"
                    class="mt-1 text-xs text-muted-foreground"
                >
                    {{ props.field.help }}
                </p>
            </div>
            <Switch :id="id" v-model="boolValue" />
        </div>

        <template v-else>
            <Label :for="id">
                {{ props.field.label }}
                <span
                    v-if="props.field.required"
                    class="text-danger-ink"
                    aria-hidden="true"
                    >*</span
                >
            </Label>

            <Textarea
                v-if="props.field.kind === 'textarea'"
                :id="id"
                v-model="textValue"
                :maxlength="props.field.max"
                rows="3"
                :aria-invalid="error ? 'true' : undefined"
            />
            <Input
                v-else-if="
                    props.field.kind === 'text' || props.field.kind === 'link'
                "
                :id="id"
                v-model="textValue"
                :maxlength="props.field.max"
                :placeholder="
                    props.field.kind === 'link' ? '/solicitar' : undefined
                "
                :aria-invalid="error ? 'true' : undefined"
            />
            <Input
                v-else-if="props.field.kind === 'number'"
                :id="id"
                v-model.number="numberValue"
                type="number"
                :min="props.field.min"
                :max="props.field.max"
                class="w-32"
                :aria-invalid="error ? 'true' : undefined"
            />
            <MediaField
                v-else-if="props.field.kind === 'media'"
                v-model="mediaValue"
                :media="
                    mediaValue
                        ? (props.media?.[String(mediaValue)] ?? null)
                        : null
                "
                collection="sitio/portada"
                :invalid="!!error"
            />
            <EditableList
                v-else-if="props.field.kind === 'list'"
                v-model="listValue"
                :fields="
                    (props.field.itemFields ?? []).map((f) => ({
                        key: f.key,
                        label: f.label,
                        kind: f.kind === 'textarea' ? 'textarea' : 'text',
                        max: f.max,
                    }))
                "
                :max="props.field.max"
                :min="props.field.min"
                :errors="listErrors"
            />
            <fieldset
                v-else-if="props.field.kind === 'topics'"
                class="grid gap-2 sm:grid-cols-2"
            >
                <legend class="sr-only">{{ props.field.label }}</legend>
                <label
                    v-for="option in props.field.options ?? []"
                    :key="option.value"
                    class="flex items-center gap-2 rounded-md border border-border px-3 py-2 text-sm"
                >
                    <Checkbox
                        :model-value="topics.includes(option.value)"
                        @update:model-value="
                            (v) => toggleTopic(option.value, v)
                        "
                    />
                    {{ option.label }}
                </label>
            </fieldset>

            <p
                v-if="props.field.help && !error"
                class="text-xs text-muted-foreground"
            >
                {{ props.field.help }}
            </p>
            <p v-if="error" class="text-xs text-danger-ink">{{ error }}</p>
        </template>
    </div>
</template>
