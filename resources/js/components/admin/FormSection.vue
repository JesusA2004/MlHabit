<script setup lang="ts">
/**
 * Sección de formulario en dos columnas (≥1024 px): explicación a la
 * izquierda, campos a la derecha. Evita formularios largos de una columna.
 */
defineProps<{ title: string; description?: string }>();
</script>

<template>
    <section
        class="grid gap-4 border-b py-6 last:border-b-0 lg:grid-cols-[minmax(0,280px)_minmax(0,1fr)] lg:gap-10"
    >
        <div>
            <h2 class="text-sm font-semibold text-foreground">{{ title }}</h2>
            <p v-if="description" class="mt-1 text-sm text-muted-foreground">
                {{ description }}
            </p>
            <slot name="aside" />
        </div>
        <div class="flex min-w-0 flex-col gap-4">
            <slot />
        </div>
    </section>
</template>
