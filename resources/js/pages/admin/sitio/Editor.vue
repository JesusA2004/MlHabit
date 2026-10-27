<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import type { Component, VNode } from 'vue';
import { computed, onBeforeUnmount, onMounted, provide, ref } from 'vue';
import { toast } from 'vue-sonner';
import AppToaster from '@/components/ds/AppToaster.vue';
import '@/../css/site-builder.css';
import '@/../css/site-builder-editor.css';
import { editorBridgeKey } from '@/components/site-builder/context';
import type { EditorBridge } from '@/components/site-builder/context';
import EditorCanvas from '@/components/site-builder/editor/EditorCanvas.vue';
import EditorTopBar from '@/components/site-builder/editor/EditorTopBar.vue';
import ElementsPanel from '@/components/site-builder/editor/ElementsPanel.vue';
import HistorySheet from '@/components/site-builder/editor/HistorySheet.vue';
import Inspector from '@/components/site-builder/editor/Inspector.vue';
import { editorKey } from '@/components/site-builder/editor/keys';
import PublishDialog from '@/components/site-builder/editor/PublishDialog.vue';
import SectionsPanel from '@/components/site-builder/editor/SectionsPanel.vue';
import TemplatePicker from '@/components/site-builder/editor/TemplatePicker.vue';
import { createEditor } from '@/components/site-builder/editor/useEditor';
import type {
    BlockType,
    DraftMeta,
    DynamicDescriptor,
    LibraryMap,
    RenderSection,
    SectionTemplate,
    VersionRow,
} from '@/components/site-builder/types';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { TooltipProvider } from '@/components/ui/tooltip';
import { useChunkedUpload } from '@/composables/useChunkedUpload';
import editorRoutes from '@/routes/admin/sitio/editor';
import versiones from '@/routes/admin/sitio/editor/versiones';
import type { VisitPolicyData } from '@/types/web';

/**
 * Editor visual de la portada (pantalla completa). El dueño acomoda en
 * escritorio; tableta y móvil se calculan solos y se pueden revisar con el
 * selector de dispositivo.
 */
const props = defineProps<{
    page: { slug: string; name: string; publicUrl: string };
    draft: DraftMeta;
    sections: RenderSection[];
    library: LibraryMap;
    templates: SectionTemplate[];
    dynamic: Record<string, DynamicDescriptor>;
    icons: string[];
    versions: VersionRow[];
    previewUrl: string;
    visitPolicy: VisitPolicyData | null;
    can: { publish: boolean; upload: boolean; library: boolean };
}>();

defineOptions({
    // Pantalla completa: sin la barra lateral del panel.
    layout: (
        _h: (component: Component, children: Component[]) => VNode,
        page: Component,
    ) => page as unknown as VNode,
});

const editor = createEditor({
    sections: props.sections,
    draft: props.draft,
    library: props.library,
    versions: props.versions,
    dynamic: props.dynamic,
    urls: {
        save: editorRoutes.borrador().url,
        publish: editorRoutes.publicar().url,
        discard: editorRoutes.descartar().url,
        restore: (id: number) => versiones.restaurar(id).url,
    },
});
provide(editorKey, editor);

const dragPayload = ref<
    { kind: 'block'; type: BlockType } | { kind: 'media'; id: number } | null
>(null);
provide('sb-drag-payload', dragPayload);

const bridge: EditorBridge = {
    library: computed(() => editor.library.value),
    isSelected: (sectionKey, id) =>
        editor.selection.section === sectionKey &&
        editor.selection.blocks.includes(id),
    isPrimary: (sectionKey, id) =>
        editor.selection.section === sectionKey &&
        editor.selection.blocks[editor.selection.blocks.length - 1] === id,
    textEditing: computed(() => editor.textEditing.value),
    cropping: computed(() => editor.cropping.value),
    canInteract: editor.canInteract,
    transient: editor.transient,
    dropTarget: computed(() => editor.dropTarget.value),
    pointerDown: editor.pointerDown,
    resizeStart: editor.resizeStart,
    doubleClick: editor.doubleClick,
    focusBlock: (sectionKey, block, additive) =>
        editor.selectBlock(sectionKey, block.id, additive),
    commitText: editor.commitText,
    endTextEdit: () => (editor.textEditing.value = null),
    updateCrop: editor.updateCrop,
    endCrop: () => (editor.cropping.value = null),
    zoom: editor.zoom,
};
provide(editorBridgeKey, bridge);

// ----- Paneles -----------------------------------------------------------
const leftTab = ref<'secciones' | 'elementos'>('secciones');
const pickerOpen = ref(false);
const pickerIndex = ref(0);
const publishOpen = ref(false);
const historyOpen = ref(false);

function openPicker(index: number) {
    pickerIndex.value = index;
    pickerOpen.value = true;
}

function onPickTemplate(template: SectionTemplate) {
    editor.addSection(template, pickerIndex.value);
    requestAnimationFrame(() => {
        document
            .querySelector(`[data-section-key="${editor.selection.section}"]`)
            ?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
}

// ----- Subir fotos desde el escritorio ------------------------------------
const canUpload = computed(() => props.can.upload);
const uploader = useChunkedUpload();

async function uploadFiles(
    files: File[],
    sectionKey?: string,
    at?: { x: number; y: number },
    targetBlock: string | null = null,
) {
    if (!canUpload.value) {
        toast.error(
            'Necesitas permiso de multimedia para subir fotos al sitio.',
        );

        return;
    }
    const key =
        sectionKey ?? editor.selection.section ?? editor.sections.value[0]?.key;
    if (!key) return;

    for (const [i, file] of files.entries()) {
        let blockId = targetBlock && i === 0 ? targetBlock : null;
        if (!blockId) {
            const position = at ? { x: at.x, y: at.y + i * 2 } : undefined;
            blockId = editor.addBlock(key, 'image', position, {
                mediaId: null,
            });
        }
        if (!blockId) continue;
        const toastId = toast.loading(`Subiendo ${file.name}…`);
        try {
            const result = await uploader.upload(file, 'sitio/editor', {
                visibility: 'public',
            });
            editor.library.value = {
                ...editor.library.value,
                [String(result.id)]: {
                    id: result.id,
                    url: result.url,
                    thumbnailUrl: result.url,
                    filename: result.filename,
                    type: result.type,
                    altText: null,
                    origin: null,
                    originLabel: null,
                    width: null,
                    height: null,
                },
            };
            editor.updateProps(key, blockId, {
                mediaId: result.id,
                focalX: 50,
                focalY: 50,
                zoom: 1,
            });
            toast.success('Foto agregada', { id: toastId });
        } catch (e) {
            toast.error(
                e instanceof Error && e.message
                    ? e.message
                    : 'No se pudo subir la foto.',
                { id: toastId },
            );
        }
    }
}

// ----- Teclado y salida -----------------------------------------------------
onMounted(() => {
    window.addEventListener('keydown', editor.onKeydown);
    window.addEventListener('beforeunload', editor.onBeforeUnload);
});
onBeforeUnmount(() => {
    window.removeEventListener('keydown', editor.onKeydown);
    window.removeEventListener('beforeunload', editor.onBeforeUnload);
    void editor.flush();
});

const canLibrary = computed(() => props.can.library);

function reload() {
    window.location.reload();
}
</script>

<template>
    <Head :title="`Editor visual · ${props.page.name}`" />

    <TooltipProvider :delay-duration="300">
        <div
            class="sb-editor fixed inset-0 flex flex-col bg-background text-foreground"
        >
            <EditorTopBar
                :page-name="props.page.name"
                :preview-url="props.previewUrl"
                :can-publish="props.can.publish"
                @publish="publishOpen = true"
                @history="historyOpen = true"
            />

            <div
                v-if="editor.saveState.value === 'conflict'"
                role="alert"
                class="flex items-center justify-center gap-3 bg-warning-soft px-4 py-2 text-sm text-warning-ink"
            >
                {{
                    editor.conflictMessage.value ??
                    'Hay una versión más reciente del borrador.'
                }}
                <button
                    type="button"
                    class="font-semibold underline underline-offset-4"
                    @click="reload"
                >
                    Recargar
                </button>
            </div>

            <div class="flex min-h-0 flex-1">
                <!-- Panel izquierdo -->
                <aside
                    class="flex w-[17rem] shrink-0 flex-col border-r border-border bg-card"
                    aria-label="Secciones y elementos"
                >
                    <Tabs
                        v-model="leftTab"
                        class="flex min-h-0 flex-1 flex-col gap-0"
                    >
                        <div class="border-b border-border px-3 py-2.5">
                            <TabsList class="grid w-full grid-cols-2">
                                <TabsTrigger value="secciones"
                                    >Secciones</TabsTrigger
                                >
                                <TabsTrigger value="elementos"
                                    >Elementos</TabsTrigger
                                >
                            </TabsList>
                        </div>
                        <TabsContent
                            value="secciones"
                            class="flex min-h-0 flex-1 flex-col"
                        >
                            <SectionsPanel @add="openPicker" />
                        </TabsContent>
                        <TabsContent
                            value="elementos"
                            class="flex min-h-0 flex-1 flex-col"
                        >
                            <ElementsPanel
                                :can-library="canLibrary"
                                :can-upload="canUpload"
                                @upload="(files) => uploadFiles(files)"
                            />
                        </TabsContent>
                    </Tabs>
                </aside>

                <!-- Lienzo -->
                <main
                    class="relative flex min-w-0 flex-1 flex-col"
                    aria-label="Lienzo de la página"
                >
                    <EditorCanvas
                        @add-section="openPicker"
                        @drop-files="
                            (files, key, at, target) =>
                                uploadFiles(files, key, at, target)
                        "
                    />
                    <p
                        v-if="editor.device.value !== 'desktop'"
                        class="pointer-events-none absolute bottom-4 left-1/2 -translate-x-1/2 rounded-full bg-espresso/90 px-4 py-2 text-xs text-white shadow-lg"
                    >
                        Vista
                        {{
                            editor.device.value === 'tablet'
                                ? 'de tableta'
                                : 'móvil'
                        }}: se acomoda sola. Para mover elementos vuelve a
                        escritorio.
                    </p>
                </main>

                <!-- Panel derecho -->
                <aside
                    class="flex w-[20.5rem] shrink-0 flex-col border-l border-border bg-card"
                    aria-label="Propiedades"
                >
                    <Inspector :visit-policy="props.visitPolicy" />
                </aside>
            </div>
        </div>

        <TemplatePicker
            v-model:open="pickerOpen"
            :templates="props.templates"
            @pick="onPickTemplate"
        />
        <PublishDialog v-model:open="publishOpen" />
        <HistorySheet v-model:open="historyOpen" />
        <AppToaster />
    </TooltipProvider>
</template>
