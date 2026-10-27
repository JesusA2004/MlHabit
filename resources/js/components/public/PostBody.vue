<script setup lang="ts">
import { EditorContent, useEditor } from '@tiptap/vue-3';
import { watch } from 'vue';
import { createPostExtensions } from '@/lib/tiptap/extensions';
import type { TiptapContent } from '@/types';

const { content } = defineProps<{ content: TiptapContent | null }>();

const editor = useEditor({
    content: content ?? '<p></p>',
    editable: false,
    extensions: createPostExtensions(false),
});

watch(
    () => content,
    (value) =>
        editor.value?.commands.setContent(value ?? '<p></p>', {
            emitUpdate: false,
        }),
);
</script>

<template>
    <EditorContent :editor="editor" class="post-body" />
</template>

<style>
.post-body :is(h2, h3, h4) {
    font-family: var(--font-display, serif);
    font-weight: 600;
    color: var(--color-paper, #f4f1ea);
    margin-top: 1.4em;
    margin-bottom: 0.5em;
}
.post-body p {
    margin: 1em 0;
    line-height: 1.7;
    color: var(--color-paper, #f4f1ea);
}
.post-body a {
    color: var(--color-gold, #c7aa7d);
    text-decoration: underline;
}
.post-body ul,
.post-body ol {
    padding-left: 1.5em;
    margin: 1em 0;
    color: var(--color-paper, #f4f1ea);
}
.post-body blockquote {
    border-left: 3px solid var(--color-gold, #c7aa7d);
    padding-left: 1em;
    font-style: italic;
    color: var(--color-mist, #aba9a3);
    margin: 1.4em 0;
}
.post-body hr {
    border-color: rgb(255 255 255 / 0.15);
    margin: 2em 0;
}
.post-body img {
    border-radius: 0.75rem;
    max-width: 100%;
    margin: 1.5em 0;
}
.post-body .tip-block {
    border-left: 4px solid var(--color-gold, #c7aa7d);
    background: color-mix(in srgb, var(--color-gold, #c7aa7d) 12%, transparent);
    border-radius: 0.75rem;
    padding: 1.25rem 1.5rem;
    margin: 1.75em 0;
}
.post-body .tip-block::before {
    content: 'EL TIP';
    display: block;
    font-size: 0.75rem;
    font-weight: 700;
    letter-spacing: 0.12em;
    color: var(--color-gold, #c7aa7d);
    margin-bottom: 0.5em;
}
.post-body .question-block {
    border: 1px dashed var(--color-gold, #c7aa7d);
    border-radius: 0.75rem;
    padding: 1.25rem 1.5rem;
    margin: 1.75em 0;
    font-family: var(--font-display, serif);
    font-size: 1.2rem;
    color: var(--color-paper, #f4f1ea);
}
.post-body video {
    width: 100%;
    border-radius: 0.75rem;
    margin: 1.5em 0;
}
</style>
