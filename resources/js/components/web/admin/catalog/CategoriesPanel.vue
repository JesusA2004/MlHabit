<script setup lang="ts">
import { Link, router, useForm } from '@inertiajs/vue3';
import {
    ArrowDown,
    ArrowUp,
    ExternalLink,
    FolderOpen,
    Pencil,
    Plus,
    Trash2,
} from '@lucide/vue';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import ConfirmDialog from '@/components/ds/ConfirmDialog.vue';
import EmptyState from '@/components/ds/EmptyState.vue';
import FormSheet from '@/components/ds/FormSheet.vue';
import StatusBadge from '@/components/ds/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import categorias from '@/routes/admin/catalogo/categorias';
import productos from '@/routes/admin/catalogo/productos';
import type { MediaRef } from '@/types/web';
import MediaField from '../MediaField.vue';

export type CategoryRow = {
    id: number;
    name: string;
    slug: string;
    tagline: string | null;
    description: string | null;
    cover: MediaRef | null;
    isPublished: boolean;
    isFeatured: boolean;
    seoTitle: string | null;
    seoDescription: string | null;
    productsCount: number;
    sort: number;
};

const props = defineProps<{ categories: CategoryRow[]; canPublish: boolean }>();

const sheetOpen = ref(false);
const editing = ref<CategoryRow | null>(null);
const form = useForm({
    name: '',
    slug: '',
    tagline: '',
    description: '',
    cover_media_id: null as number | null,
    is_published: false,
    is_featured: false,
    seo_title: '',
    seo_description: '',
});

function create() {
    editing.value = null;
    form.reset();
    form.clearErrors();
    sheetOpen.value = true;
}

function edit(row: CategoryRow) {
    editing.value = row;
    Object.assign(form, {
        name: row.name,
        slug: row.slug,
        tagline: row.tagline ?? '',
        description: row.description ?? '',
        cover_media_id: row.cover?.id ?? null,
        is_published: row.isPublished,
        is_featured: row.isFeatured,
        seo_title: row.seoTitle ?? '',
        seo_description: row.seoDescription ?? '',
    });
    form.clearErrors();
    sheetOpen.value = true;
}

function submit() {
    const options = {
        preserveScroll: true,
        onSuccess: () => (sheetOpen.value = false),
    };
    if (editing.value)
        form.put(categorias.update(editing.value.id).url, options);
    else form.post(categorias.store().url, options);
}

function move(index: number, delta: number) {
    const ids = props.categories.map((c) => c.id);
    const target = index + delta;
    if (target < 0 || target >= ids.length) return;
    [ids[index], ids[target]] = [ids[target], ids[index]];
    router.put(categorias.orden().url, { ids }, { preserveScroll: true });
}

const removing = ref<CategoryRow | null>(null);
const removeOpen = ref(false);
function remove(): Promise<void> {
    return new Promise((resolve, reject) => {
        if (!removing.value) return resolve();
        router.delete(categorias.destroy(removing.value.id).url, {
            preserveScroll: true,
            onSuccess: () => resolve(),
            onError: () => reject(new Error('No se pudo eliminar.')),
        });
    });
}
</script>

<template>
    <div class="flex flex-col gap-4">
        <div class="flex justify-end">
            <Button @click="create"
                ><Plus class="size-4" /> Nueva categoría</Button
            >
        </div>

        <ul v-if="props.categories.length" class="grid gap-3">
            <li
                v-for="(row, index) in props.categories"
                :key="row.id"
                class="flex flex-col gap-3 rounded-xl border border-border bg-card p-3 sm:flex-row sm:items-center"
            >
                <div class="flex min-w-0 flex-1 items-center gap-3">
                    <div
                        class="size-16 shrink-0 overflow-hidden rounded-lg bg-muted"
                    >
                        <img
                            v-if="row.cover?.thumbnailUrl"
                            :src="row.cover.thumbnailUrl"
                            alt=""
                            class="h-full w-full object-cover"
                        />
                    </div>
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-medium">{{ row.name }}</span>
                            <StatusBadge
                                :tone="row.isPublished ? 'success' : 'warning'"
                                :label="
                                    row.isPublished
                                        ? 'Publicada'
                                        : 'Sin publicar'
                                "
                                size="sm"
                            />
                            <StatusBadge
                                v-if="row.isFeatured"
                                tone="brand"
                                label="Destacada"
                                size="sm"
                            />
                        </div>
                        <p class="text-sm text-muted-foreground">
                            /catalogo/{{ row.slug }} · {{ row.productsCount }}
                            {{
                                row.productsCount === 1
                                    ? 'producto'
                                    : 'productos'
                            }}
                        </p>
                    </div>
                </div>
                <div class="flex flex-wrap justify-end gap-1">
                    <Button
                        variant="ghost"
                        size="icon"
                        class="size-8"
                        :disabled="index === 0"
                        :aria-label="`Subir ${row.name}`"
                        @click="move(index, -1)"
                        ><ArrowUp class="size-4"
                    /></Button>
                    <Button
                        variant="ghost"
                        size="icon"
                        class="size-8"
                        :disabled="index === props.categories.length - 1"
                        :aria-label="`Bajar ${row.name}`"
                        @click="move(index, 1)"
                        ><ArrowDown class="size-4"
                    /></Button>
                    <Button
                        v-if="row.isPublished"
                        as="a"
                        :href="`/catalogo/${row.slug}`"
                        target="_blank"
                        rel="noopener"
                        variant="ghost"
                        size="icon"
                        class="size-8"
                        :aria-label="`Ver ${row.name} en el sitio`"
                        ><ExternalLink class="size-4"
                    /></Button>
                    <Button as-child variant="outline" size="sm">
                        <Link
                            :href="
                                productos.create({
                                    query: { categoria: row.id },
                                }).url
                            "
                            ><Plus class="size-4" /> Producto</Link
                        >
                    </Button>
                    <Button variant="outline" size="sm" @click="edit(row)"
                        ><Pencil class="size-4" /> Editar</Button
                    >
                    <Button
                        variant="ghost"
                        size="icon"
                        class="size-8 text-danger-ink"
                        :aria-label="`Eliminar ${row.name}`"
                        @click="((removing = row), (removeOpen = true))"
                        ><Trash2 class="size-4"
                    /></Button>
                </div>
            </li>
        </ul>
        <EmptyState
            v-else
            :icon="FolderOpen"
            title="Aún no hay categorías"
            description="Crea las líneas de tu catálogo: cocinas integrales, clósets, recámaras…"
        >
            <Button @click="create"
                ><Plus class="size-4" /> Nueva categoría</Button
            >
        </EmptyState>

        <FormSheet
            v-model:open="sheetOpen"
            :title="editing ? `Editar ${editing.name}` : 'Nueva categoría'"
            size="lg"
        >
            <form
                id="category-form"
                class="grid gap-4"
                @submit.prevent="submit"
            >
                <div class="grid gap-1.5">
                    <Label for="cat-name">Nombre</Label>
                    <Input id="cat-name" v-model="form.name" maxlength="120" />
                    <InputError :message="form.errors.name" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="cat-slug">URL</Label>
                    <div class="flex items-center gap-2">
                        <span class="text-sm text-muted-foreground"
                            >/catalogo/</span
                        >
                        <Input
                            id="cat-slug"
                            v-model="form.slug"
                            maxlength="140"
                            placeholder="se genera del nombre"
                        />
                    </div>
                    <InputError :message="form.errors.slug" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="cat-tagline">Frase corta</Label>
                    <Input
                        id="cat-tagline"
                        v-model="form.tagline"
                        maxlength="255"
                    />
                    <InputError :message="form.errors.tagline" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="cat-desc">Descripción</Label>
                    <Textarea
                        id="cat-desc"
                        v-model="form.description"
                        rows="4"
                        maxlength="2000"
                    />
                    <InputError :message="form.errors.description" />
                </div>
                <div class="grid gap-1.5">
                    <Label>Portada</Label>
                    <MediaField
                        v-model="form.cover_media_id"
                        :media="editing?.cover ?? null"
                        collection="catalogo/categorias"
                        :invalid="!!form.errors.cover_media_id"
                    />
                    <InputError :message="form.errors.cover_media_id" />
                </div>
                <label
                    class="flex items-center justify-between gap-3 rounded-lg border border-border p-3 text-sm"
                >
                    <span
                        >Publicada<span
                            v-if="!props.canPublish"
                            class="block text-xs text-muted-foreground"
                            >Necesitas permiso para publicar.</span
                        ></span
                    >
                    <Switch
                        v-model="form.is_published"
                        :disabled="!props.canPublish"
                    />
                </label>
                <label
                    class="flex items-center justify-between gap-3 rounded-lg border border-border p-3 text-sm"
                >
                    Destacada en la portada
                    <Switch v-model="form.is_featured" />
                </label>
                <fieldset
                    class="grid gap-3 rounded-lg border border-border p-4"
                >
                    <legend class="px-1 text-sm font-medium">
                        Buscadores (opcional)
                    </legend>
                    <div class="grid gap-1.5">
                        <Label for="cat-seo-title">Título SEO</Label>
                        <Input
                            id="cat-seo-title"
                            v-model="form.seo_title"
                            maxlength="120"
                        />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="cat-seo-desc">Descripción SEO</Label>
                        <Textarea
                            id="cat-seo-desc"
                            v-model="form.seo_description"
                            rows="2"
                            maxlength="320"
                        />
                    </div>
                </fieldset>
            </form>
            <template #footer>
                <Button variant="outline" @click="sheetOpen = false"
                    >Cancelar</Button
                >
                <Button
                    type="submit"
                    form="category-form"
                    :disabled="form.processing"
                    ><Spinner v-if="form.processing" /> Guardar</Button
                >
            </template>
        </FormSheet>

        <ConfirmDialog
            v-model:open="removeOpen"
            :title="`¿Eliminar ${removing?.name ?? 'la categoría'}?`"
            description="Solo se puede eliminar si no tiene productos."
            confirm-label="Eliminar"
            tone="destructive"
            :confirm="remove"
        />
    </div>
</template>
