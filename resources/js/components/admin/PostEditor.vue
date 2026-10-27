<script setup lang="ts">
import {
    Bold,
    Heading2,
    Heading3,
    Image as ImageIcon,
    Italic,
    Link2,
    List,
    ListOrdered,
    MessageCircleQuestion,
    Minus,
    Pilcrow,
    Quote,
    Redo2,
    Sparkles,
    Underline as UnderlineIcon,
    Undo2,
    Video as VideoIcon,
} from '@lucide/vue';
import { EditorContent, useEditor } from '@tiptap/vue-3';
import type { Component } from 'vue';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import MediaPickerDialog, {
    type PickedMedia,
} from '@/components/admin/MediaPickerDialog.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Toggle } from '@/components/ui/toggle';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { createPostExtensions } from '@/lib/tiptap/extensions';
import { cn } from '@/lib/utils';
import type { TiptapContent } from '@/types';

/**
 * Editor de contenido enriquecido (Tiptap) para publicaciones y páginas.
 * - `variant="boxed"` (por defecto): caja con borde, toolbar integrada.
 * - `variant="seamless"`: sin caja, para incrustarse en una hoja de escritura.
 * La toolbar es sticky; `toolbarStickyClass` ajusta su desplazamiento.
 */
const props = withDefaults(
    defineProps<{
        variant?: 'boxed' | 'seamless';
        toolbarStickyClass?: string;
    }>(),
    { variant: 'boxed', toolbarStickyClass: 'top-14' },
);

const content = defineModel<TiptapContent | null>('content', { default: null });

const words = ref(0);

function countWords(text: string): number {
    const trimmed = text.trim();

    return trimmed ? trimmed.split(/\s+/).length : 0;
}

const editor = useEditor({
    content: content.value ?? '<p></p>',
    extensions: createPostExtensions(true),
    editorProps: {
        attributes: {
            class: 'prose-editor min-h-[360px] focus:outline-none',
            'aria-label': 'Contenido',
            'aria-multiline': 'true',
            role: 'textbox',
        },
    },
    onCreate: ({ editor: e }) => {
        words.value = countWords(e.getText());
    },
    onUpdate: ({ editor: e }) => {
        content.value = e.getJSON();
        words.value = countWords(e.getText());
    },
});

watch(content, (value) => {
    if (!editor.value) return;
    const current = JSON.stringify(editor.value.getJSON());
    const incoming = JSON.stringify(value);
    if (current !== incoming) {
        editor.value.commands.setContent(value ?? '<p></p>', {
            emitUpdate: false,
        });
        words.value = countWords(editor.value.getText());
    }
});

onBeforeUnmount(() => editor.value?.destroy());

const readingMinutes = computed(() => Math.max(1, Math.round(words.value / 220)));

/* ---------- Multimedia ---------- */

const imagePickerOpen = ref(false);
const videoPickerOpen = ref(false);

function onImageSelected(media: PickedMedia) {
    if (!media.url) return;
    editor.value
        ?.chain()
        .focus()
        .setImage({ src: media.url, alt: media.altText ?? media.filename })
        .run();
}

function onVideoSelected(media: PickedMedia) {
    if (!media.url) return;
    editor.value?.commands.insertVideoBlock({
        mediaId: media.id,
        src: media.url,
        poster: media.posterUrl ?? null,
    });
}

/* ---------- Enlaces ---------- */

const linkOpen = ref(false);
const linkUrl = ref('');
const linkIsEditing = ref(false);

function openLinkDialog() {
    const current = editor.value?.getAttributes('link').href as
        | string
        | undefined;
    linkUrl.value = current ?? '';
    linkIsEditing.value = !!current;
    linkOpen.value = true;
}

function applyLink() {
    const url = linkUrl.value.trim();
    if (!url) {
        removeLink();

        return;
    }
    editor.value
        ?.chain()
        .focus()
        .extendMarkRange('link')
        .setLink({ href: url })
        .run();
    linkOpen.value = false;
}

function removeLink() {
    editor.value?.chain().focus().extendMarkRange('link').unsetLink().run();
    linkOpen.value = false;
}

/* ---------- Toolbar ---------- */

interface ToolItem {
    key: string;
    label: string;
    shortcut?: string;
    icon: Component;
    text?: string;
    active?: () => boolean;
    disabled?: () => boolean;
    run: () => void;
}

const isMac =
    typeof navigator !== 'undefined' && /Mac|iPhone|iPad/.test(navigator.platform);
const mod = isMac ? '⌘' : 'Ctrl';

const groups = computed<ToolItem[][]>(() => {
    const e = editor.value;
    if (!e) return [];

    return [
        [
            {
                key: 'undo',
                label: 'Deshacer',
                shortcut: `${mod}+Z`,
                icon: Undo2,
                disabled: () => !e.can().undo(),
                run: () => e.chain().focus().undo().run(),
            },
            {
                key: 'redo',
                label: 'Rehacer',
                shortcut: `${mod}+Shift+Z`,
                icon: Redo2,
                disabled: () => !e.can().redo(),
                run: () => e.chain().focus().redo().run(),
            },
        ],
        [
            {
                key: 'paragraph',
                label: 'Texto normal',
                icon: Pilcrow,
                active: () => e.isActive('paragraph'),
                run: () => e.chain().focus().setParagraph().run(),
            },
            {
                key: 'h2',
                label: 'Subtítulo',
                icon: Heading2,
                active: () => e.isActive('heading', { level: 2 }),
                run: () => e.chain().focus().toggleHeading({ level: 2 }).run(),
            },
            {
                key: 'h3',
                label: 'Subtítulo menor',
                icon: Heading3,
                active: () => e.isActive('heading', { level: 3 }),
                run: () => e.chain().focus().toggleHeading({ level: 3 }).run(),
            },
        ],
        [
            {
                key: 'bold',
                label: 'Negritas',
                shortcut: `${mod}+B`,
                icon: Bold,
                active: () => e.isActive('bold'),
                run: () => e.chain().focus().toggleBold().run(),
            },
            {
                key: 'italic',
                label: 'Cursivas',
                shortcut: `${mod}+I`,
                icon: Italic,
                active: () => e.isActive('italic'),
                run: () => e.chain().focus().toggleItalic().run(),
            },
            {
                key: 'underline',
                label: 'Subrayado',
                shortcut: `${mod}+U`,
                icon: UnderlineIcon,
                active: () => e.isActive('underline'),
                run: () => e.chain().focus().toggleUnderline().run(),
            },
            {
                key: 'link',
                label: 'Enlace',
                icon: Link2,
                active: () => e.isActive('link'),
                run: openLinkDialog,
            },
        ],
        [
            {
                key: 'bullet',
                label: 'Lista con viñetas',
                icon: List,
                active: () => e.isActive('bulletList'),
                run: () => e.chain().focus().toggleBulletList().run(),
            },
            {
                key: 'ordered',
                label: 'Lista numerada',
                icon: ListOrdered,
                active: () => e.isActive('orderedList'),
                run: () => e.chain().focus().toggleOrderedList().run(),
            },
            {
                key: 'quote',
                label: 'Cita',
                icon: Quote,
                active: () => e.isActive('blockquote'),
                run: () => e.chain().focus().toggleBlockquote().run(),
            },
            {
                key: 'hr',
                label: 'Separador',
                icon: Minus,
                run: () => e.chain().focus().setHorizontalRule().run(),
            },
        ],
        [
            {
                key: 'image',
                label: 'Insertar imagen de la biblioteca',
                icon: ImageIcon,
                run: () => (imagePickerOpen.value = true),
            },
            {
                key: 'video',
                label: 'Insertar video de la biblioteca',
                icon: VideoIcon,
                run: () => (videoPickerOpen.value = true),
            },
        ],
        [
            {
                key: 'tip',
                label: 'Insertar bloque «EL TIP»',
                icon: Sparkles,
                text: 'EL TIP',
                run: () => e.commands.insertTipBlock(),
            },
            {
                key: 'question',
                label: 'Insertar pregunta final al lector',
                icon: MessageCircleQuestion,
                text: 'Pregunta',
                run: () => e.commands.insertQuestionBlock(),
            },
        ],
    ];
});
</script>

<template>
    <div
        :class="
            cn(
                'flex flex-col',
                props.variant === 'boxed' && 'rounded-xl border bg-card',
            )
        "
    >
        <div
            v-if="editor"
            role="toolbar"
            aria-label="Formato del contenido"
            :class="
                cn(
                    'sticky z-10 flex flex-wrap items-center gap-1 border-b bg-card/95 px-1.5 py-1.5 backdrop-blur supports-[backdrop-filter]:bg-card/80',
                    props.variant === 'boxed'
                        ? 'rounded-t-xl'
                        : 'rounded-lg border shadow-xs',
                    props.toolbarStickyClass,
                )
            "
        >
            <template v-for="(group, gi) in groups" :key="gi">
                <span
                    v-if="gi > 0"
                    class="mx-0.5 h-6 w-px shrink-0 bg-border"
                    aria-hidden="true"
                />
                <div class="flex items-center gap-0.5">
                    <Tooltip v-for="item in group" :key="item.key">
                        <TooltipTrigger as-child>
                            <Toggle
                                :pressed="item.active?.() ?? false"
                                :disabled="item.disabled?.() ?? false"
                                :aria-label="item.label"
                                :class="
                                    cn(
                                        'h-9 min-w-9 px-2 text-muted-foreground hover:bg-muted hover:text-foreground pointer-coarse:h-11 pointer-coarse:min-w-11',
                                        'data-[state=on]:bg-brand/15 data-[state=on]:text-foreground',
                                        item.text && 'gap-1.5 px-2.5 text-xs font-semibold',
                                    )
                                "
                                @update:pressed="item.run()"
                            >
                                <component :is="item.icon" class="size-4" />
                                <span v-if="item.text" class="hidden sm:inline">{{
                                    item.text
                                }}</span>
                            </Toggle>
                        </TooltipTrigger>
                        <TooltipContent side="bottom">
                            {{ item.label }}
                            <kbd
                                v-if="item.shortcut"
                                class="ml-1.5 font-sans opacity-70"
                                >{{ item.shortcut }}</kbd
                            >
                        </TooltipContent>
                    </Tooltip>
                </div>
            </template>
        </div>

        <EditorContent
            :editor="editor"
            :class="
                cn(
                    'cursor-text',
                    props.variant === 'boxed' ? 'px-5 py-4 md:px-8 md:py-6' : 'pt-6',
                )
            "
        />

        <div
            :class="
                cn(
                    'flex items-center justify-end gap-2 text-xs text-muted-foreground tabular-nums',
                    props.variant === 'boxed' ? 'border-t px-5 py-2' : 'pt-4',
                )
            "
            aria-live="polite"
        >
            {{ words }} {{ words === 1 ? 'palabra' : 'palabras' }}
            <span aria-hidden="true">·</span>
            {{ readingMinutes }} min de lectura
        </div>

        <MediaPickerDialog
            v-model:open="imagePickerOpen"
            type="image"
            @select="onImageSelected"
        />
        <MediaPickerDialog
            v-model:open="videoPickerOpen"
            type="video"
            @select="onVideoSelected"
        />

        <Dialog v-model:open="linkOpen">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>{{
                        linkIsEditing ? 'Editar enlace' : 'Insertar enlace'
                    }}</DialogTitle>
                    <DialogDescription
                        >Selecciona texto antes de insertar el enlace. Ej.:
                        https://instagram.com/mrinsight</DialogDescription
                    >
                </DialogHeader>
                <form class="flex flex-col gap-4" @submit.prevent="applyLink">
                    <div class="flex flex-col gap-1.5">
                        <Label for="post-editor-link">Dirección (URL)</Label>
                        <Input
                            id="post-editor-link"
                            v-model="linkUrl"
                            type="url"
                            inputmode="url"
                            placeholder="https://"
                            autocomplete="off"
                        />
                    </div>
                    <DialogFooter class="gap-2">
                        <Button
                            v-if="linkIsEditing"
                            type="button"
                            variant="ghost"
                            class="sm:mr-auto"
                            @click="removeLink"
                            >Quitar enlace</Button
                        >
                        <Button
                            type="button"
                            variant="outline"
                            @click="linkOpen = false"
                            >Cancelar</Button
                        >
                        <Button type="submit">Aplicar</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>

<style>
/*
 * Tipografía del contenido en edición: replica la del cuerpo público
 * (components/public/PostBody.vue) con tokens del admin.
 */
.prose-editor {
    max-width: 68ch;
    font-size: 1.0625rem;
    line-height: 1.7;
    color: var(--foreground);
}
.prose-editor > :first-child {
    margin-top: 0;
}
.prose-editor :is(h2, h3, h4) {
    font-family: var(--font-display, Fraunces, ui-serif, Georgia, serif);
    font-weight: 600;
    line-height: 1.2;
    letter-spacing: -0.01em;
    margin-top: 1.4em;
    margin-bottom: 0.5em;
}
.prose-editor h2 {
    font-size: 1.625rem;
}
.prose-editor h3 {
    font-size: 1.3125rem;
}
.prose-editor h4 {
    font-size: 1.125rem;
}
.prose-editor p {
    margin: 1em 0;
}
.prose-editor a {
    color: var(--brand);
    text-decoration: underline;
    text-underline-offset: 3px;
}
.prose-editor ul,
.prose-editor ol {
    padding-left: 1.5em;
    margin: 1em 0;
}
.prose-editor ul {
    list-style: disc;
}
.prose-editor ol {
    list-style: decimal;
}
.prose-editor li > p {
    margin: 0.25em 0;
}
.prose-editor blockquote {
    border-left: 3px solid var(--brand);
    padding-left: 1em;
    margin: 1.4em 0;
    font-style: italic;
    color: var(--muted-foreground);
}
.prose-editor hr {
    border: 0;
    border-top: 1px solid var(--border);
    margin: 2em 0;
}
.prose-editor hr.ProseMirror-selectednode,
.prose-editor img.ProseMirror-selectednode,
.prose-editor .video-block.ProseMirror-selectednode {
    outline: 2px solid var(--brand);
    outline-offset: 2px;
}
.prose-editor img {
    border-radius: 0.75rem;
    max-width: 100%;
    height: auto;
    margin: 1.5em 0;
}
.prose-editor video {
    width: 100%;
    border-radius: 0.75rem;
}
.prose-editor .video-block {
    margin: 1.5em 0;
    border-radius: 0.75rem;
}
.prose-editor .tip-block {
    border-left: 4px solid var(--brand);
    background: color-mix(in srgb, var(--brand) 12%, transparent);
    border-radius: 0.75rem;
    padding: 1.25rem 1.5rem;
    margin: 1.75em 0;
}
.prose-editor .tip-block::before {
    content: 'EL TIP';
    display: block;
    font-size: 0.75rem;
    font-weight: 700;
    letter-spacing: 0.12em;
    color: var(--brand);
    margin-bottom: 0.5em;
}
.prose-editor .tip-block > p:first-of-type {
    margin-top: 0;
}
.prose-editor .tip-block > p:last-child {
    margin-bottom: 0;
}
.prose-editor .question-block {
    border: 1px dashed var(--brand);
    border-radius: 0.75rem;
    padding: 1.25rem 1.5rem;
    margin: 1.75em 0;
    font-family: var(--font-display, Fraunces, ui-serif, Georgia, serif);
    font-size: 1.2rem;
}
.prose-editor .question-block > p {
    margin: 0;
}
.prose-editor p.is-editor-empty:first-child::before {
    content: attr(data-placeholder);
    float: left;
    height: 0;
    pointer-events: none;
    color: var(--muted-foreground);
}
</style>
