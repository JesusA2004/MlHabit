<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { CheckCircle2, MessageSquareWarning, ShieldCheck } from '@lucide/vue';
import { computed, ref } from 'vue';
import {
    DateText,
    PageContainer,
    ProgressSteps,
    SectionCard,
    StatusBadge,
} from '@/components/ds';
import type { ProgressStep } from '@/components/ds';
import InputError from '@/components/InputError.vue';
import EvidenceUploader from '@/components/operations/EvidenceUploader.vue';
import MediaGrid from '@/components/operations/MediaGrid.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';
import instalaciones from '@/routes/portal/instalaciones';
import type { InstallationDetail } from '@/types/operations';

const props = defineProps<{
    order: InstallationDetail;
    canRespond: boolean;
    maxPhotoBytes: number;
    rightsNotice: string;
}>();

const steps: ProgressStep[] = [
    { key: 'scheduled', title: 'Programada' },
    { key: 'in_progress', title: 'Instalando' },
    { key: 'client_review', title: 'Tu revisión' },
    { key: 'quality_review', title: 'Revisión de calidad' },
    { key: 'approved', title: 'Entregada' },
];

const items = computed(() =>
    props.order.items.filter((i) => i.status.value !== 'rejected'),
);

const form = useForm({
    conformity: '' as '' | 'conforming' | 'pending_items',
    comment: '',
    pending_item_ids: [] as number[],
    media_ids: [] as number[],
});
const uploading = ref(false);

function togglePending(id: number, checked: boolean | 'indeterminate') {
    form.pending_item_ids =
        checked === true
            ? [...form.pending_item_ids, id]
            : form.pending_item_ids.filter((x) => x !== id);
}

const canSubmit = computed(
    () =>
        form.conformity === 'conforming' ||
        (form.conformity === 'pending_items' &&
            form.comment.trim() !== '' &&
            !uploading.value),
);

function submit() {
    form.transform((data) => ({
        ...data,
        pending_item_ids:
            data.conformity === 'pending_items' ? data.pending_item_ids : [],
        media_ids: data.conformity === 'pending_items' ? data.media_ids : [],
    })).post(instalaciones.conformidad(props.order.id).url, {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head title="Revisa tu instalación" />

    <PageContainer width="narrow" class="flex flex-col gap-6 py-6 md:py-10">
        <header class="flex flex-col gap-2">
            <p class="text-sm text-muted-foreground">
                {{ order.project.name }} · {{ order.folio }}
            </p>
            <h1 class="font-display text-3xl font-semibold tracking-tight">
                {{
                    canRespond
                        ? '¿Cómo quedó tu instalación?'
                        : 'Tu instalación'
                }}
            </h1>
            <p v-if="canRespond" class="text-muted-foreground">
                Nuestro equipo terminó y tomó fotos de cada módulo. Revísalas
                con calma y cuéntanos si todo quedó como esperabas.
            </p>
        </header>

        <ProgressSteps
            :steps="steps"
            :current="order.step"
            :completed="order.step === 'approved'"
            label="Avance de la instalación"
        />

        <p
            v-if="
                order.scheduledStart &&
                ['scheduled', 'in_progress'].includes(order.status.value)
            "
            class="rounded-xl border bg-card p-4 text-sm"
        >
            Instalación programada para el
            <DateText
                :value="order.scheduledStart"
                format="datetime"
            /><template v-if="order.leadInstaller"
                >, con {{ order.leadInstaller.name }} a cargo</template
            >.
        </p>

        <!-- Respuesta ya registrada -->
        <section
            v-if="order.clientConformity"
            :class="
                cn(
                    'flex gap-3 rounded-2xl border p-5',
                    order.clientConformity.value === 'conforming'
                        ? 'border-success/30 bg-success-soft text-success-ink'
                        : 'border-warning/30 bg-warning-soft text-warning-ink',
                )
            "
        >
            <component
                :is="
                    order.clientConformity.value === 'conforming'
                        ? CheckCircle2
                        : MessageSquareWarning
                "
                class="size-6 shrink-0"
                aria-hidden="true"
            />
            <div class="flex flex-col gap-1">
                <p class="font-semibold">
                    {{
                        order.clientConformity.value === 'conforming'
                            ? 'Nos dijiste que todo quedó conforme'
                            : 'Nos reportaste pendientes'
                    }}
                </p>
                <p
                    v-if="order.clientComment"
                    class="text-sm whitespace-pre-line text-foreground"
                >
                    “{{ order.clientComment }}”
                </p>
                <p class="text-sm">
                    Respuesta del
                    <DateText
                        :value="order.clientSignedAt"
                        format="datetime"
                    />.
                    {{
                        order.clientConformity.value === 'conforming'
                            ? 'Nuestro equipo de calidad hará la revisión final.'
                            : 'Te contactaremos para atenderlos.'
                    }}
                </p>
                <MediaGrid
                    v-if="order.clientPhotos.length"
                    :items="order.clientPhotos"
                    size="sm"
                />
                <p
                    class="mt-1 flex items-center gap-1.5 text-sm font-medium text-foreground"
                >
                    <ShieldCheck
                        class="size-4 text-primary"
                        aria-hidden="true"
                    />
                    {{ rightsNotice }}
                </p>
            </div>
        </section>

        <!-- Evidencia por módulo -->
        <section aria-labelledby="modulos" class="flex flex-col gap-4">
            <h2
                id="modulos"
                class="font-display text-xl font-semibold tracking-tight"
            >
                Módulos instalados
            </h2>
            <p
                v-if="items.every((i) => i.evidence.length === 0)"
                class="rounded-xl border border-dashed p-6 text-center text-sm text-muted-foreground"
            >
                Las fotos aparecerán aquí cuando el equipo termine la
                instalación.
            </p>
            <SectionCard
                v-for="item in items"
                v-else
                :key="item.id"
                :title="item.description"
            >
                <template #actions>
                    <StatusBadge
                        v-if="item.status.value === 'done'"
                        tone="success"
                        label="Terminado"
                        size="sm"
                    />
                </template>
                <MediaGrid
                    :items="item.evidence"
                    empty-text="Sin fotos de este módulo."
                />
            </SectionCard>
        </section>

        <!-- Respuesta del cliente -->
        <section
            v-if="canRespond"
            aria-labelledby="respuesta"
            class="flex flex-col gap-4 rounded-2xl border bg-card p-5 md:p-6"
        >
            <h2
                id="respuesta"
                class="font-display text-xl font-semibold tracking-tight"
            >
                Tu respuesta
            </h2>

            <p
                class="flex items-start gap-2 rounded-xl bg-primary-soft/60 p-4 text-sm font-medium text-foreground"
            >
                <ShieldCheck
                    class="mt-0.5 size-5 shrink-0 text-primary"
                    aria-hidden="true"
                />
                {{ rightsNotice }}
            </p>

            <div
                class="grid gap-3 sm:grid-cols-2"
                role="radiogroup"
                aria-labelledby="respuesta"
            >
                <button
                    type="button"
                    role="radio"
                    :aria-checked="form.conformity === 'conforming'"
                    :class="
                        cn(
                            'flex min-h-24 flex-col items-start gap-1 rounded-2xl border-2 p-4 text-left transition-colors',
                            form.conformity === 'conforming'
                                ? 'border-success bg-success-soft'
                                : 'hover:border-success/50',
                        )
                    "
                    @click="form.conformity = 'conforming'"
                >
                    <CheckCircle2
                        class="size-6 text-success"
                        aria-hidden="true"
                    />
                    <span class="font-semibold">Todo conforme</span>
                    <span class="text-sm text-muted-foreground"
                        >La instalación quedó como esperaba.</span
                    >
                </button>
                <button
                    type="button"
                    role="radio"
                    :aria-checked="form.conformity === 'pending_items'"
                    :class="
                        cn(
                            'flex min-h-24 flex-col items-start gap-1 rounded-2xl border-2 p-4 text-left transition-colors',
                            form.conformity === 'pending_items'
                                ? 'border-warning bg-warning-soft'
                                : 'hover:border-warning/50',
                        )
                    "
                    @click="form.conformity = 'pending_items'"
                >
                    <MessageSquareWarning
                        class="size-6 text-warning"
                        aria-hidden="true"
                    />
                    <span class="font-semibold">Hay pendientes</span>
                    <span class="text-sm text-muted-foreground"
                        >Algo falta o no quedó bien.</span
                    >
                </button>
            </div>
            <InputError :message="form.errors.conformity" />

            <div
                v-if="form.conformity === 'pending_items'"
                class="flex flex-col gap-4"
            >
                <fieldset class="flex flex-col gap-2">
                    <legend class="mb-1 text-sm font-medium">
                        ¿En qué módulos? (opcional)
                    </legend>
                    <label
                        v-for="item in items"
                        :key="item.id"
                        class="flex min-h-12 items-center gap-3 rounded-xl border px-3"
                    >
                        <Checkbox
                            :model-value="
                                form.pending_item_ids.includes(item.id)
                            "
                            @update:model-value="togglePending(item.id, $event)"
                        />
                        <span class="text-sm">{{ item.description }}</span>
                    </label>
                </fieldset>

                <div class="grid gap-1.5">
                    <Label for="comment">Cuéntanos qué está pendiente</Label>
                    <Textarea
                        id="comment"
                        v-model="form.comment"
                        rows="4"
                        placeholder="Ej. La puerta del módulo de la esquina roza al cerrar."
                    />
                    <InputError :message="form.errors.comment" />
                </div>

                <div class="grid gap-1.5">
                    <Label>Fotos (opcional)</Label>
                    <EvidenceUploader
                        v-model="form.media_ids"
                        :max-size="maxPhotoBytes"
                        :allow-video="true"
                        :max-files="10"
                        collection="privado/conformidad"
                        @busy="uploading = $event"
                    />
                    <InputError :message="form.errors.media_ids" />
                </div>
            </div>

            <Button
                class="h-12 text-base"
                :disabled="!canSubmit || form.processing"
                @click="submit"
            >
                {{
                    form.processing
                        ? 'Enviando…'
                        : form.conformity === 'pending_items'
                          ? 'Enviar pendientes'
                          : 'Confirmar que todo quedó conforme'
                }}
            </Button>
        </section>
    </PageContainer>
</template>
