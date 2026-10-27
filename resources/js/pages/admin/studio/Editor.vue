<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Check,
    CloudOff,
    Columns2,
    Eye,
    GitCompareArrows,
    Hand,
    History,
    Laptop,
    Loader2,
    Monitor,
    MoreHorizontal,
    PanelLeft,
    PanelRight,
    Redo2,
    Rocket,
    Ruler,
    Settings2,
    Share2,
    Smartphone,
    Tablet,
    TriangleAlert,
    Undo2,
} from '@lucide/vue';
import { computed, nextTick, onBeforeUnmount, onMounted, provide, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import MediaPickerDialog, { type PickedMedia } from '@/components/admin/MediaPickerDialog.vue';
import AddBlockDialog, { type AddTarget } from '@/components/studio/AddBlockDialog.vue';
import CanvasFrame from '@/components/studio/CanvasFrame.vue';
import { EDITOR_CTX, type EditorOptions } from '@/components/studio/editorContext';
import InspectorPanel from '@/components/studio/InspectorPanel.vue';
import PageSettingsDialog, { type StudioPageMeta } from '@/components/studio/PageSettingsDialog.vue';
import PublishDialog from '@/components/studio/PublishDialog.vue';
import StructurePanel from '@/components/studio/StructurePanel.vue';
import VersionsSheet, { type VersionRow } from '@/components/studio/VersionsSheet.vue';
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
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Toaster } from '@/components/ui/sonner';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { apiFetch } from '@/lib/http';
import { cn } from '@/lib/utils';
import studioRoutes from '@/routes/admin/studio';
import type { FromCanvas } from '@/studio/bridge';
import { type CheckIssue, runChecks } from '@/studio/checks';
import { suggestAspect } from '@/studio/media';
import { BREAKPOINTS, schema } from '@/studio/schema';
import { createStudioStore } from '@/studio/store';
import { locate } from '@/studio/tree';
import type { Breakpoint, ResolvedMedia, StudioDocument, StudioResolved } from '@/studio/types';

const props = defineProps<{
    page: StudioPageMeta;
    document: StudioDocument;
    published: StudioDocument | null;
    resolved: StudioResolved;
    versions: VersionRow[];
    pages: { id: number; title: string; key: string; kind: string; path: string }[];
    options: EditorOptions;
    canvasUrl: string;
    previewUrl: string;
}>();

const page = ref<StudioPageMeta>({ ...props.page });
const versions = ref<VersionRow[]>(props.versions);
const published = ref<StudioDocument | null>(props.published);

const store = createStudioStore({
    document: props.document,
    resolved: props.resolved,
    revision: props.page.revision,
    saveUrl: studioRoutes.borrador(props.page.id).url,
    resolveUrl: studioRoutes.resolver(props.page.id).url,
});

// ---------------------------------------------------------------------
// Diálogos y paneles
// ---------------------------------------------------------------------
const addOpen = ref(false);
const addTarget = ref<AddTarget | null>(null);
function openAdd(target: { sectionId: string; slot: string; index?: number } | null) {
    if (!target) return;
    addTarget.value = { mode: 'element', ...target };
    addOpen.value = true;
}
function openAddSection(index: number) {
    addTarget.value = { mode: 'section', index };
    addOpen.value = true;
}

provide(EDITOR_CTX, { store, options: props.options, openAdd });

const leftOpen = ref(true);
const rightOpen = ref(true);
const wide = ref(true);

const mediaPickerOpen = ref(false);
const mediaPickerType = ref<'image' | 'video'>('image');
const mediaTargetId = ref<string | null>(null);

const publishOpen = ref(false);
const publishing = ref(false);
const issues = ref<CheckIssue[]>([]);
const serverProblems = ref<string[]>([]);
const versionsOpen = ref(false);
const settingsOpen = ref(false);
const discardOpen = ref(false);

// ---------------------------------------------------------------------
// Canvas
// ---------------------------------------------------------------------
const canvas = ref<InstanceType<typeof CanvasFrame> | null>(null);
const compare = ref<{ label: string; document: StudioDocument; resolved: StudioResolved } | null>(null);
const interact = ref(false);
const showSpacing = ref(true);
const deviceWidth = computed(() => schema.breakpoints[store.state.bp].preview);
const DEVICE_ICONS: Record<Breakpoint, typeof Monitor> = { base: Monitor, laptop: Laptop, tablet: Tablet, mobile: Smartphone };

let renderQueued = false;
function renderCanvas() {
    if (renderQueued) return;
    renderQueued = true;
    requestAnimationFrame(() => {
        renderQueued = false;
        const c = compare.value;
        canvas.value?.post({
            type: 'studio:render',
            document: c ? c.document : store.state.doc,
            resolved: c ? c.resolved : store.state.resolved,
            interactive: !c,
            label: c ? c.label : null,
        });
    });
}

let selectionFromCanvas = false;
watch(
    () => store.state.selectedId,
    (id) => {
        canvas.value?.post({ type: 'studio:select', id, scroll: !selectionFromCanvas });
        selectionFromCanvas = false;
        if (id && !wide.value) rightOpen.value = true;
    },
);
watch(() => [store.state.changeCount, store.state.resolved, compare.value], renderCanvas);
watch([interact, showSpacing], () => canvas.value?.post({ type: 'studio:mode', interact: interact.value, spacing: showSpacing.value }));

function onCanvasMessage(message: FromCanvas) {
    switch (message.type) {
        case 'studio:ready':
            renderCanvas();
            canvas.value?.post({ type: 'studio:mode', interact: interact.value, spacing: showSpacing.value });
            void nextTick(() => canvas.value?.post({ type: 'studio:select', id: store.state.selectedId, scroll: true }));
            break;
        case 'studio:select':
            selectionFromCanvas = true;
            store.select(message.id);
            break;
        case 'studio:inline':
            store.setProp(message.id, 'text', message.value);
            break;
        case 'studio:add':
            openAdd({ sectionId: message.sectionId, slot: message.slot });
            break;
        case 'studio:action':
            canvasAction(message.action, message.id);
            break;
        case 'studio:key':
            handleKey(message.key, message.ctrl, message.shift, message.alt);
            break;
    }
}

function canvasAction(action: string, id: string) {
    const loc = locate(store.state.doc, id);
    if (!loc) return;
    switch (action) {
        case 'select-parent':
            store.select(loc.section.id);
            break;
        case 'up':
            store.move(id, -1);
            break;
        case 'down':
            store.move(id, 1);
            break;
        case 'duplicate':
            store.duplicate(id);
            break;
        case 'hide':
            store.toggleHidden(id);
            break;
        case 'delete':
            store.remove(id);
            break;
        case 'media':
            if (loc.node.type === 'image' || loc.node.type === 'video') {
                mediaTargetId.value = id;
                mediaPickerType.value = loc.node.type;
                mediaPickerOpen.value = true;
            }
            break;
        case 'add-after':
            if (loc.slot) openAdd({ sectionId: loc.section.id, slot: loc.slot, index: loc.index + 1 });
            else openAddSection(loc.index + 1);
            break;
    }
}

function onMediaPicked(m: PickedMedia) {
    const id = mediaTargetId.value;
    if (!id) return;
    const resolved: ResolvedMedia = {
        id: m.id, type: m.type, mime: m.mimeType ?? '', url: m.url, posterUrl: m.posterUrl ?? null,
        width: m.width ?? null, height: m.height ?? null, duration: m.durationSeconds ?? null, alt: m.altText, filename: m.filename,
    };
    store.registerMedia(resolved);
    store.commit((doc) => {
        const node = locate(doc, id)?.node;
        if (!node) return;
        node.props.media = m.id;
        if (node.type === 'image') node.props.bind = 'none';
    });
    suggestAspect(store, id, resolved);
}

// ---------------------------------------------------------------------
// Teclado
// ---------------------------------------------------------------------
function handleKey(key: string, ctrl: boolean, shift: boolean, alt: boolean): boolean {
    const id = store.state.selectedId;
    const k = key.toLowerCase();
    if (ctrl && k === 'z') {
        if (shift) store.redo();
        else store.undo();
    } else if (ctrl && k === 'y') store.redo();
    else if (ctrl && k === 's') void store.save(true).then(() => toast.success('Borrador guardado'));
    else if (ctrl && k === 'd' && id) store.duplicate(id);
    else if ((key === 'Delete' || key === 'Backspace') && id) store.remove(id);
    else if (key === 'Escape') store.select(null);
    else if (alt && key === 'ArrowUp' && id) store.move(id, -1);
    else if (alt && key === 'ArrowDown' && id) store.move(id, 1);
    else return false;

    return true;
}

function onWindowKey(event: KeyboardEvent) {
    const target = event.target as HTMLElement;
    const typing = target.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT'].includes(target.tagName);
    const ctrl = event.ctrlKey || event.metaKey;
    // Dentro de un campo, el deshacer nativo manda; solo Ctrl+S se intercepta.
    if (typing && !(ctrl && event.key.toLowerCase() === 's')) return;
    if (document.querySelector('[role="dialog"], [role="alertdialog"]')) return;
    if (handleKey(event.key, ctrl, event.shiftKey, event.altKey)) event.preventDefault();
}

// ---------------------------------------------------------------------
// Guardado, salida, conflictos
// ---------------------------------------------------------------------
const statusLabel = computed(() => {
    switch (store.state.status) {
        case 'saving':
            return 'Guardando…';
        case 'dirty':
            return 'Cambios sin guardar';
        case 'error':
            return 'Error al guardar';
        case 'conflict':
            return 'Conflicto';
        default:
            return store.state.lastSavedAt ? 'Borrador guardado' : 'Al día';
    }
});
const hasUnpublished = computed(() => page.value.hasChanges || store.state.changeCount > 0);

function onBeforeUnload(event: BeforeUnloadEvent) {
    if (store.state.status === 'dirty' || store.state.status === 'saving' || store.state.status === 'error') {
        event.preventDefault();
    }
}

let removeRouterGuard: (() => void) | null = null;

async function reloadFromServer() {
    router.reload({ only: ['document', 'page', 'versions', 'resolved', 'published'], onSuccess: () => {
        store.replaceDocument(props.document, props.page.revision);
        page.value = { ...props.page };
        versions.value = props.versions;
    } });
}

async function overwrite() {
    await store.save(true);
    if (store.state.status === 'saved') toast.success('Tus cambios se guardaron sobre la otra sesión.');
}

// ---------------------------------------------------------------------
// Publicación, versiones, vista previa
// ---------------------------------------------------------------------
async function openPublish() {
    await store.save();
    if (store.state.status === 'error' || store.state.status === 'conflict') {
        toast.error('Resuelve el problema de guardado antes de publicar.');

        return;
    }
    issues.value = runChecks(store.state.doc, store.state.resolved);
    serverProblems.value = [];
    publishOpen.value = true;
}

async function publish(summary: string) {
    publishing.value = true;
    const response = await apiFetch(studioRoutes.publicar(page.value.id).url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ summary }),
    });
    const payload = await response.json();
    publishing.value = false;
    if (response.status === 422) {
        serverProblems.value = payload.problems ?? [payload.message];

        return;
    }
    if (!response.ok) {
        toast.error('No se pudo publicar. Intenta de nuevo.');

        return;
    }
    page.value = payload.page;
    versions.value = payload.versions;
    published.value = payload.published;
    store.state.changeCount = 0;
    publishOpen.value = false;
    toast.success(`Publicado. «${page.value.title}» ya está en línea.`, {
        action: { label: 'Ver sitio', onClick: () => window.open(page.value.path, '_blank') },
    });
}

async function viewVersion(v: VersionRow) {
    const response = await apiFetch(studioRoutes.version([page.value.id, v.id]).url);
    const payload = await response.json();
    const resolve = await apiFetch(studioRoutes.resolver(page.value.id).url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ document: payload.document }),
    });
    compare.value = { label: `Viendo ${v.kind === 'snapshot' ? 'respaldo' : 'versión'} ${v.number} — solo lectura`, document: payload.document, resolved: await resolve.json() };
    versionsOpen.value = false;
}

async function togglePublishedCompare() {
    if (compare.value) {
        compare.value = null;

        return;
    }
    if (!published.value) {
        toast.info('Esta página aún no tiene versión publicada.');

        return;
    }
    const resolve = await apiFetch(studioRoutes.resolver(page.value.id).url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ document: published.value }),
    });
    compare.value = { label: 'Versión publicada — solo lectura', document: published.value, resolved: await resolve.json() };
}

async function restore(v: VersionRow) {
    await store.save();
    const response = await apiFetch(studioRoutes.restaurar([page.value.id, v.id]).url, { method: 'POST' });
    if (!response.ok) {
        toast.error('No se pudo restaurar la versión.');

        return;
    }
    const payload = await response.json();
    page.value = payload.page;
    versions.value = payload.versions;
    store.replaceDocument(payload.document, payload.page.revision);
    compare.value = null;
    versionsOpen.value = false;
    toast.success(`Versión ${v.number} restaurada en el borrador. Publica cuando quieras que sea visible.`);
}

async function discard() {
    const response = await apiFetch(studioRoutes.descartar(page.value.id).url, { method: 'POST' });
    if (!response.ok) return;
    const payload = await response.json();
    page.value = payload.page;
    store.replaceDocument(payload.document, payload.page.revision);
    store.state.changeCount = 0;
    toast.success('Borrador descartado: vuelves a lo publicado.');
}

async function openPreview() {
    await store.save();
    window.open(props.previewUrl, '_blank', 'noopener');
}

async function share() {
    await store.save();
    const response = await apiFetch(studioRoutes.compartir(page.value.id).url, { method: 'POST' });
    if (!response.ok) return;
    const { url } = await response.json();
    try {
        await navigator.clipboard.writeText(url);
        toast.success('Enlace de vista previa copiado (válido 48 h).', { description: url });
    } catch {
        toast.success('Enlace de vista previa (válido 48 h)', { description: url });
    }
}

function switchPage(id: number) {
    if (id === page.value.id) return;
    void store.save().then(() => router.visit(studioRoutes.edit(id).url));
}

function setDevice(bp: Breakpoint) {
    store.state.bp = bp;
}

// ---------------------------------------------------------------------
// Ciclo de vida
// ---------------------------------------------------------------------
let previousDark = false;
const media = typeof window !== 'undefined' ? window.matchMedia('(min-width: 1280px)') : null;
function syncWide() {
    wide.value = media?.matches ?? true;
    leftOpen.value = wide.value;
    rightOpen.value = wide.value;
}

onMounted(() => {
    previousDark = document.documentElement.classList.contains('dark');
    document.documentElement.classList.add('dark');
    window.addEventListener('keydown', onWindowKey);
    window.addEventListener('beforeunload', onBeforeUnload);
    media?.addEventListener('change', syncWide);
    syncWide();
    removeRouterGuard = router.on('before', (event) => {
        const url = (event.detail.visit.url as URL).pathname;
        if (url.startsWith(`/admin/studio/${page.value.id}`)) return;
        if (store.state.status === 'dirty' || store.state.status === 'saving') {
            void store.save();
        }
        if (store.state.status === 'error' && !window.confirm('Hay cambios sin guardar. ¿Salir de todos modos?')) {
            event.preventDefault();
        }
    });
});

onBeforeUnmount(() => {
    if (!previousDark) document.documentElement.classList.remove('dark');
    window.removeEventListener('keydown', onWindowKey);
    window.removeEventListener('beforeunload', onBeforeUnload);
    media?.removeEventListener('change', syncWide);
    removeRouterGuard?.();
});
</script>

<template>
    <Head :title="`Studio · ${page.title}`" />
    <TooltipProvider :delay-duration="300">
        <div class="fixed inset-0 flex flex-col bg-background text-foreground">
            <!-- Barra superior -->
            <header class="flex h-14 shrink-0 items-center gap-2 border-b px-2 sm:px-3">
                <Tooltip>
                    <TooltipTrigger as-child>
                        <Link :href="studioRoutes.index().url" class="grid size-9 place-items-center rounded-md text-muted-foreground hover:bg-muted hover:text-foreground" aria-label="Volver a Studio">
                            <ArrowLeft class="size-4" />
                        </Link>
                    </TooltipTrigger>
                    <TooltipContent>Todas las páginas</TooltipContent>
                </Tooltip>

                <button v-if="!wide" type="button" class="grid size-9 place-items-center rounded-md hover:bg-muted" :class="{ 'bg-muted': leftOpen }" aria-label="Estructura" @click="leftOpen = !leftOpen">
                    <PanelLeft class="size-4" />
                </button>

                <DropdownMenu>
                    <DropdownMenuTrigger class="flex h-9 min-w-0 items-center gap-2 rounded-md px-2 text-left hover:bg-muted">
                        <span class="hidden size-6 place-items-center rounded bg-brand text-[11px] font-bold text-brand-foreground sm:grid">S</span>
                        <span class="min-w-0">
                            <span class="block truncate text-sm font-semibold">{{ page.title }}</span>
                            <span class="block truncate text-[10px] text-muted-foreground">{{ page.path }}</span>
                        </span>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="start" class="w-64">
                        <DropdownMenuLabel>Cambiar de página</DropdownMenuLabel>
                        <DropdownMenuItem v-for="p in pages" :key="p.id" @select="switchPage(p.id)">
                            <Check v-if="p.id === page.id" class="size-4" />
                            <span v-else class="size-4" />
                            {{ p.title }}
                            <span class="ml-auto text-[10px] text-muted-foreground">{{ p.path }}</span>
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>

                <span
                    class="hidden items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-medium md:inline-flex"
                    :class="{
                        'bg-success/12 text-success': store.state.status === 'saved',
                        'bg-muted text-muted-foreground': store.state.status === 'dirty' || store.state.status === 'saving',
                        'bg-destructive/15 text-destructive': store.state.status === 'error' || store.state.status === 'conflict',
                    }"
                    role="status"
                    aria-live="polite"
                >
                    <Loader2 v-if="store.state.status === 'saving'" class="size-3 animate-spin" />
                    <CloudOff v-else-if="store.state.status === 'error' || store.state.status === 'conflict'" class="size-3" />
                    <Check v-else-if="store.state.status === 'saved'" class="size-3" />
                    {{ statusLabel }}
                </span>

                <div class="mx-auto flex items-center gap-0.5 rounded-lg border bg-muted/40 p-0.5" role="radiogroup" aria-label="Dispositivo">
                    <Tooltip v-for="bp in BREAKPOINTS" :key="bp">
                        <TooltipTrigger as-child>
                            <button
                                type="button"
                                role="radio"
                                :aria-checked="store.state.bp === bp"
                                :aria-label="`${schema.breakpoints[bp].label} (${schema.breakpoints[bp].preview} px)`"
                                :class="cn('grid size-8 place-items-center rounded-md text-muted-foreground transition-colors hover:text-foreground', store.state.bp === bp && 'bg-background text-foreground shadow-xs ring-1 ring-brand/50')"
                                @click="setDevice(bp)"
                            >
                                <component :is="DEVICE_ICONS[bp]" class="size-4" />
                            </button>
                        </TooltipTrigger>
                        <TooltipContent>{{ schema.breakpoints[bp].label }} · {{ schema.breakpoints[bp].preview }} px</TooltipContent>
                    </Tooltip>
                </div>

                <div class="flex items-center gap-0.5">
                    <Tooltip>
                        <TooltipTrigger as-child>
                            <button type="button" class="grid size-9 place-items-center rounded-md hover:bg-muted disabled:opacity-35" :disabled="!store.state.past.length" aria-label="Deshacer" @click="store.undo()"><Undo2 class="size-4" /></button>
                        </TooltipTrigger>
                        <TooltipContent>Deshacer (Ctrl Z)</TooltipContent>
                    </Tooltip>
                    <Tooltip>
                        <TooltipTrigger as-child>
                            <button type="button" class="grid size-9 place-items-center rounded-md hover:bg-muted disabled:opacity-35" :disabled="!store.state.future.length" aria-label="Rehacer" @click="store.redo()"><Redo2 class="size-4" /></button>
                        </TooltipTrigger>
                        <TooltipContent>Rehacer (Ctrl ⇧ Z)</TooltipContent>
                    </Tooltip>
                    <Tooltip>
                        <TooltipTrigger as-child>
                            <button type="button" :aria-pressed="!!compare" class="hidden size-9 place-items-center rounded-md hover:bg-muted lg:grid" :class="{ 'bg-brand/20 text-brand': compare }" aria-label="Comparar con lo publicado" @click="togglePublishedCompare">
                                <GitCompareArrows class="size-4" />
                            </button>
                        </TooltipTrigger>
                        <TooltipContent>{{ compare ? 'Volver al borrador' : 'Ver lo publicado' }}</TooltipContent>
                    </Tooltip>
                    <Tooltip>
                        <TooltipTrigger as-child>
                            <button type="button" :aria-pressed="interact" class="hidden size-9 place-items-center rounded-md hover:bg-muted lg:grid" :class="{ 'bg-brand/20 text-brand': interact }" aria-label="Probar interacciones" @click="interact = !interact">
                                <Hand class="size-4" />
                            </button>
                        </TooltipTrigger>
                        <TooltipContent>{{ interact ? 'Volver a modo edición' : 'Probar carruseles y botones' }}</TooltipContent>
                    </Tooltip>

                    <DropdownMenu>
                        <DropdownMenuTrigger class="grid size-9 place-items-center rounded-md hover:bg-muted" aria-label="Más opciones">
                            <MoreHorizontal class="size-4" />
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" class="w-60">
                            <DropdownMenuItem @select="versionsOpen = true"><History class="size-4" /> Historial de versiones</DropdownMenuItem>
                            <DropdownMenuItem @select="settingsOpen = true"><Settings2 class="size-4" /> Ajustes de la página</DropdownMenuItem>
                            <DropdownMenuItem @select="share"><Share2 class="size-4" /> Compartir vista previa</DropdownMenuItem>
                            <DropdownMenuItem class="lg:hidden" @select="togglePublishedCompare"><GitCompareArrows class="size-4" /> {{ compare ? 'Volver al borrador' : 'Ver lo publicado' }}</DropdownMenuItem>
                            <DropdownMenuItem class="lg:hidden" @select="interact = !interact"><Hand class="size-4" /> {{ interact ? 'Modo edición' : 'Probar interacciones' }}</DropdownMenuItem>
                            <DropdownMenuItem @select="showSpacing = !showSpacing"><Ruler class="size-4" /> {{ showSpacing ? 'Ocultar' : 'Mostrar' }} espaciados</DropdownMenuItem>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem :disabled="!published" class="text-destructive" @select="discardOpen = true">Descartar borrador</DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>

                    <button type="button" class="hidden h-9 items-center gap-1.5 rounded-md border px-3 text-sm font-medium hover:bg-muted sm:inline-flex" @click="openPreview">
                        <Eye class="size-4" /> Vista previa
                    </button>
                    <button
                        type="button"
                        class="inline-flex h-9 items-center gap-1.5 rounded-md bg-brand px-3 text-sm font-semibold text-brand-foreground hover:bg-brand/90 disabled:opacity-50"
                        :disabled="!!compare"
                        @click="openPublish"
                    >
                        <Rocket class="size-4" />
                        <span class="hidden sm:inline">Publicar</span>
                        <span v-if="hasUnpublished" class="size-1.5 rounded-full bg-brand-foreground" aria-label="Hay cambios sin publicar" />
                    </button>
                    <button v-if="!wide" type="button" class="grid size-9 place-items-center rounded-md hover:bg-muted" :class="{ 'bg-muted': rightOpen }" aria-label="Propiedades" @click="rightOpen = !rightOpen">
                        <PanelRight class="size-4" />
                    </button>
                </div>
            </header>

            <!-- Avisos -->
            <div v-if="store.state.status === 'conflict'" class="flex flex-wrap items-center gap-3 border-b bg-destructive/15 px-4 py-2 text-sm" role="alert">
                <TriangleAlert class="size-4 text-destructive" />
                Otra sesión guardó cambios en esta página mientras editabas.
                <button type="button" class="font-semibold underline" @click="reloadFromServer">Cargar su versión</button>
                <button type="button" class="font-semibold underline" @click="overwrite">Conservar la mía</button>
            </div>
            <div v-else-if="store.state.status === 'error'" class="flex flex-wrap items-center gap-2 border-b bg-warning/15 px-4 py-2 text-sm" role="alert">
                <TriangleAlert class="size-4 text-warning" />
                {{ Object.values(store.state.errors)[0] ?? 'No se pudo guardar.' }}
                <button type="button" class="font-semibold underline" @click="store.save(true)">Reintentar</button>
            </div>
            <div v-if="compare" class="flex items-center gap-3 border-b bg-brand/15 px-4 py-2 text-sm">
                <Columns2 class="size-4 text-brand" /> {{ compare.label }}
                <button type="button" class="ml-auto font-semibold underline" @click="compare = null">Volver al borrador</button>
            </div>

            <!-- Áreas de trabajo -->
            <div class="relative flex min-h-0 flex-1">
                <div
                    v-show="leftOpen"
                    class="z-20 w-[284px] shrink-0 border-r bg-background"
                    :class="{ 'absolute inset-y-0 left-0 shadow-2xl': !wide }"
                >
                    <StructurePanel @add-section="openAddSection" />
                </div>

                <main class="relative min-w-0 flex-1">
                    <CanvasFrame ref="canvas" :src="canvasUrl" :device-width="deviceWidth" @message="onCanvasMessage" />
                </main>

                <div
                    v-show="rightOpen"
                    class="z-20 w-[328px] shrink-0 border-l bg-background"
                    :class="{ 'absolute inset-y-0 right-0 shadow-2xl': !wide }"
                >
                    <InspectorPanel @replay="(id: string) => canvas?.post({ type: 'studio:replay', id })" />
                </div>
            </div>
        </div>

        <AddBlockDialog v-model:open="addOpen" :target="addTarget" />
        <MediaPickerDialog v-model:open="mediaPickerOpen" :type="mediaPickerType" @select="onMediaPicked" />
        <PublishDialog
            v-model:open="publishOpen"
            :issues="issues"
            :server-problems="serverProblems"
            :publishing="publishing"
            :page-title="page.title"
            @publish="publish"
            @focus="(id: string) => { store.select(id); publishOpen = false; }"
        />
        <VersionsSheet
            v-model:open="versionsOpen"
            :versions="versions"
            :draft-updated-at="store.state.lastSavedAt ?? page.draftUpdatedAt"
            :draft-updated-by="page.draftUpdatedBy"
            :has-changes="hasUnpublished"
            @view="viewVersion"
            @restore="restore"
        />
        <PageSettingsDialog v-model:open="settingsOpen" :page="page" :url="studioRoutes.ajustes(page.id).url" @saved="(p: StudioPageMeta) => (page = { ...page, ...p })" />

        <AlertDialog v-model:open="discardOpen">
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>¿Descartar el borrador?</AlertDialogTitle>
                    <AlertDialogDescription>
                        Volverás a la versión publicada. Puedes deshacerlo con Ctrl Z mientras no salgas del editor.
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel>Cancelar</AlertDialogCancel>
                    <AlertDialogAction @click="discard">Descartar</AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
        <Toaster />
    </TooltipProvider>
</template>
