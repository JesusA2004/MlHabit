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
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { Spinner } from '@/components/ui/spinner';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import GalleryEditor from '@/components/web/admin/GalleryEditor.vue';
import MediaField from '@/components/web/admin/MediaField.vue';
import RichTextEditor from '@/components/web/admin/RichTextEditor.vue';
import inspiracion from '@/routes/admin/inspiracion';
import type {
    GalleryEditorItem,
    MediaRef,
    Option,
    ToneOption,
} from '@/types/web';

type ProjectData = {
    id: number;
    productCategoryId: number | null;
    title: string;
    slug: string;
    summary: string | null;
    story: string | null;
    locationLabel: string | null;
    origin: 'render' | 'real_photo';
    cover: MediaRef | null;
    gallery: GalleryEditorItem[];
    status: 'draft' | 'published' | 'archived';
    isFeatured: boolean;
    seoTitle: string | null;
    seoDescription: string | null;
    publicUrl: string | null;
    updatedAt: string | null;
};

const props = defineProps<{
    project: ProjectData | null;
    categories: Option<number>[];
    statusOptions: ToneOption[];
    originOptions: ToneOption[];
    can: { publish: boolean };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Panel', href: '/admin' },
            { title: 'Inspiración', href: '/admin/inspiracion' },
            { title: 'Proyecto', href: '#' },
        ],
    },
});

const p = props.project;
const form = useForm({
    product_category_id: (p?.productCategoryId ?? null) as number | null,
    title: p?.title ?? '',
    slug: p?.slug ?? '',
    summary: p?.summary ?? '',
    story: p?.story ?? '',
    location_label: p?.locationLabel ?? '',
    origin: p?.origin ?? 'render',
    cover_media_id: p?.cover?.id ?? (null as number | null),
    gallery: (p?.gallery ?? []) as GalleryEditorItem[],
    status: p?.status ?? 'draft',
    is_featured: p?.isFeatured ?? false,
    seo_title: p?.seoTitle ?? '',
    seo_description: p?.seoDescription ?? '',
});

const locked = computed(() => p?.status === 'published' && !props.can.publish);

function submit() {
    form.transform((data) => ({
        ...data,
        gallery: data.gallery.map((g) => ({
            id: g.id,
            origin: g.origin,
            caption: g.caption,
        })),
    }));
    if (p) form.put(inspiracion.update(p.id).url, { preserveScroll: true });
    else form.post(inspiracion.store().url);
}

const deleteOpen = ref(false);
function destroy(): Promise<void> {
    return new Promise((resolve, reject) => {
        if (!p) return resolve();
        router.delete(inspiracion.destroy(p.id).url, {
            onSuccess: () => resolve(),
            onError: () => reject(new Error('No se pudo eliminar.')),
        });
    });
}
</script>

<template>
    <Head :title="p ? p.title : 'Nuevo proyecto'" />

    <PageContainer>
        <PageHeader :title="p ? p.title : 'Nuevo proyecto de inspiración'">
            <template #breadcrumbs>
                <Link
                    :href="inspiracion.index().url"
                    class="inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
                    ><ArrowLeft class="size-4" /> Inspiración</Link
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
            v-if="locked"
            class="rounded-lg bg-warning-soft p-3 text-sm text-warning-ink"
        >
            Este proyecto está publicado. Para modificarlo necesitas el permiso
            de publicar contenido.
        </p>

        <form class="grid gap-6 lg:grid-cols-12" @submit.prevent="submit">
            <div class="flex flex-col gap-6 lg:col-span-8">
                <SectionCard
                    title="Origen de las imágenes"
                    description="Obligatorio. El sitio muestra esta etiqueta en el proyecto y en cada imagen."
                >
                    <RadioGroup
                        v-model="form.origin"
                        class="grid gap-3 sm:grid-cols-2"
                        aria-label="Origen"
                    >
                        <label
                            class="flex items-start gap-3 rounded-lg border border-border p-4 text-sm has-[[data-state=checked]]:border-primary has-[[data-state=checked]]:bg-primary-soft/40"
                        >
                            <RadioGroupItem value="real_photo" class="mt-0.5" />
                            <span
                                ><span class="font-medium">Proyecto real</span
                                ><span class="block text-muted-foreground"
                                    >Fotos de un trabajo entregado, con
                                    autorización del cliente.</span
                                ></span
                            >
                        </label>
                        <label
                            class="flex items-start gap-3 rounded-lg border border-border p-4 text-sm has-[[data-state=checked]]:border-primary has-[[data-state=checked]]:bg-primary-soft/40"
                        >
                            <RadioGroupItem value="render" class="mt-0.5" />
                            <span
                                ><span class="font-medium"
                                    >Render conceptual</span
                                ><span class="block text-muted-foreground"
                                    >Propuesta de diseño; no es un trabajo
                                    entregado.</span
                                ></span
                            >
                        </label>
                    </RadioGroup>
                    <InputError :message="form.errors.origin" />
                </SectionCard>

                <SectionCard title="Contenido">
                    <div class="grid gap-5">
                        <div class="grid gap-1.5">
                            <Label for="s-title">Título</Label>
                            <Input
                                id="s-title"
                                v-model="form.title"
                                maxlength="160"
                            />
                            <InputError :message="form.errors.title" />
                        </div>
                        <div class="grid gap-1.5">
                            <Label for="s-summary">Resumen</Label>
                            <Textarea
                                id="s-summary"
                                v-model="form.summary"
                                rows="2"
                                maxlength="400"
                            />
                            <InputError :message="form.errors.summary" />
                        </div>
                        <div class="grid gap-1.5">
                            <Label for="s-story">Historia del proyecto</Label>
                            <RichTextEditor
                                id="s-story"
                                v-model="form.story"
                                placeholder="¿Qué necesitaba el cliente? ¿Qué propusimos?"
                            />
                            <InputError :message="form.errors.story" />
                        </div>
                        <div class="grid gap-1.5">
                            <Label for="s-location">Ubicación (opcional)</Label>
                            <Input
                                id="s-location"
                                v-model="form.location_label"
                                maxlength="120"
                                placeholder="Solo si el cliente lo autorizó"
                            />
                            <p class="text-xs text-muted-foreground">
                                No escribas direcciones exactas. Déjalo vacío si
                                el cliente no lo autorizó.
                            </p>
                        </div>
                    </div>
                </SectionCard>

                <SectionCard title="Imágenes">
                    <div class="grid gap-6">
                        <div class="grid gap-1.5">
                            <Label>Portada</Label>
                            <MediaField
                                v-model="form.cover_media_id"
                                :media="p?.cover ?? null"
                                collection="inspiracion/proyectos"
                                :invalid="!!form.errors.cover_media_id"
                            />
                            <InputError :message="form.errors.cover_media_id" />
                        </div>
                        <div class="grid gap-1.5">
                            <Label>Galería</Label>
                            <GalleryEditor
                                v-model="form.gallery"
                                collection="inspiracion/proyectos"
                            />
                        </div>
                    </div>
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
                        <label
                            class="flex items-center justify-between gap-3 rounded-lg border border-border p-3 text-sm"
                        >
                            Destacado en la portada
                            <Switch v-model="form.is_featured" />
                        </label>
                    </div>
                </SectionCard>

                <SectionCard title="Organización">
                    <div class="grid gap-4">
                        <div class="grid gap-1.5">
                            <Label for="s-category">Categoría (opcional)</Label>
                            <SearchSelect
                                id="s-category"
                                v-model="form.product_category_id"
                                :options="props.categories"
                                clearable
                                placeholder="Sin categoría"
                            />
                        </div>
                        <div class="grid gap-1.5">
                            <Label for="s-slug">URL</Label>
                            <Input
                                id="s-slug"
                                v-model="form.slug"
                                maxlength="180"
                                placeholder="se genera del título"
                            />
                            <InputError :message="form.errors.slug" />
                        </div>
                    </div>
                </SectionCard>

                <SectionCard title="Buscadores (opcional)">
                    <div class="grid gap-4">
                        <div class="grid gap-1.5">
                            <Label for="s-seo-title">Título SEO</Label>
                            <Input
                                id="s-seo-title"
                                v-model="form.seo_title"
                                maxlength="120"
                            />
                        </div>
                        <div class="grid gap-1.5">
                            <Label for="s-seo-desc">Descripción SEO</Label>
                            <Textarea
                                id="s-seo-desc"
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
                        :disabled="form.processing || locked"
                        ><Spinner v-if="form.processing" /> Guardar
                        proyecto</Button
                    >
                    <Button
                        v-if="p"
                        type="button"
                        variant="ghost"
                        class="text-danger-ink"
                        :disabled="locked"
                        @click="deleteOpen = true"
                        ><Trash2 class="size-4" /> Eliminar proyecto</Button
                    >
                </div>
            </div>
        </form>

        <ConfirmDialog
            v-model:open="deleteOpen"
            title="¿Eliminar este proyecto?"
            description="Dejará de mostrarse en el sitio. Las imágenes siguen en la biblioteca."
            confirm-label="Eliminar"
            tone="destructive"
            :confirm="destroy"
        />
    </PageContainer>
</template>
