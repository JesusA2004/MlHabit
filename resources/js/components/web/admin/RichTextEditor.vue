<script setup lang="ts">
import Link from '@tiptap/extension-link';
import Placeholder from '@tiptap/extension-placeholder';
import StarterKit from '@tiptap/starter-kit';
import { EditorContent, useEditor } from '@tiptap/vue-3';
import {
    Bold,
    Heading2,
    Heading3,
    Italic,
    Link2,
    List,
    ListOrdered,
    Quote,
    Redo2,
    Undo2,
    Unlink,
} from '@lucide/vue';
import { onBeforeUnmount, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { cn } from '@/lib/utils';

/**
 * Editor de texto enriquecido (Tiptap) para páginas legales, descripciones
 * e historias. Solo formatos básicos; el servidor sanea el HTML al guardar.
 */
const model = defineModel<string>({ default: '' });

const props = withDefaults(
    defineProps<{
        placeholder?: string;
        invalid?: boolean;
        minHeight?: string;
        id?: string;
    }>(),
    {
        placeholder: 'Escribe aquí…',
        invalid: false,
        minHeight: 'min-h-64',
    },
);

const editor = useEditor({
    content: model.value,
    extensions: [
        StarterKit.configure({
            heading: { levels: [2, 3] },
            code: false,
            codeBlock: false,
            strike: false,
            link: false,
        }),
        Link.configure({
            openOnClick: false,
            autolink: true,
            protocols: ['https', 'mailto', 'tel'],
        }),
        Placeholder.configure({ placeholder: props.placeholder }),
    ],
    editorProps: {
        attributes: {
            class: 'outline-none',
            'aria-multiline': 'true',
            role: 'textbox',
            ...(props.id ? { id: props.id } : {}),
        },
    },
    onUpdate: ({ editor: e }) => {
        model.value = e.isEmpty ? '' : e.getHTML();
    },
});

watch(model, (value) => {
    if (
        editor.value &&
        value !== editor.value.getHTML() &&
        !(value === '' && editor.value.isEmpty)
    ) {
        editor.value.commands.setContent(value, { emitUpdate: false });
    }
});

onBeforeUnmount(() => editor.value?.destroy());

const linkOpen = ref(false);
const linkUrl = ref('');

function openLink() {
    linkUrl.value =
        (editor.value?.getAttributes('link').href as string | undefined) ?? '';
}

function applyLink() {
    const url = linkUrl.value.trim();
    if (!editor.value) return;
    if (url === '') {
        editor.value.chain().focus().unsetLink().run();
    } else {
        const href = /^(https:|mailto:|tel:|\/)/i.test(url)
            ? url
            : `https://${url}`;
        editor.value
            .chain()
            .focus()
            .extendMarkRange('link')
            .setLink({ href })
            .run();
    }
    linkOpen.value = false;
}

type Action = {
    label: string;
    icon: unknown;
    run: () => void;
    active?: () => boolean;
};

const actions: Action[] = [
    {
        label: 'Negritas',
        icon: Bold,
        run: () => editor.value?.chain().focus().toggleBold().run(),
        active: () => !!editor.value?.isActive('bold'),
    },
    {
        label: 'Cursivas',
        icon: Italic,
        run: () => editor.value?.chain().focus().toggleItalic().run(),
        active: () => !!editor.value?.isActive('italic'),
    },
    {
        label: 'Subtítulo',
        icon: Heading2,
        run: () =>
            editor.value?.chain().focus().toggleHeading({ level: 2 }).run(),
        active: () => !!editor.value?.isActive('heading', { level: 2 }),
    },
    {
        label: 'Subtítulo menor',
        icon: Heading3,
        run: () =>
            editor.value?.chain().focus().toggleHeading({ level: 3 }).run(),
        active: () => !!editor.value?.isActive('heading', { level: 3 }),
    },
    {
        label: 'Lista',
        icon: List,
        run: () => editor.value?.chain().focus().toggleBulletList().run(),
        active: () => !!editor.value?.isActive('bulletList'),
    },
    {
        label: 'Lista numerada',
        icon: ListOrdered,
        run: () => editor.value?.chain().focus().toggleOrderedList().run(),
        active: () => !!editor.value?.isActive('orderedList'),
    },
    {
        label: 'Cita',
        icon: Quote,
        run: () => editor.value?.chain().focus().toggleBlockquote().run(),
        active: () => !!editor.value?.isActive('blockquote'),
    },
];
</script>

<template>
    <div
        :class="
            cn(
                'overflow-hidden rounded-lg border bg-card focus-within:ring-3 focus-within:ring-ring/40',
                props.invalid ? 'border-danger' : 'border-input',
            )
        "
    >
        <div
            role="toolbar"
            aria-label="Formato de texto"
            class="flex flex-wrap items-center gap-0.5 border-b border-border bg-muted/40 p-1"
        >
            <Button
                v-for="action in actions"
                :key="action.label"
                type="button"
                variant="ghost"
                size="icon"
                :class="
                    cn(
                        'size-8',
                        action.active?.() && 'bg-accent text-foreground',
                    )
                "
                :aria-label="action.label"
                :aria-pressed="action.active?.() ?? false"
                @click="action.run"
            >
                <component :is="action.icon" class="size-4" />
            </Button>
            <Popover
                v-model:open="linkOpen"
                @update:open="(v: boolean) => v && openLink()"
            >
                <PopoverTrigger as-child>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        :class="
                            cn(
                                'size-8',
                                editor?.isActive('link') && 'bg-accent',
                            )
                        "
                        aria-label="Enlace"
                    >
                        <Link2 class="size-4" />
                    </Button>
                </PopoverTrigger>
                <PopoverContent class="w-80">
                    <form class="flex gap-2" @submit.prevent="applyLink">
                        <Input
                            v-model="linkUrl"
                            placeholder="https://… o /ruta"
                            aria-label="Dirección del enlace"
                        />
                        <Button type="submit" size="sm">Aplicar</Button>
                    </form>
                </PopoverContent>
            </Popover>
            <Button
                v-if="editor?.isActive('link')"
                type="button"
                variant="ghost"
                size="icon"
                class="size-8"
                aria-label="Quitar enlace"
                @click="editor?.chain().focus().unsetLink().run()"
            >
                <Unlink class="size-4" />
            </Button>
            <span class="mx-1 h-5 w-px bg-border" aria-hidden="true" />
            <Button
                type="button"
                variant="ghost"
                size="icon"
                class="size-8"
                aria-label="Deshacer"
                @click="editor?.chain().focus().undo().run()"
                ><Undo2 class="size-4"
            /></Button>
            <Button
                type="button"
                variant="ghost"
                size="icon"
                class="size-8"
                aria-label="Rehacer"
                @click="editor?.chain().focus().redo().run()"
                ><Redo2 class="size-4"
            /></Button>
        </div>
        <EditorContent
            :editor="editor"
            :class="
                cn(
                    'px-4 py-3 text-sm leading-relaxed [&_.ProseMirror]:outline-none',
                    props.minHeight,
                    '[&_.ProseMirror]:min-h-[inherit] [&_h2]:mt-5 [&_h2]:mb-2 [&_h2]:text-lg [&_h2]:font-semibold [&_h3]:mt-4 [&_h3]:mb-1 [&_h3]:font-semibold [&_p]:my-2',
                    '[&_a]:text-primary [&_a]:underline [&_blockquote]:border-l-2 [&_blockquote]:pl-3 [&_blockquote]:italic [&_ol]:list-decimal [&_ol]:pl-5 [&_ul]:list-disc [&_ul]:pl-5',
                    '[&_p.is-editor-empty:first-child]:before:pointer-events-none [&_p.is-editor-empty:first-child]:before:float-left [&_p.is-editor-empty:first-child]:before:h-0 [&_p.is-editor-empty:first-child]:before:text-muted-foreground [&_p.is-editor-empty:first-child]:before:content-[attr(data-placeholder)]',
                )
            "
        />
    </div>
</template>
