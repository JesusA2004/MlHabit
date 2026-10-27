<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

/**
 * Texto enriquecido. El HTML SIEMPRE llega saneado desde el servidor
 * (App\Support\Html::clean al guardar); aquí solo se le da estilo.
 */
const props = defineProps<{ html: string; class?: HTMLAttributes['class'] }>();
</script>

<template>
    <!-- eslint-disable-next-line vue/no-v-html -->
    <div
        :class="
            cn(
                'max-w-prose text-[1.0625rem] leading-relaxed text-foreground/85',
                '[&_p]:mt-4 [&_p:first-child]:mt-0',
                '[&_h2]:mt-10 [&_h2]:font-display [&_h2]:text-2xl [&_h2]:font-semibold [&_h2]:tracking-tight [&_h2]:text-foreground',
                '[&_h3]:mt-8 [&_h3]:font-display [&_h3]:text-xl [&_h3]:font-semibold [&_h3]:text-foreground',
                '[&_h4]:mt-6 [&_h4]:font-semibold [&_h4]:text-foreground',
                '[&_li]:mt-1.5 [&_ol]:mt-4 [&_ol]:list-decimal [&_ol]:pl-6 [&_ul]:mt-4 [&_ul]:list-disc [&_ul]:pl-6',
                '[&_a]:font-medium [&_a]:text-primary [&_a]:underline [&_a]:underline-offset-4',
                '[&_blockquote]:mt-6 [&_blockquote]:border-l-2 [&_blockquote]:border-oak [&_blockquote]:pl-5 [&_blockquote]:italic',
                '[&_hr]:my-10 [&_hr]:border-border [&_strong]:font-semibold [&_strong]:text-foreground',
                '[&_table]:mt-6 [&_table]:w-full [&_table]:text-sm [&_td]:border-b [&_td]:py-2 [&_th]:border-b [&_th]:py-2 [&_th]:text-left',
                '[&_img]:mt-6 [&_img]:rounded-xl',
                props.class,
            )
        "
        v-html="props.html"
    />
</template>
