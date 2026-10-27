<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ArrowLeft, CalendarDays, ExternalLink, ImagePlus, Milestone, PenTool, Pencil, Plus, Save, Trash2, X } from '@lucide/vue';
import { computed, ref } from 'vue';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';
import EmptyState from '@/components/admin/EmptyState.vue';
import FormSection from '@/components/admin/FormSection.vue';
import MediaPickerDialog, { type PickedMedia } from '@/components/admin/MediaPickerDialog.vue';
import PostEditor from '@/components/admin/PostEditor.vue';
import InputError from '@/components/InputError.vue';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import paginas from '@/routes/admin/paginas';
import paginasEntradas from '@/routes/admin/paginas-entradas';
import type { BreadcrumbItem, TiptapContent } from '@/types';

interface PageEntryRow {
    id: number;
    title: string;
    entry_date: string | null;
    body: TiptapContent | null;
    is_visible: boolean;
    order: number;
    cover: { id: number; url: string | null } | null;
}

const props = defineProps<{
    page: {
        key: string;
        title: string;
        intro: string | null;
        body: TiptapContent | null;
        cover: { id: number; url: string | null } | null;
        seo_title: string | null;
        seo_description: string | null;
    };
    label: string;
    entries?: PageEntryRow[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Panel', href: '/admin' },
            { title: 'Páginas', href: paginas.index() },
        ] as BreadcrumbItem[],
    },
});

const STUDIO_HREF = '/admin/studio';
const PUBLIC_PATHS: Record<string, string> = {
    'mi-historia': '/mi-historia',
    'mi-proceso': '/mi-proceso',
    'aviso-privacidad': '/aviso-de-privacidad',
};
const publicPath = computed(() => PUBLIC_PATHS[props.page.key] ?? null);

/** Acciones de fila: 36 px con ratón, 44 px en pantallas táctiles. */
const rowAction = 'size-9 pointer-coarse:size-11';

const form = useForm({
    title: props.page.title,
    intro: props.page.intro ?? '',
    body: props.page.body,
    cover_media_id: props.page.cover?.id ?? null,
    seo_title: props.page.seo_title ?? '',
    seo_description: props.page.seo_description ?? '',
});

const coverUrl = ref(props.page.cover?.url ?? null);
const coverPickerOpen = ref(false);

function onCoverSelected(media: PickedMedia) {
    form.cover_media_id = media.id;
    coverUrl.value = media.url;
}

function removeCover() {
    form.cover_media_id = null;
    coverUrl.value = null;
}

function save() {
    form.put(paginas.update(props.page.key).url, { preserveScroll: true });
}

// Vista previa del resultado en buscadores.
const seoPreviewTitle = computed(() => form.seo_title || form.title || props.label);
const seoPreviewDescription = computed(() => form.seo_description || form.intro || 'Agrega una meta descripción para controlar el texto que muestran los buscadores.');

// --- Entradas de Mi Proceso ---
const entryForm = useForm({ title: '', entry_date: '', is_visible: true, order: 0 });
const addingEntry = ref(false);

function openAddEntry() {
    entryForm.reset();
    entryForm.clearErrors();
    entryForm.order = props.entries?.length ?? 0;
    addingEntry.value = true;
}

function addEntry() {
    entryForm.post(paginasEntradas.store().url, {
        preserveScroll: true,
        onSuccess: () => {
            entryForm.reset();
            addingEntry.value = false;
        },
    });
}

function toggleEntryVisible(entry: PageEntryRow) {
    router.put(
        paginasEntradas.update(entry.id).url,
        {
            title: entry.title,
            entry_date: entry.entry_date,
            body: entry.body,
            is_visible: !entry.is_visible,
            order: entry.order,
        },
        { preserveScroll: true },
    );
}

const entryToDelete = ref<PageEntryRow | null>(null);

function destroyEntry() {
    if (!entryToDelete.value) return;
    router.delete(paginasEntradas.destroy(entryToDelete.value.id).url, { preserveScroll: true });
    entryToDelete.value = null;
}

// --- Edición completa de una entrada (título, fecha, portada, contenido) ---
const editingEntry = ref<PageEntryRow | null>(null);
const entryEditForm = useForm<{ title: string; entry_date: string; body: TiptapContent | null; cover_media_id: number | null; is_visible: boolean; order: number }>({
    title: '',
    entry_date: '',
    body: null,
    cover_media_id: null,
    is_visible: true,
    order: 0,
});
const entryCoverUrl = ref<string | null>(null);
const entryCoverPickerOpen = ref(false);

function openEntryEditor(entry: PageEntryRow) {
    editingEntry.value = entry;
    entryEditForm.clearErrors();
    entryEditForm.title = entry.title;
    entryEditForm.entry_date = entry.entry_date ?? '';
    entryEditForm.body = entry.body;
    entryEditForm.cover_media_id = entry.cover?.id ?? null;
    entryEditForm.is_visible = entry.is_visible;
    entryEditForm.order = entry.order;
    entryCoverUrl.value = entry.cover?.url ?? null;
}

function onEntryCoverSelected(media: PickedMedia) {
    entryEditForm.cover_media_id = media.id;
    entryCoverUrl.value = media.url;
}

function removeEntryCover() {
    entryEditForm.cover_media_id = null;
    entryCoverUrl.value = null;
}

function saveEntryEdit() {
    if (!editingEntry.value) return;
    entryEditForm.put(paginasEntradas.update(editingEntry.value.id).url, {
        preserveScroll: true,
        onSuccess: () => (editingEntry.value = null),
    });
}

function fmtDate(date: string | null): string {
    if (!date) return 'Sin fecha';

    return new Intl.DateTimeFormat('es-MX', { dateStyle: 'long' }).format(new Date(`${date}T12:00:00`));
}

const visibleCount = computed(() => props.entries?.filter((e) => e.is_visible).length ?? 0);
</script>

<template>
    <Head :title="label" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6 lg:p-8">
        <AdminPageHeader eyebrow="Páginas" :title="label" description="Textos, fotografía y SEO de la página. La composición visual se diseña en Studio.">
            <template #actions>
                <Button as-child variant="ghost">
                    <Link :href="paginas.index()"><ArrowLeft class="size-4" /> Páginas</Link>
                </Button>
                <Button v-if="publicPath" as-child variant="outline">
                    <a :href="publicPath" target="_blank" rel="noopener"><ExternalLink class="size-4" /> Ver página</a>
                </Button>
                <Button type="submit" form="page-form" :disabled="form.processing" :aria-busy="form.processing">
                    <Save class="size-4" /> Guardar cambios
                </Button>
            </template>
        </AdminPageHeader>

        <!-- Studio: dónde vive la composición -->
        <aside class="flex flex-col gap-3 rounded-xl border border-brand/30 bg-brand/5 p-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-start gap-3">
                <span class="grid size-10 shrink-0 place-items-center rounded-lg bg-brand/15 text-brand"><PenTool class="size-5" /></span>
                <div>
                    <p class="text-sm font-semibold">El texto se escribe aquí; el diseño, en Studio</p>
                    <p class="mt-0.5 text-sm text-muted-foreground">
                        Studio muestra el título, la introducción, la fotografía y el contenido de esta página por referencia. Lo que guardes aquí aparece allá sin copiar nada.
                    </p>
                </div>
            </div>
            <Button as-child variant="outline" class="shrink-0 pointer-coarse:min-h-11">
                <Link :href="STUDIO_HREF"><PenTool class="size-4" /> Diseñar en Studio</Link>
            </Button>
        </aside>

        <form id="page-form" class="rounded-xl border bg-card px-5 md:px-8" @submit.prevent="save">
            <FormSection title="Encabezado" description="Lo primero que lee la gente. Un título corto y una introducción de una o dos frases.">
                <div class="grid gap-2">
                    <Label for="title">Título</Label>
                    <Input id="title" v-model="form.title" required maxlength="255" placeholder="Ej.: Mi historia" :aria-invalid="!!form.errors.title" />
                    <InputError :message="form.errors.title" />
                </div>
                <div class="grid gap-2">
                    <Label for="intro">Introducción</Label>
                    <Textarea
                        id="intro"
                        v-model="form.intro"
                        rows="3"
                        maxlength="2000"
                        placeholder="Ej.: De vender en la calle a dirigir mi propio negocio: esto es lo que aprendí en el camino."
                    />
                    <InputError :message="form.errors.intro" />
                </div>
            </FormSection>

            <FormSection title="Fotografía principal" description="Se usa como portada de la página. Recomendado: horizontal, al menos 1600 px de ancho.">
                <div class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-end">
                    <button
                        type="button"
                        class="group relative flex aspect-video w-full items-center justify-center overflow-hidden rounded-lg border border-dashed bg-muted/40 transition-colors hover:border-brand focus-visible:ring-3 focus-visible:ring-ring/50 focus-visible:outline-none"
                        :aria-label="coverUrl ? 'Cambiar fotografía principal' : 'Elegir fotografía principal'"
                        @click="coverPickerOpen = true"
                    >
                        <img v-if="coverUrl" :src="coverUrl" alt="" class="size-full object-cover" />
                        <span v-else class="flex flex-col items-center gap-2 text-sm text-muted-foreground">
                            <ImagePlus class="size-6" />
                            Elegir de la biblioteca
                        </span>
                    </button>
                    <div v-if="coverUrl" class="flex gap-2 sm:flex-col">
                        <Button type="button" variant="outline" class="pointer-coarse:min-h-11" @click="coverPickerOpen = true"><ImagePlus class="size-4" /> Cambiar</Button>
                        <Button type="button" variant="ghost" class="pointer-coarse:min-h-11" @click="removeCover"><X class="size-4" /> Quitar</Button>
                    </div>
                </div>
                <InputError :message="form.errors.cover_media_id" />
            </FormSection>

            <FormSection title="Contenido completo" description="El cuerpo de la página. Usa encabezados para dividir ideas; puedes insertar imágenes, videos y citas.">
                <PostEditor v-model:content="form.body" />
                <InputError :message="form.errors.body" />
            </FormSection>

            <FormSection title="SEO" description="Cómo aparece la página en Google y al compartirla. Si lo dejas vacío, se usan el título y la introducción.">
                <div class="grid gap-2">
                    <div class="flex items-center justify-between">
                        <Label for="seo_title">Título SEO</Label>
                        <span class="text-xs text-muted-foreground tabular-nums">{{ form.seo_title.length }}/60 recomendado</span>
                    </div>
                    <Input id="seo_title" v-model="form.seo_title" maxlength="255" :placeholder="form.title" />
                    <InputError :message="form.errors.seo_title" />
                </div>
                <div class="grid gap-2">
                    <div class="flex items-center justify-between">
                        <Label for="seo_description">Meta descripción</Label>
                        <span class="text-xs text-muted-foreground tabular-nums">{{ form.seo_description.length }}/160 recomendado</span>
                    </div>
                    <Textarea id="seo_description" v-model="form.seo_description" rows="2" maxlength="500" placeholder="Ej.: La historia de Zuriel Ávila: negocios, hábitos y vida, sin tapujos." />
                    <InputError :message="form.errors.seo_description" />
                </div>
                <div class="rounded-lg border bg-muted/30 p-4" aria-label="Vista previa en buscadores">
                    <p class="text-xs text-muted-foreground">Vista previa en buscadores</p>
                    <p class="mt-2 truncate text-base font-medium text-brand">{{ seoPreviewTitle }}</p>
                    <p class="truncate font-mono text-xs text-muted-foreground">mrinsight.mx{{ publicPath ?? '' }}</p>
                    <p class="mt-1 line-clamp-2 text-sm text-muted-foreground">{{ seoPreviewDescription }}</p>
                </div>
            </FormSection>

            <div class="sticky bottom-0 z-10 -mx-5 flex items-center justify-between gap-3 border-t bg-card/95 px-5 py-3 backdrop-blur md:-mx-8 md:px-8">
                <p class="text-xs text-muted-foreground" role="status">
                    <template v-if="form.processing">Guardando…</template>
                    <template v-else-if="form.isDirty"><span class="mr-1.5 inline-block size-2 rounded-full bg-warning align-middle" />Cambios sin guardar</template>
                    <template v-else-if="form.recentlySuccessful">Guardado</template>
                    <template v-else>Sin cambios pendientes</template>
                </p>
                <Button type="submit" :disabled="form.processing" :aria-busy="form.processing"><Save class="size-4" /> Guardar cambios</Button>
            </div>
        </form>

        <!-- Línea de tiempo de Mi proceso -->
        <section v-if="entries" class="flex flex-col gap-4 rounded-xl border bg-card p-5 md:p-8" aria-labelledby="entries-title">
            <header class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h2 id="entries-title" class="text-base font-semibold">Entradas del proceso</h2>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Cada entrada es un momento de la línea de tiempo. {{ entries.length }} en total · {{ visibleCount }} visibles en el sitio.
                    </p>
                </div>
                <Button class="pointer-coarse:min-h-11" @click="openAddEntry"><Plus class="size-4" /> Agregar entrada</Button>
            </header>

            <EmptyState
                v-if="entries.length === 0"
                :icon="Milestone"
                title="Sin entradas todavía"
                description="Agrega el primer momento de tu proceso. Ej.: «Abrí mi primer local» con su fecha y una fotografía."
            >
                <Button @click="openAddEntry"><Plus class="size-4" /> Agregar la primera</Button>
            </EmptyState>

            <ol v-else class="relative flex flex-col">
                <li v-for="entry in entries" :key="entry.id" class="relative flex gap-4 pb-4 pl-6 last:pb-0">
                    <span class="absolute top-0 bottom-0 left-[7px] w-px bg-border" aria-hidden="true" />
                    <span
                        class="absolute top-5 left-0 size-[15px] rounded-full border-2 bg-card"
                        :class="entry.is_visible ? 'border-brand' : 'border-muted-foreground/40'"
                        aria-hidden="true"
                    />
                    <div class="flex min-w-0 flex-1 flex-col gap-3 rounded-lg border p-3 sm:flex-row sm:items-center" :class="!entry.is_visible && 'bg-muted/30'">
                        <button
                            type="button"
                            class="flex min-w-0 flex-1 items-center gap-3 rounded-md text-left focus-visible:ring-3 focus-visible:ring-ring/50 focus-visible:outline-none"
                            :aria-label="`Editar entrada ${entry.title}`"
                            @click="openEntryEditor(entry)"
                        >
                            <span class="size-16 shrink-0 overflow-hidden rounded-md bg-muted">
                                <img v-if="entry.cover?.url" :src="entry.cover.url" class="size-full object-cover" alt="" loading="lazy" />
                                <span v-else class="grid size-full place-items-center text-muted-foreground"><ImagePlus class="size-4" /></span>
                            </span>
                            <span class="min-w-0">
                                <span class="block truncate text-sm font-medium" :class="!entry.is_visible && 'text-muted-foreground'">{{ entry.title }}</span>
                                <span class="mt-0.5 flex items-center gap-1.5 text-xs text-muted-foreground"><CalendarDays class="size-3.5" /> {{ fmtDate(entry.entry_date) }}</span>
                            </span>
                        </button>
                        <div class="flex items-center justify-end gap-1">
                            <label :for="`entry-visible-${entry.id}`" class="flex min-h-11 cursor-pointer items-center gap-2 rounded-md px-2 text-xs text-muted-foreground">
                                <Switch :id="`entry-visible-${entry.id}`" :model-value="entry.is_visible" @update:model-value="toggleEntryVisible(entry)" />
                                {{ entry.is_visible ? 'Visible' : 'Oculta' }}
                            </label>
                            <Button variant="ghost" size="icon" :class="rowAction" :aria-label="`Editar ${entry.title}`" @click="openEntryEditor(entry)">
                                <Pencil class="size-4" />
                            </Button>
                            <Button variant="ghost" size="icon" :class="rowAction" :aria-label="`Eliminar ${entry.title}`" @click="entryToDelete = entry">
                                <Trash2 class="size-4" />
                            </Button>
                        </div>
                    </div>
                </li>
            </ol>
        </section>
    </div>

    <MediaPickerDialog v-model:open="coverPickerOpen" type="image" @select="onCoverSelected" />

    <!-- Nueva entrada -->
    <Dialog v-model:open="addingEntry">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Nueva entrada</DialogTitle>
                <DialogDescription>Empieza con el título y la fecha; después podrás agregar fotografía y contenido.</DialogDescription>
            </DialogHeader>
            <form class="flex flex-col gap-4" @submit.prevent="addEntry">
                <div class="grid gap-2">
                    <Label for="new-entry-title">Título</Label>
                    <Input id="new-entry-title" v-model="entryForm.title" required maxlength="255" placeholder="Ej.: Abrí mi primer local" />
                    <InputError :message="entryForm.errors.title" />
                </div>
                <div class="grid gap-2">
                    <Label for="new-entry-date">Fecha <span class="font-normal text-muted-foreground">(opcional)</span></Label>
                    <Input id="new-entry-date" v-model="entryForm.entry_date" type="date" />
                    <InputError :message="entryForm.errors.entry_date" />
                </div>
                <label for="new-entry-visible" class="flex min-h-11 cursor-pointer items-center gap-3 text-sm">
                    <Switch id="new-entry-visible" :model-value="entryForm.is_visible" @update:model-value="(v: boolean) => (entryForm.is_visible = v)" />
                    Visible en el sitio público
                </label>
                <DialogFooter>
                    <Button type="button" variant="ghost" @click="addingEntry = false">Cancelar</Button>
                    <Button type="submit" :disabled="entryForm.processing" :aria-busy="entryForm.processing">Guardar entrada</Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <!-- Editar entrada -->
    <Dialog :open="editingEntry !== null" @update:open="(v: boolean) => !v && (editingEntry = null)">
        <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-3xl">
            <DialogHeader>
                <DialogTitle>Editar entrada</DialogTitle>
                <DialogDescription>{{ editingEntry?.title }}</DialogDescription>
            </DialogHeader>
            <form class="flex flex-col gap-5" @submit.prevent="saveEntryEdit">
                <div class="grid gap-5 md:grid-cols-[minmax(0,1fr)_220px]">
                    <div class="flex flex-col gap-4">
                        <div class="grid gap-2">
                            <Label for="edit-entry-title">Título</Label>
                            <Input id="edit-entry-title" v-model="entryEditForm.title" required maxlength="255" />
                            <InputError :message="entryEditForm.errors.title" />
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="edit-entry-date">Fecha</Label>
                                <Input id="edit-entry-date" v-model="entryEditForm.entry_date" type="date" />
                                <InputError :message="entryEditForm.errors.entry_date" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="edit-entry-order">Posición</Label>
                                <Input id="edit-entry-order" v-model.number="entryEditForm.order" type="number" min="0" />
                                <InputError :message="entryEditForm.errors.order" />
                            </div>
                        </div>
                        <label for="edit-entry-visible" class="flex min-h-11 cursor-pointer items-center gap-3 text-sm">
                            <Switch id="edit-entry-visible" :model-value="entryEditForm.is_visible" @update:model-value="(v: boolean) => (entryEditForm.is_visible = v)" />
                            Visible en el sitio público
                        </label>
                    </div>
                    <div class="grid content-start gap-2">
                        <span class="text-sm font-medium">Fotografía</span>
                        <button
                            type="button"
                            class="flex aspect-[4/3] w-full items-center justify-center overflow-hidden rounded-lg border border-dashed bg-muted/40 transition-colors hover:border-brand focus-visible:ring-3 focus-visible:ring-ring/50 focus-visible:outline-none"
                            :aria-label="entryCoverUrl ? 'Cambiar fotografía de la entrada' : 'Elegir fotografía de la entrada'"
                            @click="entryCoverPickerOpen = true"
                        >
                            <img v-if="entryCoverUrl" :src="entryCoverUrl" alt="" class="size-full object-cover" />
                            <span v-else class="flex flex-col items-center gap-2 text-sm text-muted-foreground">
                                <ImagePlus class="size-6" />
                                Elegir fotografía
                            </span>
                        </button>
                        <Button v-if="entryCoverUrl" type="button" variant="ghost" size="sm" class="pointer-coarse:min-h-11" @click="removeEntryCover">
                            <X class="size-4" /> Quitar fotografía
                        </Button>
                        <InputError :message="entryEditForm.errors.cover_media_id" />
                    </div>
                </div>
                <div class="grid gap-2">
                    <span class="text-sm font-medium">Contenido</span>
                    <p class="text-xs text-muted-foreground">Encabezados, párrafos, imágenes, videos y citas.</p>
                    <PostEditor v-model:content="entryEditForm.body" />
                    <InputError :message="entryEditForm.errors.body" />
                </div>
                <DialogFooter>
                    <Button type="button" variant="ghost" @click="editingEntry = null">Cancelar</Button>
                    <Button type="submit" :disabled="entryEditForm.processing" :aria-busy="entryEditForm.processing">Guardar entrada</Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <MediaPickerDialog v-model:open="entryCoverPickerOpen" type="image" @select="onEntryCoverSelected" />

    <AlertDialog :open="entryToDelete !== null" @update:open="(v: boolean) => !v && (entryToDelete = null)">
        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle>¿Eliminar «{{ entryToDelete?.title }}»?</AlertDialogTitle>
                <AlertDialogDescription>La entrada desaparecerá de la línea de tiempo. Esta acción no se puede deshacer.</AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
                <AlertDialogCancel>Cancelar</AlertDialogCancel>
                <AlertDialogAction class="bg-destructive text-white hover:bg-destructive/90" @click="destroyEntry">Eliminar</AlertDialogAction>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>
</template>
