<script setup lang="ts">
import { nextTick, onMounted, ref } from 'vue';
import { plainText, sanitizeRichText } from '../sanitize';

/**
 * Texto editable en el lienzo (doble clic en un título o párrafo). Mientras
 * se edita, el contenido NO es reactivo (el navegador manda); al salir se
 * entrega limpio. Pegar siempre inserta texto plano.
 */
const props = withDefaults(
    defineProps<{
        tag?: string;
        value: string;
        rich?: boolean;
        placeholder?: string;
    }>(),
    { tag: 'div', rich: false, placeholder: 'Escribe aquí…' },
);

const emit = defineEmits<{ commit: [value: string]; cancel: [] }>();

const el = ref<HTMLElement | null>(null);
let done = false;

onMounted(async () => {
    if (!el.value) return;
    if (props.rich) {
        el.value.innerHTML = props.value;
    } else {
        el.value.textContent = props.value;
    }
    await nextTick();
    el.value.focus();
    const range = document.createRange();
    range.selectNodeContents(el.value);
    const selection = window.getSelection();
    selection?.removeAllRanges();
    selection?.addRange(range);
    if (props.rich) {
        document.execCommand('defaultParagraphSeparator', false, 'p');
    }
});

function finish(cancel = false) {
    if (done || !el.value) return;
    done = true;
    if (cancel) {
        emit('cancel');

        return;
    }
    emit(
        'commit',
        props.rich
            ? sanitizeRichText(el.value.innerHTML)
            : plainText(el.value.innerText),
    );
}

function onKeydown(event: KeyboardEvent) {
    event.stopPropagation();
    if (event.key === 'Escape') {
        event.preventDefault();
        finish(true);
    } else if (event.key === 'Enter' && !props.rich) {
        event.preventDefault();
        finish();
    } else if (
        event.key === 'Enter' &&
        props.rich &&
        (event.metaKey || event.ctrlKey)
    ) {
        event.preventDefault();
        finish();
    }
}

function onPaste(event: ClipboardEvent) {
    event.preventDefault();
    const text = event.clipboardData?.getData('text/plain') ?? '';
    document.execCommand('insertText', false, text);
}
</script>

<template>
    <component
        :is="props.tag"
        ref="el"
        class="sb-editable"
        contenteditable="true"
        role="textbox"
        :aria-multiline="props.rich ? 'true' : 'false'"
        :data-placeholder="props.placeholder"
        spellcheck="true"
        @keydown="onKeydown"
        @paste="onPaste"
        @blur="finish()"
        @pointerdown.stop
    />
</template>
