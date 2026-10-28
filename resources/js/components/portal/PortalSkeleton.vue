<script setup lang="ts">
/**
 * Esqueleto genérico de una pantalla del portal: se muestra cuando una
 * navegación tarda (ver PortalLayout). Decorativo para lectores de
 * pantalla; el estado se anuncia con `aria-busy` en el contenedor.
 */
</script>

<template>
    <div
        class="mx-auto flex w-full max-w-7xl flex-col gap-6 px-4 py-6 md:px-6 md:py-8 lg:px-8"
        aria-hidden="true"
    >
        <div class="flex flex-col gap-3">
            <div class="portal-skeleton h-4 w-32" />
            <div class="portal-skeleton h-8 w-72 max-w-full" />
        </div>
        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <div v-for="i in 4" :key="i" class="portal-skeleton h-28" />
        </div>
        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_340px]">
            <div class="flex flex-col gap-3">
                <div v-for="i in 4" :key="i" class="portal-skeleton h-20" />
            </div>
            <div class="portal-skeleton hidden h-64 lg:block" />
        </div>
    </div>
</template>
