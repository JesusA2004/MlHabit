<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import {
    FolderOpen,
    Hammer,
    PackageX,
    Ruler,
    TriangleAlert,
} from '@lucide/vue';
import type { Component } from 'vue';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import { EmptyState, PageContainer } from '@/components/ds';
import InputError from '@/components/InputError.vue';
import EvidenceUploader from '@/components/operations/EvidenceUploader.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';
import incidencias from '@/routes/portal/incidencias';

const props = defineProps<{
    projects: { id: number; folio: string; name: string }[];
    categories: { value: string; label: string }[];
    preselectedProjectId: number | null;
    maxPhotoBytes: number;
}>();

const ICONS: Record<string, Component> = {
    damage: TriangleAlert,
    missing_part: PackageX,
    installation_error: Hammer,
    fabrication_error: Ruler,
};

const initialProject =
    props.projects.find((p) => p.id === props.preselectedProjectId)?.id ??
    (props.projects.length === 1 ? props.projects[0].id : null);

const form = useForm({
    project_id: initialProject ? String(initialProject) : '',
    category: '',
    title: '',
    description: '',
    media_ids: [] as number[],
});
const uploading = ref(false);

const ready = computed(
    () =>
        form.project_id !== '' &&
        form.category !== '' &&
        form.title.trim() !== '' &&
        form.description.trim() !== '' &&
        !uploading.value,
);

function submit() {
    form.transform((data) => ({
        ...data,
        project_id: Number(data.project_id),
    })).post(incidencias.store().url, {
        onError: (e) =>
            toast.error(Object.values(e)[0] ?? 'No pudimos enviar tu reporte.'),
    });
}
</script>

<template>
    <Head title="Reportar un problema" />

    <PageContainer width="narrow" class="flex flex-col gap-6 py-6 md:py-10">
        <header class="flex flex-col gap-2">
            <h1 class="font-display text-3xl font-semibold tracking-tight">
                Reportar un problema
            </h1>
            <p class="text-muted-foreground">
                Cuéntanos qué pasó y, si puedes, agrega fotos. Te contactaremos
                para darle solución.
            </p>
        </header>

        <EmptyState
            v-if="projects.length === 0"
            :icon="FolderOpen"
            title="Aún no tienes proyectos activos"
            description="Cuando tengas un proyecto con nosotros, podrás reportar aquí cualquier problema."
        >
            <template #action>
                <Button as-child variant="outline"
                    ><Link href="/portal">Ir a mi portal</Link></Button
                >
            </template>
        </EmptyState>

        <form v-else class="flex flex-col gap-6" @submit.prevent="submit">
            <div v-if="projects.length > 1" class="grid gap-1.5">
                <Label for="project">Proyecto</Label>
                <Select v-model="form.project_id">
                    <SelectTrigger id="project" class="h-12 w-full"
                        ><SelectValue placeholder="Elige tu proyecto"
                    /></SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="p in projects"
                            :key="p.id"
                            :value="String(p.id)"
                            >{{ p.name }} ({{ p.folio }})</SelectItem
                        >
                    </SelectContent>
                </Select>
                <InputError :message="form.errors.project_id" />
            </div>
            <p v-else class="text-sm text-muted-foreground">
                Proyecto:
                <span class="font-medium text-foreground">{{
                    projects[0].name
                }}</span>
            </p>

            <fieldset class="flex flex-col gap-2">
                <legend class="mb-2 text-sm font-medium">
                    ¿Qué tipo de problema es?
                </legend>
                <div class="grid gap-2 sm:grid-cols-2" role="radiogroup">
                    <button
                        v-for="c in categories"
                        :key="c.value"
                        type="button"
                        role="radio"
                        :aria-checked="form.category === c.value"
                        :class="
                            cn(
                                'flex min-h-16 items-center gap-3 rounded-2xl border-2 p-4 text-left text-sm font-medium transition-colors',
                                form.category === c.value
                                    ? 'border-primary bg-primary-soft'
                                    : 'hover:border-primary/40',
                            )
                        "
                        @click="form.category = c.value"
                    >
                        <component
                            :is="ICONS[c.value] ?? TriangleAlert"
                            class="size-5 shrink-0 text-primary"
                            aria-hidden="true"
                        />
                        {{ c.label }}
                    </button>
                </div>
                <InputError :message="form.errors.category" />
            </fieldset>

            <div class="grid gap-1.5">
                <Label for="title">Resumen en pocas palabras</Label>
                <Input
                    id="title"
                    v-model="form.title"
                    maxlength="200"
                    class="h-12"
                    placeholder="Ej. Cajón de la isla no cierra"
                />
                <InputError :message="form.errors.title" />
            </div>

            <div class="grid gap-1.5">
                <Label for="description">¿Qué pasó?</Label>
                <Textarea
                    id="description"
                    v-model="form.description"
                    rows="5"
                    placeholder="Dónde está el problema, desde cuándo y cualquier detalle que nos ayude."
                />
                <InputError :message="form.errors.description" />
            </div>

            <div class="grid gap-1.5">
                <Label>Fotos o video (opcional)</Label>
                <EvidenceUploader
                    v-model="form.media_ids"
                    :max-size="maxPhotoBytes"
                    :max-files="10"
                    collection="privado/incidencias"
                    @busy="uploading = $event"
                />
                <InputError :message="form.errors.media_ids" />
            </div>

            <Button
                type="submit"
                class="h-12 text-base"
                :disabled="!ready || form.processing"
            >
                {{ form.processing ? 'Enviando…' : 'Enviar reporte' }}
            </Button>
        </form>
    </PageContainer>
</template>
