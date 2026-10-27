<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { ChevronDown, Download, FileText, PencilRuler } from '@lucide/vue';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import {
    DateText,
    EmptyState,
    PageContainer,
    PageHeader,
    SectionCard,
    StatusBadge,
} from '@/components/ds';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { decision } from '@/routes/portal/disenos';
import type { DesignData, FileItem, ProjectOption } from '@/types/projects';

const props = defineProps<{
    projects: ProjectOption[];
    project: { uuid: string; folio: string; name: string } | null;
    designs: DesignData[];
}>();

const latest = computed(() => props.designs[0] ?? null);
const previous = computed(() => props.designs.slice(1));
const awaiting = computed(
    () => latest.value?.status.value === 'sent_to_client',
);

const mode = ref<'approve' | 'changes'>('approve');
const form = useForm({
    decision: 'approve',
    layout: false,
    finishes: false,
    comment: '',
});

function submit() {
    if (!latest.value) {
        return;
    }

    form.decision = mode.value;
    form.post(decision(latest.value.id).url, {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
}

const images = (files: FileItem[]) => files.filter((f) => f.is_image && f.url);
const documents = (files: FileItem[]) =>
    files.filter((f) => !f.is_image && f.url);
const openPrevious = ref(false);
</script>

<template>
    <Head title="Mi diseño" />

    <PageContainer class="flex flex-col gap-6">
        <PageHeader
            title="Diseño"
            :description="
                project
                    ? `${project.name} · ${project.folio}`
                    : 'Aquí verás los renders y planos de tu mueble.'
            "
        />

        <EmptyState
            v-if="!latest"
            :icon="PencilRuler"
            title="Tu diseño está en camino"
            description="Nuestro equipo lo prepara con las medidas de tu espacio. Te avisaremos por correo cuando puedas revisarlo."
        />

        <template v-else>
            <SectionCard>
                <template #header>
                    <div
                        class="flex w-full flex-wrap items-start justify-between gap-3"
                    >
                        <div>
                            <h2 class="text-base font-semibold text-foreground">
                                Versión {{ latest.number }} · {{ latest.title }}
                            </h2>
                            <p
                                v-if="latest.sent_at"
                                class="text-sm text-muted-foreground"
                            >
                                Enviado el
                                <DateText
                                    :value="latest.sent_at"
                                    format="long"
                                />
                            </p>
                        </div>
                        <StatusBadge
                            :tone="latest.status.tone"
                            :label="latest.status.client_label"
                        />
                    </div>
                </template>

                <div
                    v-if="images(latest.files).length"
                    class="grid gap-3 sm:grid-cols-2"
                >
                    <a
                        v-for="file in images(latest.files)"
                        :key="file.id"
                        :href="file.url!"
                        target="_blank"
                        rel="noopener"
                        class="group overflow-hidden rounded-lg border bg-muted focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    >
                        <img
                            :src="file.url!"
                            :alt="
                                file.caption ??
                                `Render de la versión ${latest.number}`
                            "
                            loading="lazy"
                            class="aspect-[4/3] w-full object-cover transition group-hover:scale-[1.02]"
                        />
                        <span
                            v-if="file.caption"
                            class="block px-3 py-2 text-xs text-muted-foreground"
                            >{{ file.caption }}</span
                        >
                    </a>
                </div>
                <ul
                    v-if="documents(latest.files).length"
                    class="mt-3 flex flex-col gap-2"
                >
                    <li v-for="file in documents(latest.files)" :key="file.id">
                        <a
                            :href="file.url!"
                            class="flex items-center gap-2 rounded-lg border px-3 py-2.5 text-sm hover:bg-muted/50"
                        >
                            <FileText
                                class="size-4 text-muted-foreground"
                                aria-hidden="true"
                            />
                            <span class="min-w-0 flex-1 truncate">{{
                                file.caption ?? file.name
                            }}</span>
                            <Download
                                class="size-4 text-muted-foreground"
                                aria-hidden="true"
                            />
                            <span class="sr-only">Descargar</span>
                        </a>
                    </li>
                </ul>
                <p
                    v-if="latest.notes"
                    class="mt-4 text-sm whitespace-pre-line text-foreground"
                >
                    {{ latest.notes }}
                </p>
                <p
                    v-if="latest.client_comment && !awaiting"
                    class="mt-4 rounded-lg bg-muted/50 p-3 text-sm text-muted-foreground"
                >
                    Tu comentario: «{{ latest.client_comment }}»
                </p>
            </SectionCard>

            <!-- Decisión -->
            <SectionCard
                v-if="awaiting"
                title="¿Qué te parece?"
                description="Si todo está como lo imaginas, apruébalo. Si no, cuéntanos qué cambiarías."
            >
                <div
                    class="mb-4 grid grid-cols-2 gap-2"
                    role="radiogroup"
                    aria-label="Tu decisión"
                >
                    <Button
                        type="button"
                        role="radio"
                        :aria-checked="mode === 'approve'"
                        :variant="mode === 'approve' ? 'default' : 'outline'"
                        @click="mode = 'approve'"
                    >
                        Aprobar
                    </Button>
                    <Button
                        type="button"
                        role="radio"
                        :aria-checked="mode === 'changes'"
                        :variant="mode === 'changes' ? 'default' : 'outline'"
                        @click="mode = 'changes'"
                    >
                        Pedir cambios
                    </Button>
                </div>

                <form class="flex flex-col gap-4" @submit.prevent="submit">
                    <fieldset
                        v-if="mode === 'approve'"
                        class="flex flex-col gap-3"
                    >
                        <legend
                            class="mb-1 text-sm font-medium text-foreground"
                        >
                            Confirma lo que apruebas
                        </legend>
                        <div class="flex items-start gap-2.5">
                            <Checkbox
                                id="approve-layout"
                                :model-value="form.layout"
                                @update:model-value="
                                    (v) => (form.layout = v === true)
                                "
                            />
                            <Label
                                for="approve-layout"
                                class="leading-snug font-normal"
                                >La distribución: medidas, módulos y
                                ubicación.</Label
                            >
                        </div>
                        <div class="flex items-start gap-2.5">
                            <Checkbox
                                id="approve-finishes"
                                :model-value="form.finishes"
                                @update:model-value="
                                    (v) => (form.finishes = v === true)
                                "
                            />
                            <Label
                                for="approve-finishes"
                                class="leading-snug font-normal"
                                >Los acabados: colores, materiales y
                                herrajes.</Label
                            >
                        </div>
                        <InputError :message="form.errors.layout" />
                    </fieldset>

                    <div class="flex flex-col gap-2">
                        <Label for="design-comment">{{
                            mode === 'approve'
                                ? 'Comentario (opcional)'
                                : '¿Qué te gustaría cambiar?'
                        }}</Label>
                        <Textarea
                            id="design-comment"
                            v-model="form.comment"
                            rows="4"
                            :placeholder="
                                mode === 'approve'
                                    ? 'Algo que quieras que sepamos'
                                    : 'Ej.: quiero más cajones bajo la tarja y un tono de madera más claro'
                            "
                            :aria-invalid="!!form.errors.comment"
                            aria-describedby="design-comment-error"
                        />
                        <InputError
                            id="design-comment-error"
                            :message="
                                form.errors.comment ??
                                (form.errors as Record<string, string>).status
                            "
                        />
                    </div>

                    <Button
                        type="submit"
                        class="self-start max-sm:w-full"
                        :disabled="
                            form.processing ||
                            (mode === 'approve' &&
                                !(form.layout && form.finishes)) ||
                            (mode === 'changes' && !form.comment.trim())
                        "
                    >
                        {{
                            form.processing
                                ? 'Enviando…'
                                : mode === 'approve'
                                  ? 'Aprobar diseño'
                                  : 'Enviar comentarios'
                        }}
                    </Button>
                </form>
            </SectionCard>

            <Collapsible v-if="previous.length" v-model:open="openPrevious">
                <CollapsibleTrigger as-child>
                    <Button variant="ghost" class="self-start">
                        Versiones anteriores ({{ previous.length }})
                        <ChevronDown
                            :class="[
                                'size-4 transition',
                                openPrevious && 'rotate-180',
                            ]"
                            aria-hidden="true"
                        />
                    </Button>
                </CollapsibleTrigger>
                <CollapsibleContent>
                    <ul class="mt-2 flex flex-col gap-2">
                        <li
                            v-for="d in previous"
                            :key="d.id"
                            class="rounded-xl border bg-card p-4"
                        >
                            <div
                                class="flex flex-wrap items-center justify-between gap-2"
                            >
                                <p class="text-sm font-medium text-foreground">
                                    Versión {{ d.number }} · {{ d.title }}
                                </p>
                                <StatusBadge
                                    :tone="d.status.tone"
                                    :label="d.status.client_label"
                                    size="sm"
                                />
                            </div>
                            <div class="mt-2 flex flex-wrap gap-2">
                                <a
                                    v-for="file in d.files.filter((f) => f.url)"
                                    :key="file.id"
                                    :href="file.url!"
                                    target="_blank"
                                    rel="noopener"
                                    class="text-sm text-primary underline-offset-4 hover:underline"
                                >
                                    {{ file.caption ?? file.name }}
                                </a>
                            </div>
                            <p
                                v-if="d.client_comment"
                                class="mt-2 text-sm text-muted-foreground"
                            >
                                Tu comentario: «{{ d.client_comment }}»
                            </p>
                        </li>
                    </ul>
                </CollapsibleContent>
            </Collapsible>
        </template>
    </PageContainer>
</template>
