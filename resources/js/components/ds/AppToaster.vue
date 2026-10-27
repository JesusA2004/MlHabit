<script setup lang="ts">
import { useMediaQuery } from '@vueuse/core';
import { Toaster } from '@/components/ui/sonner';

/**
 * Toaster global con estilo de marca. Los mensajes `flash.toast` del
 * backend llegan vía lib/flashToast.ts; desde el cliente usa
 * `toast.success('Cotización enviada')` de vue-sonner o `showToast()`.
 */
const isMobile = useMediaQuery('(max-width: 767px)');
</script>

<template>
    <Toaster
        :position="isMobile ? 'top-center' : 'bottom-right'"
        :visible-toasts="3"
        :duration="5000"
        close-button
        :toast-options="{
            classes: {
                toast: 'rounded-xl! border-border! shadow-lg! font-sans!',
                title: 'font-medium! text-foreground!',
                description: 'text-muted-foreground!',
                success: '[&_[data-icon]]:text-success!',
                error: '[&_[data-icon]]:text-danger!',
                warning: '[&_[data-icon]]:text-warning!',
                info: '[&_[data-icon]]:text-info!',
                closeButton: 'bg-card! border-border! text-muted-foreground!',
            },
        }"
    />
</template>
