<script setup lang="ts">
import { computed, onBeforeUnmount, provide, reactive, watch, watchEffect } from 'vue';
import { loadFonts } from '@/lib/fonts';
import { documentCss, documentFonts } from '@/studio/css';
import type { RenderContext, StudioDocument, StudioResolved } from '@/studio/types';
import type { SocialLinkSummary } from '@/types';
import { RENDER_CTX } from './context';
import SectionView from './SectionView.vue';

/**
 * Renderizador único de documentos de Studio: lo usan el sitio público,
 * la vista previa y el canvas del editor. Así lo que se edita es
 * exactamente lo que se publica.
 */
const props = withDefaults(
    defineProps<{
        document: StudioDocument;
        resolved: StudioResolved;
        social?: SocialLinkSummary[];
        editing?: boolean;
    }>(),
    { social: () => [], editing: false },
);

const ctx = reactive<RenderContext>({
    resolved: props.resolved,
    editing: props.editing,
    social: props.social,
});

watch(
    () => [props.resolved, props.editing, props.social] as const,
    ([resolved, editing, social]) => {
        ctx.resolved = resolved;
        ctx.editing = editing;
        ctx.social = social;
    },
);

provide(RENDER_CTX, ctx);

const css = computed(() => documentCss(props.document));

// CSS por bloque generado desde valores validados (ver studio/css.ts).
let styleEl: HTMLStyleElement | null = null;
watchEffect(() => {
    if (typeof document === 'undefined') return;
    if (!styleEl) {
        styleEl = document.createElement('style');
        styleEl.dataset.studio = '';
        document.head.appendChild(styleEl);
    }
    styleEl.textContent = css.value;
});

watchEffect(() => loadFonts(documentFonts(props.document)));

onBeforeUnmount(() => {
    styleEl?.remove();
    styleEl = null;
});
</script>

<template>
    <div class="sn-page" :data-editing="editing || undefined">
        <SectionView
            v-for="section in document.sections"
            :key="section.id"
            :node="section"
        />
    </div>
</template>
