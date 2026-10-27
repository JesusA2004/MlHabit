<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    Archive,
    ArrowLeft,
    CalendarClock,
    Check,
    ChevronLeft,
    ChevronRight,
    CircleDot,
    CloudUpload,
    Eye,
    ImagePlus,
    Images,
    Loader2,
    RefreshCw,
    Rocket,
    Star,
    Trash2,
    X,
} from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import MediaPickerDialog, {
    type PickedMedia,
} from '@/components/admin/MediaPickerDialog.vue';
import PostEditor from '@/components/admin/PostEditor.vue';
import StatusBadge from '@/components/admin/StatusBadge.vue';
import InputError from '@/components/InputError.vue';
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
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import publicaciones from '@/routes/admin/publicaciones';
import type { BreadcrumbItem, TiptapContent } from '@/types';

interface PostData {
    id: number;
    category_id: number;
    title: string;
    slug: string;
    excerpt: string | null;
    body: TiptapContent | null;
    status: string;
    statusLabel: string;
    is_featured: boolean;
    seo_title: string | null;
    seo_description: string | null;
    tags: string[] | null;
    scheduled_at: string | null;
    published_at: string | null;
    cover: { id: number; url: string | null } | null;
    gallery: { id: number; url: string | null }[];
}

const props = defineProps<{
    post: PostData | null;
    categories: { id: number; name: string; slug: string }[];
}>();

const isEditing = props.post !== null;

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Panel', href: '/admin' },
            { title: 'Publicaciones', href: publicaciones.index() },
        ] as BreadcrumbItem[],
    },
});

const form = useForm({
    category_id: props.post?.category_id ?? props.categories[0]?.id ?? null,
    title: props.post?.title ?? '',
    slug: props.post?.slug ?? '',
    excerpt: props.post?.excerpt ?? '',
    body: props.post?.body ?? null,
    cover_media_id: props.post?.cover?.id ?? null,
    gallery_media_ids: props.post?.gallery.map((g) => g.id) ?? [],
    is_featured: props.post?.is_featured ?? false,
    seo_title: props.post?.seo_title ?? '',
    seo_description: props.post?.seo_description ?? '',
    tags: props.post?.tags ?? [],
});

/* ---------- Estado de guardado ---------- */

const isDirty = ref(false);
/** Cambios aún no copiados por el autoguardado (revisión). */
const autosaveDirty = ref(false);
const autosaving = ref(false);
const lastSavedAt = ref<Date | null>(null);
const lastAutosaveAt = ref<Date | null>(null);
const now = ref(Date.now());

function markDirty() {
    isDirty.value = true;
    autosaveDirty.value = true;
}

watch(() => form.body, markDirty);
watch(
    () => [
        form.category_id,
        form.cover_media_id,
        form.gallery_media_ids.join(','),
        form.tags.join('|'),
    ],
    markDirty,
);

const RTF = new Intl.RelativeTimeFormat('es-MX', { numeric: 'auto' });

function ago(date: Date): string {
    const seconds = Math.round((now.value - date.getTime()) / 1000);
    if (seconds < 45) return 'hace un momento';
    if (seconds < 3600) return RTF.format(-Math.round(seconds / 60), 'minute');

    return RTF.format(-Math.round(seconds / 3600), 'hour');
}

const saveStatus = computed<{
    label: string;
    tone: 'muted' | 'warning' | 'success';
    busy: boolean;
}>(() => {
    if (form.processing) return { label: 'Guardando…', tone: 'muted', busy: true };
    if (autosaving.value)
        return { label: 'Guardando copia…', tone: 'muted', busy: true };
    if (isDirty.value && lastAutosaveAt.value)
        return {
            label: `Sin guardar · copia automática ${ago(lastAutosaveAt.value)}`,
            tone: 'warning',
            busy: false,
        };
    if (isDirty.value)
        return { label: 'Cambios sin guardar', tone: 'warning', busy: false };
    if (lastSavedAt.value)
        return {
            label: `Guardado ${ago(lastSavedAt.value)}`,
            tone: 'success',
            busy: false,
        };

    return isEditing
        ? { label: 'Todo guardado', tone: 'success', busy: false }
        : { label: 'Borrador nuevo', tone: 'muted', busy: false };
});

/* ---------- Guardar y acciones ---------- */

function save(then?: () => void) {
    commitTagDraft();
    if (isEditing && props.post) {
        form.put(publicaciones.update(props.post.id).url, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                isDirty.value = false;
                autosaveDirty.value = false;
                lastSavedAt.value = new Date();
                // El mensaje de éxito llega como toast desde el servidor.
                if (then) then();
            },
            onError: () => toast.error('Revisa los campos marcados.'),
        });
    } else {
        form.post(publicaciones.store().url, {
            onSuccess: () => {
                isDirty.value = false;
            },
            onError: () => toast.error('Revisa los campos marcados.'),
        });
    }
}

const actionBusy = ref<null | 'publish' | 'archive' | 'featured' | 'schedule'>(
    null,
);

function postAction(
    kind: NonNullable<typeof actionBusy.value>,
    url: string,
    data: Record<string, string>,
    message: string,
    onSuccess?: () => void,
) {
    actionBusy.value = kind;
    router.post(url, data, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            onSuccess?.();
        },
        onError: (errors) => {
            if (kind === 'schedule') scheduleError.value = errors.scheduled_at;
            toast.error(errors.scheduled_at ?? 'No se pudo completar la acción.');
        },
        onFinish: () => (actionBusy.value = null),
    });
}

function publish() {
    const post = props.post;
    if (!post) return;
    const go = () =>
        postAction(
            'publish',
            publicaciones.publicar(post.id).url,
            {},
            'Publicación publicada.',
        );
    if (isDirty.value) save(go);
    else go();
}

function archive() {
    if (!props.post) return;
    postAction(
        'archive',
        publicaciones.archivar(props.post.id).url,
        {},
        'Publicación archivada.',
    );
}

function toggleFeatured() {
    if (!props.post) {
        form.is_featured = !form.is_featured;
        markDirty();

        return;
    }
    const willFeature = !props.post.is_featured;
    postAction(
        'featured',
        publicaciones.destacar(props.post.id).url,
        {},
        willFeature ? 'Publicación destacada.' : 'Ya no está destacada.',
        () => (form.is_featured = props.post?.is_featured ?? willFeature),
    );
}

const featured = computed(() => props.post?.is_featured ?? form.is_featured);

function preview() {
    if (!props.post) return;
    window.open(publicaciones.preview(props.post.id).url, '_blank');
    if (isDirty.value) {
        toast.info('La vista previa muestra la última versión guardada.');
    }
}

/* ---------- Programar ---------- */

function toLocalInput(date: Date): string {
    const pad = (n: number) => String(n).padStart(2, '0');

    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

const showScheduler = ref(false);
const scheduleAt = ref(
    props.post?.scheduled_at ? toLocalInput(new Date(props.post.scheduled_at)) : '',
);
const scheduleError = ref<string | undefined>();
const minSchedule = computed(() => toLocalInput(new Date(now.value + 60_000)));

function schedule() {
    const post = props.post;
    if (!post || !scheduleAt.value) return;
    scheduleError.value = undefined;
    const go = () =>
        postAction(
            'schedule',
            publicaciones.programar(post.id).url,
            { scheduled_at: scheduleAt.value },
            'Publicación programada.',
            () => (showScheduler.value = false),
        );
    if (isDirty.value) save(go);
    else go();
}

const DATE_LONG = new Intl.DateTimeFormat('es-MX', {
    weekday: 'short',
    day: 'numeric',
    month: 'long',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
});

function longDate(iso: string | null): string {
    return iso ? DATE_LONG.format(new Date(iso)) : '';
}

/* ---------- Autoguardado ---------- */

let autosaveTimer: ReturnType<typeof setInterval> | undefined;
let clockTimer: ReturnType<typeof setInterval> | undefined;

onMounted(() => {
    clockTimer = setInterval(() => (now.value = Date.now()), 20_000);
    if (!props.post) return;
    autosaveTimer = setInterval(() => {
        if (!autosaveDirty.value || form.processing || !form.title.trim())
            return;
        autosaving.value = true;
        router.patch(
            publicaciones.autoguardar(props.post!.id).url,
            { title: form.title, excerpt: form.excerpt, body: form.body },
            {
                showProgress: false,
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => {
                    autosaveDirty.value = false;
                    lastAutosaveAt.value = new Date();
                },
                onFinish: () => (autosaving.value = false),
            },
        );
    }, 30000);
});

onBeforeUnmount(() => {
    clearInterval(autosaveTimer);
    clearInterval(clockTimer);
});

function handleBeforeUnload(e: BeforeUnloadEvent) {
    if (isDirty.value) {
        e.preventDefault();
    }
}

function handleKeydown(e: KeyboardEvent) {
    if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 's') {
        e.preventDefault();
        if (!form.processing) save();
    }
}

onMounted(() => {
    window.addEventListener('beforeunload', handleBeforeUnload);
    window.addEventListener('keydown', handleKeydown);
});
onBeforeUnmount(() => {
    window.removeEventListener('beforeunload', handleBeforeUnload);
    window.removeEventListener('keydown', handleKeydown);
});

/* ---------- Portada ---------- */

const coverUrl = ref(props.post?.cover?.url ?? null);
const coverPickerOpen = ref(false);

function onCoverSelected(media: PickedMedia) {
    form.cover_media_id = media.id;
    coverUrl.value = media.url;
}

function removeCover() {
    form.cover_media_id = null;
    coverUrl.value = null;
}

/* ---------- Galería ---------- */

const gallery = ref<{ id: number; url: string | null }[]>(
    props.post?.gallery.map((g) => ({ ...g })) ?? [],
);
const galleryPickerOpen = ref(false);

function syncGallery() {
    form.gallery_media_ids = gallery.value.map((g) => g.id);
}

function onGallerySelected(media: PickedMedia) {
    if (gallery.value.some((g) => g.id === media.id)) {
        toast.info('Esa imagen ya está en la galería.');

        return;
    }
    gallery.value.push({ id: media.id, url: media.url });
    syncGallery();
}

function moveGallery(index: number, delta: -1 | 1) {
    const target = index + delta;
    if (target < 0 || target >= gallery.value.length) return;
    const items = [...gallery.value];
    [items[index], items[target]] = [items[target], items[index]];
    gallery.value = items;
    syncGallery();
}

function removeGallery(index: number) {
    gallery.value.splice(index, 1);
    syncGallery();
}

/* ---------- Etiquetas ---------- */

const tagDraft = ref('');

function addTag(raw: string) {
    const tag = raw.trim().replace(/,+$/, '').trim().slice(0, 50);
    if (!tag) return;
    if (form.tags.some((t) => t.toLowerCase() === tag.toLowerCase())) return;
    form.tags = [...form.tags, tag];
}

function commitTagDraft() {
    tagDraft.value
        .split(',')
        .forEach((part) => addTag(part));
    tagDraft.value = '';
}

function onTagKeydown(e: KeyboardEvent) {
    if (e.key === 'Enter' || e.key === ',') {
        e.preventDefault();
        commitTagDraft();
    } else if (e.key === 'Backspace' && !tagDraft.value && form.tags.length) {
        form.tags = form.tags.slice(0, -1);
    }
}

function removeTag(tag: string) {
    form.tags = form.tags.filter((t) => t !== tag);
}

/* ---------- SEO ---------- */

function slugify(text: string): string {
    return text
        .toLowerCase()
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '')
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '')
        .slice(0, 80);
}

const host = typeof window !== 'undefined' ? window.location.host : '';
const slugPlaceholder = computed(() => slugify(form.title) || 'mi-nuevo-tip');
const snippetTitle = computed(
    () => form.seo_title || form.title || 'Título de la publicación',
);
const snippetDescription = computed(
    () =>
        form.seo_description ||
        form.excerpt ||
        'Agrega una meta descripción o un resumen para controlar este texto.',
);
const snippetPath = computed(() => form.slug || slugPlaceholder.value);

/* ---------- Presentación ---------- */

const categoryName = computed(
    () => props.categories.find((c) => c.id === form.category_id)?.name ?? null,
);

const primaryIsPublish = computed(
    () => !!props.post && props.post.status !== 'published',
);

const fieldError = computed(() => form.errors as Record<string, string | undefined>);
const tagsError = computed(() => {
    const errors = fieldError.value;

    return (
        errors.tags ??
        Object.entries(errors).find(([key]) => key.startsWith('tags.'))?.[1]
    );
});
const galleryError = computed(() => {
    const errors = fieldError.value;

    return (
        errors.gallery_media_ids ??
        Object.entries(errors).find(([key]) =>
            key.startsWith('gallery_media_ids.'),
        )?.[1]
    );
});
</script>

<template>
    <Head :title="isEditing ? `Editar: ${post?.title}` : 'Nueva publicación'" />

    <form
        class="flex flex-1 flex-col"
        novalidate
        @submit.prevent="save()"
        @input="markDirty"
    >
        <!-- Barra superior -->
        <div
            class="sticky top-14 z-20 flex h-14 items-center gap-2 border-b bg-background/90 px-4 backdrop-blur-md md:px-6 lg:px-8"
        >
            <Button
                as-child
                variant="ghost"
                size="sm"
                class="-ml-2 h-10 shrink-0 text-muted-foreground"
            >
                <Link :href="publicaciones.index()" aria-label="Volver a publicaciones">
                    <ArrowLeft class="size-4" />
                    <span class="hidden sm:inline">Publicaciones</span>
                </Link>
            </Button>
            <span class="hidden h-5 w-px bg-border sm:block" aria-hidden="true" />
            <StatusBadge v-if="post" :status="post.status" class="hidden sm:inline-flex" />
            <p
                class="flex min-w-0 items-center gap-1.5 truncate text-xs"
                :class="{
                    'text-muted-foreground': saveStatus.tone === 'muted',
                    'text-warning': saveStatus.tone === 'warning',
                    'text-success': saveStatus.tone === 'success',
                }"
                role="status"
                aria-live="polite"
            >
                <Loader2 v-if="saveStatus.busy" class="size-3.5 shrink-0 animate-spin" />
                <CircleDot v-else-if="saveStatus.tone === 'warning'" class="size-3.5 shrink-0" />
                <Check v-else class="size-3.5 shrink-0" />
                <span class="truncate">{{ saveStatus.label }}</span>
            </p>

            <div class="ml-auto flex shrink-0 items-center gap-2">
                <Button
                    v-if="post"
                    type="button"
                    variant="ghost"
                    class="h-10"
                    @click="preview"
                >
                    <Eye class="size-4" />
                    <span class="hidden md:inline">Vista previa</span>
                    <span class="sr-only md:hidden">Vista previa</span>
                </Button>
                <Button
                    type="submit"
                    :variant="primaryIsPublish ? 'outline' : 'default'"
                    class="h-10"
                    :disabled="form.processing"
                    :aria-busy="form.processing"
                >
                    <Loader2 v-if="form.processing" class="size-4 animate-spin" />
                    <CloudUpload v-else class="size-4" />
                    <span :class="primaryIsPublish && 'hidden sm:inline'">{{
                        isEditing ? 'Guardar' : 'Guardar borrador'
                    }}</span>
                    <span v-if="primaryIsPublish" class="sr-only sm:hidden">Guardar</span>
                </Button>
                <Button
                    v-if="primaryIsPublish"
                    type="button"
                    class="h-10"
                    :disabled="actionBusy !== null || form.processing"
                    :aria-busy="actionBusy === 'publish'"
                    @click="publish"
                >
                    <Loader2 v-if="actionBusy === 'publish'" class="size-4 animate-spin" />
                    <Rocket v-else class="size-4" />
                    {{ post?.status === 'scheduled' ? 'Publicar ahora' : 'Publicar' }}
                </Button>
            </div>
        </div>

        <div
            class="grid flex-1 items-start gap-6 p-4 md:p-6 lg:grid-cols-[minmax(0,1fr)_340px] lg:gap-8 lg:p-8 xl:grid-cols-[minmax(0,1fr)_368px]"
        >
            <!-- Área de escritura -->
            <section
                class="min-w-0 rounded-xl border bg-card px-5 py-6 md:px-10 md:py-10"
                aria-label="Contenido de la publicación"
            >
                <div class="mx-auto flex max-w-[68ch] flex-col">
                    <p
                        class="mb-3 text-[11px] font-semibold tracking-[0.16em] text-brand uppercase"
                    >
                        {{ categoryName ?? 'Sin categoría' }}
                    </p>
                    <Label for="title" class="sr-only">Título</Label>
                    <textarea
                        id="title"
                        v-model="form.title"
                        rows="1"
                        required
                        maxlength="255"
                        placeholder="Escribe un título que atrape"
                        :aria-invalid="!!form.errors.title"
                        class="w-full resize-none bg-transparent font-display text-3xl leading-[1.1] font-semibold tracking-tight field-sizing-content placeholder:text-muted-foreground/60 focus:outline-none md:text-[2.5rem]"
                        @keydown.enter.prevent
                    />
                    <InputError :message="form.errors.title" class="mt-1" />

                    <Label for="excerpt" class="sr-only">Resumen</Label>
                    <textarea
                        id="excerpt"
                        v-model="form.excerpt"
                        rows="2"
                        maxlength="500"
                        placeholder="Resumen: una o dos frases que inviten a leer. Aparece en tarjetas y redes."
                        :aria-invalid="!!form.errors.excerpt"
                        class="mt-4 w-full resize-none bg-transparent text-lg leading-relaxed text-muted-foreground field-sizing-content placeholder:text-muted-foreground/60 focus:text-foreground focus:outline-none"
                    />
                    <div class="mt-1 flex items-center justify-between gap-3">
                        <InputError :message="form.errors.excerpt" />
                        <span
                            class="ml-auto text-xs text-muted-foreground tabular-nums"
                            :class="(form.excerpt?.length ?? 0) > 450 && 'text-warning'"
                            >{{ form.excerpt?.length ?? 0 }}/500</span
                        >
                    </div>

                    <div class="mt-6">
                        <PostEditor
                            v-model:content="form.body"
                            variant="seamless"
                            toolbar-sticky-class="top-28"
                        />
                    </div>
                </div>
            </section>

            <!-- Barra lateral -->
            <aside
                class="flex min-w-0 flex-col gap-4 lg:sticky lg:top-32 lg:max-h-[calc(100svh-9rem)] lg:overflow-y-auto lg:overscroll-contain lg:pr-1 lg:pb-2"
                aria-label="Ajustes de la publicación"
            >
                <!-- Estado y publicación -->
                <section class="rounded-xl border bg-card p-4">
                    <div class="flex items-center justify-between gap-2">
                        <h2 class="text-sm font-semibold">Estado y publicación</h2>
                        <StatusBadge :status="post?.status ?? 'draft'" />
                    </div>

                    <template v-if="post">
                        <dl class="mt-3 flex flex-col gap-2 text-sm">
                            <div v-if="post.published_at" class="flex justify-between gap-3">
                                <dt class="text-muted-foreground">Publicada</dt>
                                <dd class="text-right font-medium">{{ longDate(post.published_at) }}</dd>
                            </div>
                            <div v-if="post.scheduled_at" class="flex justify-between gap-3">
                                <dt class="text-muted-foreground">Sale</dt>
                                <dd class="text-right font-medium">{{ longDate(post.scheduled_at) }}</dd>
                            </div>
                            <div class="flex justify-between gap-3">
                                <dt class="text-muted-foreground">Autoguardado</dt>
                                <dd class="text-right text-xs leading-5">
                                    {{
                                        lastAutosaveAt
                                            ? `Copia ${ago(lastAutosaveAt)}`
                                            : 'Cada 30 s mientras escribes'
                                    }}
                                </dd>
                            </div>
                        </dl>

                        <div class="mt-4 flex flex-col gap-2">
                            <Button
                                v-if="post.status !== 'published'"
                                type="button"
                                variant="outline"
                                class="h-10 w-full justify-start"
                                :aria-expanded="showScheduler"
                                aria-controls="post-scheduler"
                                @click="showScheduler = !showScheduler"
                            >
                                <CalendarClock class="size-4" />
                                {{ post.scheduled_at ? 'Reprogramar' : 'Programar publicación' }}
                            </Button>
                            <div
                                v-if="showScheduler"
                                id="post-scheduler"
                                class="flex flex-col gap-2 rounded-lg bg-muted/50 p-3"
                                @input.stop
                            >
                                <Label for="schedule-at" class="text-xs">Fecha y hora de salida</Label>
                                <Input
                                    id="schedule-at"
                                    v-model="scheduleAt"
                                    type="datetime-local"
                                    :min="minSchedule"
                                    class="h-10 bg-background"
                                    :aria-invalid="!!scheduleError"
                                />
                                <InputError :message="scheduleError" />
                                <Button
                                    type="button"
                                    class="h-10"
                                    :disabled="!scheduleAt || actionBusy !== null"
                                    :aria-busy="actionBusy === 'schedule'"
                                    @click="schedule"
                                >
                                    <Loader2 v-if="actionBusy === 'schedule'" class="size-4 animate-spin" />
                                    Programar
                                </Button>
                            </div>
                            <Button
                                v-if="post.status !== 'archived'"
                                type="button"
                                variant="ghost"
                                class="h-10 w-full justify-start text-muted-foreground"
                                :disabled="actionBusy !== null"
                                :aria-busy="actionBusy === 'archive'"
                                @click="archive"
                            >
                                <Loader2 v-if="actionBusy === 'archive'" class="size-4 animate-spin" />
                                <Archive v-else class="size-4" />
                                Archivar
                            </Button>
                        </div>
                    </template>
                    <p v-else class="mt-2 text-sm text-muted-foreground">
                        Guarda el borrador para poder publicarlo, programarlo o verlo en vista previa.
                    </p>
                </section>

                <!-- Portada -->
                <section class="rounded-xl border bg-card p-4">
                    <h2 class="text-sm font-semibold">Portada</h2>
                    <p class="mt-0.5 text-xs text-muted-foreground">Horizontal 16:9; se recorta en tarjetas.</p>
                    <button
                        v-if="!coverUrl"
                        type="button"
                        class="mt-3 flex aspect-video w-full flex-col items-center justify-center gap-2 rounded-lg border border-dashed bg-muted/40 text-sm text-muted-foreground transition-colors hover:border-brand hover:text-foreground focus-visible:ring-3 focus-visible:ring-ring/50 focus-visible:outline-none"
                        @click="coverPickerOpen = true"
                    >
                        <ImagePlus class="size-6" />
                        Elegir portada
                    </button>
                    <template v-else>
                        <div class="mt-3 aspect-video overflow-hidden rounded-lg bg-muted">
                            <img :src="coverUrl" alt="Portada seleccionada" class="size-full object-cover" />
                        </div>
                        <div class="mt-2 flex gap-2">
                            <Button type="button" variant="outline" class="h-10 flex-1" @click="coverPickerOpen = true">
                                <RefreshCw class="size-4" /> Cambiar
                            </Button>
                            <Button type="button" variant="ghost" class="h-10 text-muted-foreground" @click="removeCover">
                                <Trash2 class="size-4" /> Quitar
                            </Button>
                        </div>
                    </template>
                    <InputError :message="form.errors.cover_media_id" class="mt-2" />
                </section>

                <!-- Categoría -->
                <section class="rounded-xl border bg-card p-4">
                    <Label for="category" class="text-sm font-semibold">Categoría</Label>
                    <Select v-model="form.category_id">
                        <SelectTrigger
                            id="category"
                            class="mt-2 h-10 w-full"
                            :aria-invalid="!!form.errors.category_id"
                        >
                            <SelectValue placeholder="Selecciona una categoría" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="category in categories"
                                :key="category.id"
                                :value="category.id"
                            >
                                {{ category.name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="form.errors.category_id" class="mt-1" />
                </section>

                <!-- Etiquetas -->
                <section class="rounded-xl border bg-card p-4">
                    <Label for="tags" class="text-sm font-semibold">Etiquetas</Label>
                    <div
                        class="mt-2 flex min-h-10 flex-wrap items-center gap-1.5 rounded-md border border-input px-2 py-1.5 shadow-xs focus-within:border-ring focus-within:ring-3 focus-within:ring-ring/50 dark:bg-input/30"
                    >
                        <span
                            v-for="tag in form.tags"
                            :key="tag"
                            class="inline-flex h-7 items-center gap-1 rounded-full bg-secondary pr-1 pl-2.5 text-xs font-medium text-secondary-foreground"
                        >
                            {{ tag }}
                            <button
                                type="button"
                                class="grid size-5 place-items-center rounded-full text-muted-foreground hover:bg-background hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring/50 focus-visible:outline-none"
                                :aria-label="`Quitar etiqueta ${tag}`"
                                @click="removeTag(tag)"
                            >
                                <X class="size-3" />
                            </button>
                        </span>
                        <input
                            id="tags"
                            v-model="tagDraft"
                            type="text"
                            maxlength="50"
                            :placeholder="form.tags.length ? 'Agregar…' : 'hábitos, enfoque…'"
                            class="h-7 min-w-24 flex-1 bg-transparent text-sm outline-none placeholder:text-muted-foreground"
                            @keydown="onTagKeydown"
                            @blur="commitTagDraft"
                        />
                    </div>
                    <p class="mt-1.5 text-xs text-muted-foreground">Enter o coma para agregar. Ej.: disciplina</p>
                    <InputError :message="tagsError" class="mt-1" />
                </section>

                <!-- Destacado -->
                <section class="flex items-start justify-between gap-4 rounded-xl border bg-card p-4">
                    <div>
                        <Label for="featured" class="flex items-center gap-1.5 text-sm font-semibold">
                            <Star class="size-4" :class="featured ? 'fill-brand text-brand' : 'text-muted-foreground'" />
                            Destacada
                        </Label>
                        <p class="mt-0.5 text-xs text-muted-foreground">
                            Aparece en los espacios destacados del inicio.
                        </p>
                    </div>
                    <Switch
                        id="featured"
                        :model-value="featured"
                        :disabled="actionBusy === 'featured'"
                        class="mt-0.5 pointer-coarse:scale-125"
                        @update:model-value="toggleFeatured"
                    />
                </section>

                <!-- Galería -->
                <section class="rounded-xl border bg-card p-4">
                    <div class="flex items-center justify-between gap-2">
                        <h2 class="text-sm font-semibold">Galería</h2>
                        <span class="text-xs text-muted-foreground tabular-nums">{{ gallery.length }}</span>
                    </div>
                    <p class="mt-0.5 text-xs text-muted-foreground">Fotos extra al final del tip, en este orden.</p>
                    <ul v-if="gallery.length" class="mt-3 grid grid-cols-2 gap-2">
                        <li
                            v-for="(item, index) in gallery"
                            :key="item.id"
                            class="relative aspect-[4/3] overflow-hidden rounded-lg bg-muted"
                        >
                            <img
                                v-if="item.url"
                                :src="item.url"
                                :alt="`Imagen ${index + 1} de la galería`"
                                loading="lazy"
                                class="size-full object-cover"
                            />
                            <div class="absolute inset-x-0 bottom-0 flex items-center justify-between bg-foreground/60 p-1 text-background">
                                <div class="flex">
                                    <button
                                        type="button"
                                        class="grid size-8 place-items-center rounded-md hover:bg-background/20 focus-visible:ring-2 focus-visible:ring-background focus-visible:outline-none disabled:opacity-40 pointer-coarse:size-10"
                                        :disabled="index === 0"
                                        :aria-label="`Mover imagen ${index + 1} antes`"
                                        @click="moveGallery(index, -1)"
                                    >
                                        <ChevronLeft class="size-4" />
                                    </button>
                                    <button
                                        type="button"
                                        class="grid size-8 place-items-center rounded-md hover:bg-background/20 focus-visible:ring-2 focus-visible:ring-background focus-visible:outline-none disabled:opacity-40 pointer-coarse:size-10"
                                        :disabled="index === gallery.length - 1"
                                        :aria-label="`Mover imagen ${index + 1} después`"
                                        @click="moveGallery(index, 1)"
                                    >
                                        <ChevronRight class="size-4" />
                                    </button>
                                </div>
                                <button
                                    type="button"
                                    class="grid size-8 place-items-center rounded-md hover:bg-background/20 focus-visible:ring-2 focus-visible:ring-background focus-visible:outline-none pointer-coarse:size-10"
                                    :aria-label="`Quitar imagen ${index + 1} de la galería`"
                                    @click="removeGallery(index)"
                                >
                                    <X class="size-4" />
                                </button>
                            </div>
                        </li>
                    </ul>
                    <Button
                        type="button"
                        variant="outline"
                        class="mt-3 h-10 w-full"
                        @click="galleryPickerOpen = true"
                    >
                        <Images class="size-4" />
                        {{ gallery.length ? 'Agregar otra imagen' : 'Agregar imágenes' }}
                    </Button>
                    <InputError :message="galleryError" class="mt-1" />
                </section>

                <!-- SEO -->
                <section class="flex flex-col gap-4 rounded-xl border bg-card p-4">
                    <div>
                        <h2 class="text-sm font-semibold">SEO y dirección</h2>
                        <p class="mt-0.5 text-xs text-muted-foreground">Así podría verse en Google.</p>
                    </div>

                    <div class="rounded-lg border bg-background p-3" aria-label="Vista previa en buscadores">
                        <p class="truncate text-xs text-muted-foreground">
                            {{ host }} › tips › {{ snippetPath }}
                        </p>
                        <p class="mt-1 line-clamp-1 text-base leading-snug font-medium text-primary underline-offset-2 hover:underline">
                            {{ snippetTitle }}
                        </p>
                        <p class="mt-1 line-clamp-2 text-xs leading-relaxed text-muted-foreground">
                            {{ snippetDescription }}
                        </p>
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <Label for="slug">Dirección</Label>
                        <div
                            class="flex h-10 items-center rounded-md border border-input pl-3 text-sm shadow-xs focus-within:border-ring focus-within:ring-3 focus-within:ring-ring/50 dark:bg-input/30"
                            :class="form.errors.slug && 'border-destructive'"
                        >
                            <span class="shrink-0 text-muted-foreground">/tips/</span>
                            <input
                                id="slug"
                                v-model="form.slug"
                                maxlength="255"
                                :placeholder="slugPlaceholder"
                                :aria-invalid="!!form.errors.slug"
                                class="h-full min-w-0 flex-1 bg-transparent pr-3 outline-none placeholder:text-muted-foreground/70"
                            />
                        </div>
                        <p class="text-xs text-muted-foreground">Solo minúsculas, números y guiones. Vacío = se genera del título.</p>
                        <InputError :message="form.errors.slug" />
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <div class="flex items-baseline justify-between gap-2">
                            <Label for="seo_title">Título SEO</Label>
                            <span
                                class="text-xs text-muted-foreground tabular-nums"
                                :class="(form.seo_title?.length ?? 0) > 60 && 'text-warning'"
                                >{{ form.seo_title?.length ?? 0 }}/60</span
                            >
                        </div>
                        <Input
                            id="seo_title"
                            v-model="form.seo_title"
                            maxlength="255"
                            class="h-10"
                            :placeholder="form.title || 'Usa el título si lo dejas vacío'"
                            :aria-invalid="!!form.errors.seo_title"
                        />
                        <InputError :message="form.errors.seo_title" />
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <div class="flex items-baseline justify-between gap-2">
                            <Label for="seo_description">Meta descripción</Label>
                            <span
                                class="text-xs text-muted-foreground tabular-nums"
                                :class="(form.seo_description?.length ?? 0) > 160 && 'text-warning'"
                                >{{ form.seo_description?.length ?? 0 }}/160</span
                            >
                        </div>
                        <Textarea
                            id="seo_description"
                            v-model="form.seo_description"
                            rows="3"
                            maxlength="500"
                            placeholder="Si la dejas vacía se usa el resumen."
                            :aria-invalid="!!form.errors.seo_description"
                        />
                        <InputError :message="form.errors.seo_description" />
                    </div>
                </section>
            </aside>
        </div>
    </form>

    <MediaPickerDialog
        v-model:open="coverPickerOpen"
        type="image"
        @select="onCoverSelected"
    />
    <MediaPickerDialog
        v-model:open="galleryPickerOpen"
        type="image"
        @select="onGallerySelected"
    />
</template>
