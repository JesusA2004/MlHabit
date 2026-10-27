<script setup lang="ts">
import { computed } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import PortalLayout from '@/layouts/PortalLayout.vue';
import TechnicianLayout from '@/layouts/TechnicianLayout.vue';
import { adminNavigation } from '@/lib/navigation';
import { shellFor, usePermissions } from '@/lib/permissions';
import type { BreadcrumbItem } from '@/types';

/**
 * Envoltorio de las pantallas de cuenta (`settings/*`): usa el layout que
 * corresponde a quien inicia sesión. Personal con módulos del panel → panel;
 * técnicos → app de campo; clientes → portal.
 */
const { breadcrumbs = [] } = defineProps<{ breadcrumbs?: BreadcrumbItem[] }>();

const { user } = usePermissions();
const shell = computed(() => shellFor(user.value, adminNavigation));
</script>

<template>
    <TechnicianLayout v-if="shell === 'technician'" title="Mi cuenta">
        <slot />
    </TechnicianLayout>
    <PortalLayout v-else-if="shell === 'portal'">
        <slot />
    </PortalLayout>
    <AppLayout v-else :breadcrumbs="breadcrumbs">
        <slot />
    </AppLayout>
</template>
