<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ArrowLeft, ExternalLink, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import ConfirmDialog from '@/components/ds/ConfirmDialog.vue';
import PageContainer from '@/components/ds/PageContainer.vue';
import PageHeader from '@/components/ds/PageHeader.vue';
import SearchSelect from '@/components/ds/SearchSelect.vue';
import SectionCard from '@/components/ds/SectionCard.vue';
import StatusBadge from '@/components/ds/StatusBadge.vue';
import type { Tone } from '@/components/ds/tones';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { Spinner } from '@/components/ui/spinner';
import { Switch } from '@/components/ui/switch';
import {
    TagsInput,
    TagsInputInput,
    TagsInputItem,
    TagsInputItemDelete,
    TagsInputItemText,
} from '@/components/ui/tags-input';
import { Textarea } from '@/components/ui/textarea';
import EditableList from '@/components/web/admin/EditableList.vue';
import GalleryEditor from '@/components/web/admin/GalleryEditor.vue';
import MediaField from '@/components/web/admin/MediaField.vue';
import RichTextEditor from '@/components/web/admin/RichTextEditor.vue';
import catalogo from '@/routes/admin/catalogo';
import productos from '@/routes/admin/catalogo/productos';
import type {
    EditorialItem,
    GalleryEditorItem,
    MediaRef,
    ToneOption,
} from '@/types/web';

type ProductData = {
    id: number;
    productCategoryId: number;
    name: string;
    slug: string;
    summary: string | null;
    description: string | null;
    benefits: EditorialItem[];
    features: EditorialItem[];
    cover: MediaRef | null;
    gallery: GalleryEditorItem[];
    finishIds: number[];
    tags: string[];
    priceFrom: string | null;
    priceTo: string | null;
    showPrice: boolean;
    status: 'draft' | 'published' | 'archived';
    isFeatured: boolean;
    seoTitle: string | null;
    seoDescription: string | null;
    publishedAt: string | null;
    updatedAt: string | null;
    publicUrl: string | null;
    categoryPublished: boolean;
};

const props = defineProps<{
    product: ProductData | null;
    categories: { value: number; label: string; isPublished: boolean }[];
    finishes: {
        id: number;
        name: string;
        material: string | null;
        colorHex: string | null;
        isActive: boolean;
        media: MediaRef | null;
    }[];
    tagSuggestions: string[];
    statusOptions: ToneOption[];
    originOptions: ToneOption[];
    defaultCategoryId: number | null;
    can: { publish: boolean };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Panel', href: '/admin' },
            { title: 'Catálogo', href: '/admin/catalogo' },
            { title: 'Producto', href: '#' },
        ],
    },
});

const p = props.product;
const form = useForm({
    product_category_id: (p?.productCategoryId ??
        props.defaultCategoryId ??
        null) as number | null,
    name: p?.name ?? '',
    slug: p?.slug ?? '',
    summary: p?.summary ?? '',
    description: p?.description ?? '',
    benefits: (p?.benefits ?? []) as Record<string, string | null>[],
    features: (p?.features ?? []) as Record<string, string | null>[],
    cover_media_id: p?.cover?.id ?? (null as number | null),
    gallery: (p?.gallery ?? []) as GalleryEditorItem[],
    finish_ids: (p?.finishIds ?? []) as number[],
    tags: (p?.tags ?? []) as string[],
    price_from: p?.priceFrom ?? '',
    price_to: p?.priceTo ?? '',
    show_price: p?.showPrice ?? false,
    status: p?.status ?? 'draft',
    is_featured: p?.isFeatured ?? false,
    seo_title: p?.seoTitle ?? '',
    seo_description: p?.seoDescription ?? '',
});

const lockedPublished = computed(
    () => p?.status === 'published' && !props.can.publish,
);
const errors = computed(
    () => form.errors as Record<string, string | undefined>,
);
const listErrors = (key: string) =>
    Object.fromEntries(
        Object.entries(errors.value)
            .filter(([k]) => k.startsWith(`${key}.`))
            .map(([k, v]) => [k.slice(key.length + 1), v]),
    );

function toggleFinish(id: number, checked: boolean | 'indeterminate') {
    const set = new Set(form.finish_ids);
    if (checked === true) set.add(id);
    else set.delete(id);
    form.finish_ids = [...set];
}

function submit() {
    form.transform((data) => ({
        ...data,
        gallery: data.gallery.map((g) => ({
            id: g.id,
            origin: g.origin,
            caption: g.caption,
        })),
        price_from: data.price_from === '' ? null : data.price_from,
        price_to: data.price_to === '' ? null : data.price_to,
    }));
    if (p) form.put(productos.update(p.id).url, { preserveScroll: true });
    else form.post(productos.store().url);
}

const deleteOpen = ref(false);
function destroy(): Promise<void> {
    return new Promise((resolve, reject) => {
        if (!p) return resolve();
        router.delete(productos.destroy(p.id).url, {
            onSuccess: () => resolve(),
            onError: () => reject(new Error('No se pudo eliminar.')),
        });
    });
}

const statusTone = computed(
    () =>
        (props.statusOptions.find((s) => s.value === p?.status)?.tone ??
            'neutral') as Tone,
);
const statusLabel = computed(
    () => props.statusOptions.find((s) => s.value === p?.status)?.label ?? '',
);
</script>

<template>
    <Head :title="p ? p.name : 'Nuevo producto'" />

    <PageContainer>
        <PageHeader :title="p ? p.name : 'Nuevo producto'">
            <template #breadcrumbs>
                <Link
                    :href="catalogo.index().url"
                    class="inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
                    ><ArrowLeft class="size-4" /> Catálogo</Link
                >
            </template>
            <template v-if="p" #meta>
                <StatusBadge :tone="statusTone" :label="statusLabel" />
                <span
                    v-if="p.status === 'published' && !p.categoryPublished"
                    class="text-warning-ink"
                    >La categoría no está publicada: el producto no se ve en el
                    sitio.</span
                >
            </template>
            <template v-if="p?.publicUrl" #actions>
                <Button
                    as="a"
                    :href="p.publicUrl"
                    target="_blank"
                    rel="noopener"
                    variant="outline"
                    ><ExternalLink class="size-4" /> Ver en el sitio</Button
                >
            </template>
        </PageHeader>

        <p
            v-if="lockedPublished"
            class="rounded-lg bg-warning-soft p-3 text-sm text-warning-ink"
        >
            Este producto está publicado. Para modificarlo necesitas el permiso
            de publicar contenido.
        </p>

        <form class="grid gap-6 lg:grid-cols-12" @submit.prevent="submit">
            <div class="flex flex-col gap-6 lg:col-span-8">
                <SectionCard title="Información">
                    <div class="grid gap-5">
                        <div class="grid gap-1.5">
                            <Label for="p-name">Nombre</Label>
                            <Input
                                id="p-name"
                                v-model="form.name"
                                maxlength="160"
                            />
                            <InputError :message="form.errors.name" />
                        </div>
                        <div class="grid gap-1.5">
                            <Label for="p-summary">Resumen</Label>
                            <Textarea
                                id="p-summary"
                                v-model="form.summary"
                                rows="2"
                                maxlength="400"
                            />
                            <p class="text-xs text-muted-foreground">
                                Aparece en las tarjetas del catálogo.
                            </p>
                            <InputError :message="form.errors.summary" />
                        </div>
                        <div class="grid gap-1.5">
                            <Label for="p-description">Descripción</Label>
                            <RichTextEditor
                                id="p-description"
                                v-model="form.description"
                                placeholder="Describe el producto, sus usos y posibilidades…"
                            />
                            <InputError :message="form.errors.description" />
                        </div>
                    </div>
                </SectionCard>

                <SectionCard
                    title="Imágenes"
                    description="La portada aparece en tarjetas y en el encabezado. Indica en cada imagen de la galería si es un render conceptual o un proyecto real."
                >
                    <div class="grid gap-6">
                        <div class="grid gap-1.5">
                            <Label>Portada</Label>
                            <MediaField
                                v-model="form.cover_media_id"
                                :media="p?.cover ?? null"
                                collection="catalogo/productos"
                                :invalid="!!form.errors.cover_media_id"
                            />
                            <InputError :message="form.errors.cover_media_id" />
                        </div>
                        <div class="grid gap-1.5">
                            <Label>Galería</Label>
                            <GalleryEditor
                                v-model="form.gallery"
                                collection="catalogo/productos"
                            />
                            <InputError :message="form.errors.gallery" />
                        </div>
                    </div>
                </SectionCard>

                <SectionCard
                    title="Beneficios"
                    description="Razones cortas para elegir este producto."
                >
                    <EditableList
                        v-model="form.benefits"
                        :fields="[
                            { key: 'title', label: 'Beneficio', max: 120 },
                            {
                                key: 'body',
                                label: 'Detalle (opcional)',
                                kind: 'textarea',
                                max: 600,
                            },
                        ]"
                        add-label="Agregar beneficio"
                        :errors="listErrors('benefits')"
                    />
                </SectionCard>

                <SectionCard
                    title="Características"
                    description="Datos técnicos o de diseño (materiales, herrajes, medidas típicas…)."
                >
                    <EditableList
                        v-model="form.features"
                        :fields="[
                            { key: 'title', label: 'Característica', max: 120 },
                            {
                                key: 'body',
                                label: 'Valor o detalle',
                                kind: 'textarea',
                                max: 600,
                            },
                        ]"
                        add-label="Agregar característica"
                        :errors="listErrors('features')"
                    />
                </SectionCard>

                <SectionCard
                    title="Acabados"
                    description="Marca los acabados disponibles para este producto."
                >
                    <div
                        v-if="props.finishes.length"
                        class="grid gap-2 sm:grid-cols-2"
                    >
                        <label
                            v-for="finish in props.finishes"
                            :key="finish.id"
                            class="flex items-center gap-3 rounded-lg border border-border p-2.5 text-sm"
                            :class="!finish.isActive && 'opacity-60'"
                        >
                            <Checkbox
                                :model-value="
                                    form.finish_ids.includes(finish.id)
                                "
                                @update:model-value="
                                    (v) => toggleFinish(finish.id, v)
                                "
                            />
                            <span
                                class="size-7 shrink-0 overflow-hidden rounded-full ring-1 ring-black/10"
                                :style="
                                    finish.colorHex
                                        ? { backgroundColor: finish.colorHex }
                                        : undefined
                                "
                            >
                                <img
                                    v-if="finish.media?.thumbnailUrl"
                                    :src="finish.media.thumbnailUrl"
                                    alt=""
                                    class="h-full w-full object-cover"
                                />
                            </span>
                            <span class="min-w-0 truncate"
                                >{{ finish.name
                                }}<span
                                    v-if="finish.material"
                                    class="text-muted-foreground"
                                >
                                    · {{ finish.material }}</span
                                ></span
                            >
                        </label>
                    </div>
                    <p v-else class="text-sm text-muted-foreground">
                        Aún no hay acabados. Créalos en
                        <Link
                            :href="
                                catalogo.index({
                                    query: { pestana: 'acabados' },
                                }).url
                            "
                            class="text-primary underline"
                            >Catálogo → Acabados</Link
                        >.
                    </p>
                </SectionCard>
            </div>

            <div class="flex flex-col gap-6 lg:col-span-4">
                <SectionCard title="Publicación">
                    <div class="grid gap-4">
                        <RadioGroup
                            v-model="form.status"
                            class="grid gap-2"
                            aria-label="Estado"
                        >
                            <label
                                v-for="option in props.statusOptions"
                                :key="option.value"
                                class="flex items-center gap-3 rounded-lg border border-border p-3 text-sm"
                                :class="
                                    option.value === 'published' &&
                                    !props.can.publish &&
                                    'opacity-60'
                                "
                            >
                                <RadioGroupItem
                                    :value="option.value"
                                    :disabled="
                                        !props.can.publish &&
                                        (option.value === 'published' ||
                                            form.status === 'published')
                                    "
                                />
                                {{ option.label }}
                            </label>
                        </RadioGroup>
                        <p
                            v-if="!props.can.publish"
                            class="text-xs text-muted-foreground"
                        >
                            Para publicar necesitas el permiso de publicar
                            contenido.
                        </p>
                        <InputError :message="form.errors.status" />
                        <label
                            class="flex items-center justify-between gap-3 rounded-lg border border-border p-3 text-sm"
                        >
                            Destacado <Switch v-model="form.is_featured" />
                        </label>
                    </div>
                </SectionCard>

                <SectionCard title="Organización">
                    <div class="grid gap-4">
                        <div class="grid gap-1.5">
                            <Label for="p-category">Categoría</Label>
                            <SearchSelect
                                id="p-category"
                                v-model="form.product_category_id"
                                :options="props.categories"
                                placeholder="Elige una categoría"
                                :invalid="!!form.errors.product_category_id"
                            />
                            <InputError
                                :message="form.errors.product_category_id"
                            />
                        </div>
                        <div class="grid gap-1.5">
                            <Label for="p-slug">URL</Label>
                            <Input
                                id="p-slug"
                                v-model="form.slug"
                                maxlength="180"
                                placeholder="se genera del nombre"
                            />
                            <InputError :message="form.errors.slug" />
                        </div>
                        <div class="grid gap-1.5">
                            <Label for="p-tags">Etiquetas</Label>
                            <TagsInput id="p-tags" v-model="form.tags">
                                <TagsInputItem
                                    v-for="tag in form.tags"
                                    :key="tag"
                                    :value="tag"
                                >
                                    <TagsInputItemText />
                                    <TagsInputItemDelete />
                                </TagsInputItem>
                                <TagsInputInput
                                    placeholder="Escribe y presiona Enter"
                                    list="tag-suggestions"
                                />
                            </TagsInput>
                            <datalist id="tag-suggestions">
                                <option
                                    v-for="tag in props.tagSuggestions"
                                    :key="tag"
                                    :value="tag"
                                />
                            </datalist>
                        </div>
                    </div>
                </SectionCard>

                <SectionCard
                    title="Precio de referencia"
                    description="Opcional. Solo se muestra si activas la opción; el precio final siempre depende de la cotización."
                >
                    <div class="grid gap-4">
                        <div class="grid grid-cols-2 gap-3">
                            <div class="grid gap-1.5">
                                <Label for="p-from">Desde (MXN)</Label>
                                <Input
                                    id="p-from"
                                    v-model="form.price_from"
                                    inputmode="decimal"
                                    placeholder="0.00"
                                />
                            </div>
                            <div class="grid gap-1.5">
                                <Label for="p-to">Hasta (MXN)</Label>
                                <Input
                                    id="p-to"
                                    v-model="form.price_to"
                                    inputmode="decimal"
                                    placeholder="Opcional"
                                />
                            </div>
                        </div>
                        <InputError
                            :message="
                                form.errors.price_from || form.errors.price_to
                            "
                        />
                        <label
                            class="flex items-center justify-between gap-3 rounded-lg border border-border p-3 text-sm"
                        >
                            Mostrar precio en el sitio
                            <Switch v-model="form.show_price" />
                        </label>
                    </div>
                </SectionCard>

                <SectionCard title="Buscadores (opcional)">
                    <div class="grid gap-4">
                        <div class="grid gap-1.5">
                            <Label for="p-seo-title">Título SEO</Label>
                            <Input
                                id="p-seo-title"
                                v-model="form.seo_title"
                                maxlength="120"
                            />
                        </div>
                        <div class="grid gap-1.5">
                            <Label for="p-seo-desc">Descripción SEO</Label>
                            <Textarea
                                id="p-seo-desc"
                                v-model="form.seo_description"
                                rows="3"
                                maxlength="320"
                            />
                        </div>
                    </div>
                </SectionCard>

                <div class="flex flex-col gap-2">
                    <Button
                        type="submit"
                        size="lg"
                        :disabled="form.processing || lockedPublished"
                        ><Spinner v-if="form.processing" /> Guardar
                        producto</Button
                    >
                    <Button
                        v-if="p"
                        type="button"
                        variant="ghost"
                        class="text-danger-ink"
                        :disabled="lockedPublished"
                        @click="deleteOpen = true"
                        ><Trash2 class="size-4" /> Eliminar producto</Button
                    >
                </div>
            </div>
        </form>

        <ConfirmDialog
            v-model:open="deleteOpen"
            title="¿Eliminar este producto?"
            description="Dejará de mostrarse en el sitio y se quitará su galería. Las imágenes siguen en la biblioteca."
            confirm-label="Eliminar"
            tone="destructive"
            :confirm="destroy"
        />
    </PageContainer>
</template>
