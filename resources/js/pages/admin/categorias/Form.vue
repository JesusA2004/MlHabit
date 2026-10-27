<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Save } from '@lucide/vue';
import { computed } from 'vue';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';
import FormSection from '@/components/admin/FormSection.vue';
import InputError from '@/components/InputError.vue';
import SegmentedControl from '@/components/studio/controls/SegmentedControl.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import categorias from '@/routes/admin/categorias';
import type { BreadcrumbItem } from '@/types';

const props = defineProps<{
    category: {
        id: number;
        name: string;
        slug: string;
        description: string | null;
        color: string;
        icon: string | null;
        status: string;
        order: number;
    } | null;
}>();

const isEditing = props.category !== null;

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Panel', href: '/admin' },
            { title: 'Categorías', href: categorias.index() },
        ] as BreadcrumbItem[],
    },
});

const form = useForm({
    name: props.category?.name ?? '',
    slug: props.category?.slug ?? '',
    description: props.category?.description ?? '',
    color: props.category?.color ?? '#C7AA7D',
    icon: props.category?.icon ?? '',
    status: props.category?.status ?? 'active',
    order: props.category?.order ?? 0,
});

const statusOptions = [
    { value: 'active', label: 'Activa' },
    { value: 'inactive', label: 'Inactiva' },
];

/** Vista previa del slug que generará el servidor si se deja vacío. */
const slugPreview = computed(() => {
    if (form.slug) return form.slug;

    return form.name
        .toLowerCase()
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '')
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');
});

const validHex = computed(() => /^#[0-9A-Fa-f]{6}$/.test(form.color));

function submit() {
    if (isEditing && props.category) {
        form.put(categorias.update(props.category.id).url);
    } else {
        form.post(categorias.store().url);
    }
}
</script>

<template>
    <Head :title="isEditing ? 'Editar categoría' : 'Nueva categoría'" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6 lg:p-8">
        <AdminPageHeader
            eyebrow="Categorías"
            :title="isEditing ? `Editar «${category?.name}»` : 'Nueva categoría'"
            description="El nombre y el color identifican la categoría en todo el sitio: etiquetas, filtros y tarjetas de tips."
        >
            <template #actions>
                <Button as-child variant="ghost">
                    <Link :href="categorias.index()"><ArrowLeft class="size-4" /> Volver</Link>
                </Button>
                <Button type="submit" form="category-form" :disabled="form.processing" :aria-busy="form.processing">
                    <Save class="size-4" />
                    {{ isEditing ? 'Guardar cambios' : 'Crear categoría' }}
                </Button>
            </template>
        </AdminPageHeader>

        <form id="category-form" class="rounded-xl border bg-card px-5 md:px-8" @submit.prevent="submit">
            <FormSection title="Identidad" description="Cómo se llama la categoría y cómo aparece en el filtro de tips del sitio.">
                <div class="grid gap-4 md:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="name">Nombre</Label>
                        <Input id="name" v-model="form.name" required maxlength="255" placeholder="Ej.: Negocios" :aria-invalid="!!form.errors.name" />
                        <InputError :message="form.errors.name" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="slug">Slug <span class="font-normal text-muted-foreground">(opcional)</span></Label>
                        <Input id="slug" v-model="form.slug" placeholder="negocios" :aria-invalid="!!form.errors.slug" />
                        <p class="truncate text-xs text-muted-foreground">
                            Filtro: <span class="font-mono">/tips?categoria={{ slugPreview || '…' }}</span>
                            <template v-if="!form.slug"> · se genera desde el nombre</template>
                        </p>
                        <InputError :message="form.errors.slug" />
                    </div>
                </div>
                <div class="grid gap-2">
                    <Label for="description">Descripción</Label>
                    <Textarea
                        id="description"
                        v-model="form.description"
                        rows="3"
                        maxlength="2000"
                        placeholder="Ej.: Ideas prácticas para emprender y hacer crecer un negocio sin humo."
                    />
                    <p class="text-xs text-muted-foreground">Una o dos frases que resuman de qué trata la categoría.</p>
                    <InputError :message="form.errors.description" />
                </div>
            </FormSection>

            <FormSection title="Color editorial" description="Acompaña a la categoría en etiquetas y gráficas. Elige un tono que contraste con el fondo oscuro del sitio.">
                <div class="grid items-start gap-4 md:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
                    <div class="grid gap-2">
                        <Label for="color">Color</Label>
                        <div class="flex items-center gap-2">
                            <input
                                id="color-picker"
                                v-model="form.color"
                                type="color"
                                aria-label="Selector de color"
                                class="h-10 w-14 shrink-0 cursor-pointer rounded-md border bg-background p-1"
                            />
                            <Input id="color" v-model="form.color" class="font-mono uppercase" maxlength="7" :aria-invalid="!!form.errors.color || !validHex" />
                        </div>
                        <p class="text-xs text-muted-foreground">Formato hexadecimal, por ejemplo <span class="font-mono">#C7AA7D</span>.</p>
                        <InputError :message="form.errors.color" />
                    </div>

                    <div class="grid gap-2" aria-hidden="true">
                        <span class="text-sm font-medium">Vista previa</span>
                        <div class="flex flex-col gap-3 rounded-lg border bg-muted/40 p-4">
                            <span
                                class="inline-flex h-6 w-fit items-center gap-1.5 rounded-full border px-2.5 text-[11px] font-semibold"
                                :style="{ borderColor: validHex ? form.color : undefined, color: validHex ? form.color : undefined }"
                            >
                                <span class="size-1.5 rounded-full bg-current" />
                                {{ form.name || 'Nombre de categoría' }}
                            </span>
                            <div class="h-1.5 overflow-hidden rounded-full bg-muted">
                                <div class="h-full w-2/3 rounded-full" :style="{ background: validHex ? form.color : undefined }" />
                            </div>
                        </div>
                    </div>
                </div>
            </FormSection>

            <FormSection title="Visibilidad y orden" description="Las categorías inactivas no aparecen en el sitio ni se pueden asignar a nuevos tips.">
                <div class="grid gap-4 md:grid-cols-2">
                    <div class="grid gap-2">
                        <Label id="status-label">Estado</Label>
                        <SegmentedControl v-model="form.status" label="Estado" :options="statusOptions" class="[&>button]:min-h-9" />
                        <InputError :message="form.errors.status" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="order">Orden</Label>
                        <Input id="order" v-model.number="form.order" type="number" min="0" class="max-w-32" />
                        <p class="text-xs text-muted-foreground">Menor número = aparece primero. Ej.: 0, 1, 2…</p>
                        <InputError :message="form.errors.order" />
                    </div>
                </div>
            </FormSection>

            <div class="flex flex-col-reverse gap-2 border-t py-5 sm:flex-row sm:justify-end">
                <Button as-child variant="ghost">
                    <Link :href="categorias.index()">Cancelar</Link>
                </Button>
                <Button type="submit" :disabled="form.processing" :aria-busy="form.processing">
                    {{ isEditing ? 'Guardar cambios' : 'Crear categoría' }}
                </Button>
            </div>
        </form>
    </div>
</template>
