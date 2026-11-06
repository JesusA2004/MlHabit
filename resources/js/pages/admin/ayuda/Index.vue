<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { PageContainer, PageHeader } from '@/components/ds';
import HelpCenter from '@/components/tour/HelpCenter.vue';

/**
 * Centro de ayuda del panel: una ficha por módulo (según los permisos de
 * la persona) con su guía y su recorrido. El contenido vive en
 * components/tour/tours/admin.ts.
 */
defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Panel', href: '/admin' },
            { title: 'Centro de ayuda', href: '/admin/ayuda' },
        ],
    },
});
</script>

<template>
    <Head title="Centro de ayuda" />

    <PageContainer>
        <PageHeader
            title="Centro de ayuda"
            description="Para qué sirve cada módulo, sus pasos clave y un recorrido guiado en pantalla. Solo ves los módulos a los que tienes acceso."
        />

        <HelpCenter area="admin" />
    </PageContainer>
</template>
