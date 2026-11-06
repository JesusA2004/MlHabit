<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { onBeforeUnmount, onMounted, ref } from 'vue';
import ConfirmDialog from '@/components/ds/ConfirmDialog.vue';
import { hasActiveUploads, uploadsInProgress } from '@/lib/uploadActivity';

/**
 * Advierte antes de salir mientras hay subidas en curso (ver
 * `lib/uploadActivity`): el aviso nativo del navegador al cerrar o recargar
 * la pestaña y un diálogo propio al navegar dentro de la app con Inertia.
 * Se monta una vez por layout autenticado (panel, portal y técnicos).
 */
const open = ref(false);
const pendingUrl = ref<string | null>(null);
let bypass = false;

function onBeforeUnload(event: BeforeUnloadEvent) {
    if (!hasActiveUploads.value) {
        return;
    }

    event.preventDefault();
    // Navegadores antiguos requieren un valor de retorno.
    event.returnValue = '';
}

let removeBefore: (() => void) | null = null;

onMounted(() => {
    window.addEventListener('beforeunload', onBeforeUnload);
    removeBefore = router.on('before', (event) => {
        const visit = event.detail.visit;

        if (bypass || !hasActiveUploads.value || visit.prefetch) {
            return;
        }

        // Solo navegaciones a otra pantalla: recargas parciales, envíos de
        // formularios y paginación en la misma ruta no interrumpen la subida.
        if (
            visit.method !== 'get' ||
            visit.url.pathname === window.location.pathname
        ) {
            return;
        }

        pendingUrl.value = visit.url.href;
        open.value = true;

        return false;
    });
});

onBeforeUnmount(() => {
    window.removeEventListener('beforeunload', onBeforeUnload);
    removeBefore?.();
});

function leave() {
    const url = pendingUrl.value;
    pendingUrl.value = null;

    if (url) {
        bypass = true;
        router.visit(url, { onFinish: () => (bypass = false) });
    }
}
</script>

<template>
    <ConfirmDialog
        v-model:open="open"
        title="Hay archivos subiéndose"
        :description="
            uploadsInProgress === 1
                ? 'Si sales de esta pantalla, la subida se interrumpe. Puedes esperar a que termine o salir y volver a intentarlo después.'
                : `Hay ${uploadsInProgress} archivos subiéndose. Si sales de esta pantalla, las subidas se interrumpen.`
        "
        confirm-label="Salir de todos modos"
        cancel-label="Esperar a que termine"
        tone="destructive"
        :confirm="leave"
        @cancelled="pendingUrl = null"
    />
</template>
