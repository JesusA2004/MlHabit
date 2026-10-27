<script setup lang="ts">
/** Marca de MR. INSIGHT en el panel (isotipo + nombre). */
</script>

<template>
    <div class="flex aspect-square size-8 items-center justify-center rounded-lg bg-[#0d0e10] ring-1 ring-brand/40">
        <svg viewBox="0 0 64 64" class="size-6" aria-hidden="true">
            <circle cx="32" cy="29" r="14" fill="none" stroke="#C7AA7D" stroke-width="3.5" />
            <circle cx="32" cy="29" r="4.5" fill="#C7AA7D" />
            <line x1="17" y1="50" x2="47" y2="50" stroke="#C7AA7D" stroke-width="3.5" stroke-linecap="round" />
        </svg>
    </div>
    <div class="ml-1 grid flex-1 text-left leading-tight">
        <span class="truncate font-display text-[15px] font-semibold tracking-tight">MR. INSIGHT</span>
        <span class="truncate text-[10px] tracking-[0.16em] text-muted-foreground uppercase">Sala de redacción</span>
    </div>
</template>
