<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed, useId } from 'vue';
import InputError from '@/components/InputError.vue';
import SectionCard from '@/components/ds/SectionCard.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import ajustes from '@/routes/admin/sitio/ajustes';
import type { MediaRef } from '@/types/web';
import MediaField from '../MediaField.vue';

/**
 * Formulario de un grupo de ajustes del sitio (identidad, contacto, SEO,
 * pie de página). Los campos vacíos no se muestran en el sitio.
 */
export type SettingField = {
    key: string;
    label: string;
    kind?: 'text' | 'textarea' | 'email' | 'tel' | 'media';
    help?: string;
    placeholder?: string;
    max?: number;
    aspect?: string;
};

const props = defineProps<{
    group: 'brand' | 'contact' | 'seo' | 'footer';
    title: string;
    description?: string;
    fields: SettingField[];
    values: Record<string, unknown>;
    media?: Record<string, MediaRef | null>;
}>();

const uid = useId();

const initial = computed<Record<string, string | number | null>>(() =>
    Object.fromEntries(
        props.fields.map((f) => {
            const v = props.values[f.key];

            return [
                f.key,
                typeof v === 'string' || typeof v === 'number'
                    ? v
                    : f.kind === 'media'
                      ? null
                      : '',
            ];
        }),
    ),
);
const form = useForm<Record<string, string | number | null>>({
    ...initial.value,
});

function submit() {
    form.put(ajustes.update(props.group).url, { preserveScroll: true });
}

function str(key: string): string {
    const v = form[key];

    return typeof v === 'string' ? v : v == null ? '' : String(v);
}

function mediaId(key: string): number | null {
    const v = form[key];

    return typeof v === 'number' ? v : null;
}

function mediaRef(key: string): MediaRef | null {
    const id = mediaId(key);

    return id ? (props.media?.[String(id)] ?? null) : null;
}

function errorOf(key: string): string | undefined {
    return (form.errors as Record<string, string | undefined>)[key];
}
</script>

<template>
    <SectionCard :title="props.title" :description="props.description">
        <form class="grid gap-5" @submit.prevent="submit">
            <div
                class="grid gap-5"
                :class="
                    props.fields.some((f) => f.kind === 'media')
                        ? 'sm:grid-cols-2'
                        : ''
                "
            >
                <div
                    v-for="field in props.fields"
                    :key="field.key"
                    class="grid gap-1.5"
                    :class="field.kind === 'textarea' ? 'sm:col-span-2' : ''"
                >
                    <Label :for="`${uid}-${field.key}`">{{
                        field.label
                    }}</Label>
                    <MediaField
                        v-if="field.kind === 'media'"
                        :model-value="mediaId(field.key)"
                        :media="mediaRef(field.key)"
                        collection="sitio/identidad"
                        :aspect="field.aspect ?? 'aspect-[16/9]'"
                        :invalid="!!errorOf(field.key)"
                        @update:model-value="(v) => (form[field.key] = v)"
                    />
                    <Textarea
                        v-else-if="field.kind === 'textarea'"
                        :id="`${uid}-${field.key}`"
                        :model-value="str(field.key)"
                        :maxlength="field.max"
                        :placeholder="field.placeholder"
                        rows="3"
                        @update:model-value="(v) => (form[field.key] = v)"
                    />
                    <Input
                        v-else
                        :id="`${uid}-${field.key}`"
                        :model-value="str(field.key)"
                        :type="
                            field.kind === 'email'
                                ? 'email'
                                : field.kind === 'tel'
                                  ? 'tel'
                                  : 'text'
                        "
                        :maxlength="field.max"
                        :placeholder="field.placeholder"
                        @update:model-value="(v) => (form[field.key] = v)"
                    />
                    <p
                        v-if="field.help && !errorOf(field.key)"
                        class="text-xs text-muted-foreground"
                    >
                        {{ field.help }}
                    </p>
                    <InputError :message="errorOf(field.key)" />
                </div>
            </div>
            <div class="flex justify-end">
                <Button
                    type="submit"
                    :disabled="form.processing || !form.isDirty"
                    ><Spinner v-if="form.processing" /> Guardar cambios</Button
                >
            </div>
        </form>
    </SectionCard>
</template>
