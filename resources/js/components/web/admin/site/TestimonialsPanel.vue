<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import {
    MessageSquareQuote,
    Pencil,
    Plus,
    ShieldCheck,
    Trash2,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import ConfirmDialog from '@/components/ds/ConfirmDialog.vue';
import DatePicker from '@/components/ds/DatePicker.vue';
import EmptyState from '@/components/ds/EmptyState.vue';
import FormSheet from '@/components/ds/FormSheet.vue';
import SearchSelect from '@/components/ds/SearchSelect.vue';
import SectionCard from '@/components/ds/SectionCard.vue';
import StatusBadge from '@/components/ds/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import testimonios from '@/routes/admin/sitio/testimonios';
import type { MediaRef, Option } from '@/types/web';
import MediaField from '../MediaField.vue';

export type TestimonialRow = {
    id: number;
    authorName: string;
    authorContext: string | null;
    quote: string;
    showcaseProjectId: number | null;
    showcaseProjectTitle: string | null;
    media: MediaRef | null;
    consentObtainedAt: string | null;
    consentNote: string | null;
    isPublished: boolean;
};

const props = defineProps<{
    testimonials: TestimonialRow[];
    showcaseOptions: Option<number>[];
    canPublish: boolean;
}>();

const sheetOpen = ref(false);
const editing = ref<TestimonialRow | null>(null);
const form = useForm({
    author_name: '',
    author_context: '',
    quote: '',
    showcase_project_id: null as number | null,
    media_id: null as number | null,
    consent_obtained_at: null as string | null,
    consent_note: '',
    is_published: false,
});

const hasConsent = computed(() => !!form.consent_obtained_at);

function create() {
    editing.value = null;
    form.reset();
    form.clearErrors();
    sheetOpen.value = true;
}

function edit(row: TestimonialRow) {
    editing.value = row;
    form.author_name = row.authorName;
    form.author_context = row.authorContext ?? '';
    form.quote = row.quote;
    form.showcase_project_id = row.showcaseProjectId;
    form.media_id = row.media?.id ?? null;
    form.consent_obtained_at = row.consentObtainedAt;
    form.consent_note = row.consentNote ?? '';
    form.is_published = row.isPublished;
    form.clearErrors();
    sheetOpen.value = true;
}

function submit() {
    const options = {
        preserveScroll: true,
        onSuccess: () => (sheetOpen.value = false),
    };
    if (editing.value)
        form.put(testimonios.update(editing.value.id).url, options);
    else form.post(testimonios.store().url, options);
}

const removing = ref<TestimonialRow | null>(null);
const removeOpen = ref(false);
function remove(): Promise<void> {
    return new Promise((resolve, reject) => {
        if (!removing.value) return resolve();
        router.delete(testimonios.destroy(removing.value.id).url, {
            preserveScroll: true,
            onSuccess: () => resolve(),
            onError: () => reject(new Error('No se pudo eliminar.')),
        });
    });
}
</script>

<template>
    <SectionCard
        title="Testimonios"
        description="Solo se publican testimonios reales con el consentimiento del cliente registrado. Si no hay ninguno publicado, la sección no aparece en el sitio."
    >
        <template #actions>
            <Button size="sm" @click="create"
                ><Plus class="size-4" /> Agregar testimonio</Button
            >
        </template>

        <ul v-if="props.testimonials.length" class="grid gap-3">
            <li
                v-for="row in props.testimonials"
                :key="row.id"
                class="rounded-lg border border-border p-4"
            >
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="font-medium">
                            {{ row.authorName
                            }}<span
                                v-if="row.authorContext"
                                class="font-normal text-muted-foreground"
                            >
                                · {{ row.authorContext }}</span
                            >
                        </p>
                        <p
                            class="mt-1 line-clamp-2 text-sm text-muted-foreground"
                        >
                            “{{ row.quote }}”
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        <StatusBadge
                            :tone="row.isPublished ? 'success' : 'neutral'"
                            :label="row.isPublished ? 'Publicado' : 'Borrador'"
                            size="sm"
                        />
                        <StatusBadge
                            v-if="row.consentObtainedAt"
                            tone="info"
                            :icon="ShieldCheck"
                            label="Con consentimiento"
                            size="sm"
                        />
                        <StatusBadge
                            v-else
                            tone="warning"
                            label="Sin consentimiento"
                            size="sm"
                        />
                        <Button
                            variant="ghost"
                            size="icon"
                            class="size-8"
                            :aria-label="`Editar testimonio de ${row.authorName}`"
                            @click="edit(row)"
                            ><Pencil class="size-4"
                        /></Button>
                        <Button
                            variant="ghost"
                            size="icon"
                            class="size-8 text-danger-ink"
                            :aria-label="`Eliminar testimonio de ${row.authorName}`"
                            @click="((removing = row), (removeOpen = true))"
                            ><Trash2 class="size-4"
                        /></Button>
                    </div>
                </div>
            </li>
        </ul>
        <EmptyState
            v-else
            compact
            :icon="MessageSquareQuote"
            title="Aún no hay testimonios"
            description="Cuando un cliente autorice compartir su opinión, regístrala aquí con la fecha de su consentimiento."
        />

        <FormSheet
            v-model:open="sheetOpen"
            :title="editing ? 'Editar testimonio' : 'Nuevo testimonio'"
            size="lg"
        >
            <form
                id="testimonial-form"
                class="grid gap-4"
                @submit.prevent="submit"
            >
                <div class="grid gap-1.5">
                    <Label for="t-name">Nombre como se mostrará</Label>
                    <Input
                        id="t-name"
                        v-model="form.author_name"
                        maxlength="120"
                        placeholder="p. ej. Ana G."
                    />
                    <InputError :message="form.errors.author_name" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="t-context">Contexto (opcional)</Label>
                    <Input
                        id="t-context"
                        v-model="form.author_context"
                        maxlength="160"
                        placeholder="p. ej. Cocina integral"
                    />
                    <InputError :message="form.errors.author_context" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="t-quote">Testimonio</Label>
                    <Textarea
                        id="t-quote"
                        v-model="form.quote"
                        rows="4"
                        maxlength="1200"
                    />
                    <InputError :message="form.errors.quote" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="t-project"
                        >Proyecto de inspiración relacionado (opcional)</Label
                    >
                    <SearchSelect
                        id="t-project"
                        v-model="form.showcase_project_id"
                        :options="props.showcaseOptions"
                        clearable
                        placeholder="Sin proyecto"
                    />
                </div>
                <div class="grid gap-1.5">
                    <Label>Foto (opcional)</Label>
                    <MediaField
                        v-model="form.media_id"
                        :media="editing?.media ?? null"
                        aspect="aspect-square max-w-40"
                    />
                </div>
                <fieldset
                    class="grid gap-3 rounded-lg border border-border p-4"
                >
                    <legend class="px-1 text-sm font-medium">
                        Consentimiento
                    </legend>
                    <div class="grid gap-1.5">
                        <Label for="t-consent"
                            >Fecha en que el cliente autorizó publicarlo</Label
                        >
                        <DatePicker
                            id="t-consent"
                            v-model="form.consent_obtained_at"
                        />
                        <InputError
                            :message="form.errors.consent_obtained_at"
                        />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="t-note">Cómo se obtuvo (opcional)</Label>
                        <Input
                            id="t-note"
                            v-model="form.consent_note"
                            maxlength="255"
                            placeholder="p. ej. Autorización por escrito en el acta de entrega"
                        />
                    </div>
                </fieldset>
                <label
                    class="flex items-start justify-between gap-3 rounded-lg border border-border p-3 text-sm"
                >
                    <span>
                        Publicar en el sitio
                        <span
                            v-if="!props.canPublish"
                            class="block text-xs text-muted-foreground"
                            >Necesitas permiso para publicar contenido.</span
                        >
                        <span
                            v-else-if="!hasConsent"
                            class="block text-xs text-muted-foreground"
                            >Registra la fecha de consentimiento para poder
                            publicarlo.</span
                        >
                    </span>
                    <Switch
                        v-model="form.is_published"
                        :disabled="!props.canPublish || !hasConsent"
                    />
                </label>
                <InputError :message="form.errors.is_published" />
            </form>
            <template #footer>
                <Button variant="outline" @click="sheetOpen = false"
                    >Cancelar</Button
                >
                <Button
                    type="submit"
                    form="testimonial-form"
                    :disabled="form.processing"
                    ><Spinner v-if="form.processing" /> Guardar</Button
                >
            </template>
        </FormSheet>

        <ConfirmDialog
            v-model:open="removeOpen"
            title="¿Eliminar este testimonio?"
            description="Se borra de forma permanente y deja de mostrarse en el sitio."
            confirm-label="Eliminar"
            tone="destructive"
            :confirm="remove"
        />
    </SectionCard>
</template>
