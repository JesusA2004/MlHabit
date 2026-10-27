<script setup lang="ts">
import { computed } from 'vue';
import { Input } from '@/components/ui/input';
import { Switch } from '@/components/ui/switch';
import type { StudioLink } from '@/studio/types';
import SegmentedControl from './SegmentedControl.vue';

/**
 * Destino de un enlace: página del sitio, red social configurada, URL
 * externa o ancla de la página. Nunca acepta `javascript:`.
 */
const props = defineProps<{
    modelValue: StudioLink | null | undefined;
    label: string;
    nullable?: boolean;
    links: { value: string; label: string }[];
    social: string[];
}>();

const emit = defineEmits<{ 'update:modelValue': [value: StudioLink | null] }>();

const type = computed(() => props.modelValue?.type ?? 'none');

const typeOptions = computed(() => [
    ...(props.nullable ? [{ value: 'none', label: 'Ninguno' }] : []),
    { value: 'page', label: 'Página' },
    { value: 'social', label: 'Red' },
    { value: 'url', label: 'URL' },
    { value: 'anchor', label: 'Ancla' },
]);

function setType(value: string | null | undefined) {
    if (value === 'none' || !value) return emit('update:modelValue', null);
    const defaults: Record<string, string> = { page: '/', social: props.social[0] ?? 'instagram', url: 'https://', anchor: '' };
    emit('update:modelValue', { type: value as StudioLink['type'], value: defaults[value], newTab: value === 'url' || value === 'social' });
}

function update(patch: Partial<StudioLink>) {
    if (!props.modelValue) return;
    emit('update:modelValue', { ...props.modelValue, ...patch });
}

const urlInvalid = computed(() => type.value === 'url' && !/^(https?:\/\/|mailto:|tel:)\S+$/i.test(props.modelValue?.value ?? ''));
</script>

<template>
    <div class="flex flex-col gap-2">
        <SegmentedControl :model-value="type" :options="typeOptions" :label="`${label}: tipo`" @update:model-value="setType" />

        <select
            v-if="type === 'page'"
            class="h-9 w-full rounded-md border border-input bg-background px-2 text-sm"
            :value="modelValue?.value"
            :aria-label="`${label}: página`"
            @change="update({ value: ($event.target as HTMLSelectElement).value })"
        >
            <option v-for="link in links" :key="link.value" :value="link.value">{{ link.label }}</option>
            <option v-if="modelValue && !links.some((l) => l.value === modelValue!.value)" :value="modelValue.value">{{ modelValue.value }}</option>
        </select>

        <select
            v-else-if="type === 'social'"
            class="h-9 w-full rounded-md border border-input bg-background px-2 text-sm capitalize"
            :value="modelValue?.value"
            :aria-label="`${label}: red social`"
            @change="update({ value: ($event.target as HTMLSelectElement).value })"
        >
            <option v-for="platform in social" :key="platform" :value="platform">{{ platform }}</option>
            <option v-if="!social.length" disabled value="">Configura redes en Sitio web → Redes sociales</option>
        </select>

        <template v-else-if="type === 'url' || type === 'anchor'">
            <Input
                :model-value="modelValue?.value"
                :placeholder="type === 'url' ? 'https://… o mailto:…' : 'id-de-la-seccion'"
                :aria-invalid="urlInvalid || undefined"
                class="h-9 text-sm"
                @update:model-value="update({ value: String($event) })"
            />
            <p v-if="urlInvalid" class="text-[11px] text-destructive">Debe empezar con https://, mailto: o tel:</p>
        </template>

        <label v-if="type !== 'none' && type !== 'anchor'" class="flex items-center justify-between gap-2 text-xs text-muted-foreground">
            Abrir en pestaña nueva
            <Switch :model-value="modelValue?.newTab ?? false" @update:model-value="update({ newTab: Boolean($event) })" />
        </label>
    </div>
</template>
