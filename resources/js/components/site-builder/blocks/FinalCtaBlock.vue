<script setup lang="ts">
import { ArrowRight } from '@lucide/vue';
import { computed } from 'vue';
import ResponsiveImage from '@/components/web/public/ResponsiveImage.vue';
import SmartLink from '@/components/web/public/SmartLink.vue';
import type { Block } from '../types';
import { useBlock } from './useBlock';

/** Cierre con la invitación principal (imagen de fondo opcional). */
const props = defineProps<{ block: Block }>();

const { section, str, editing } = useBlock(() => props.block);

const image = computed(
    () => section?.value.data[props.block.id]?.image ?? null,
);
const primaryHref = computed(() => str('primary_cta_href') || '/solicitar');
const linkTag = computed(() => (editing.value ? 'span' : SmartLink));
</script>

<template>
    <div
        class="relative isolate overflow-hidden rounded-[2rem] bg-primary px-6 py-16 text-primary-foreground @2xl/sbpage:px-14 @2xl/sbpage:py-24"
    >
        <template v-if="image">
            <ResponsiveImage
                :image="image"
                :sizes="image.sizes"
                class="absolute inset-0 -z-20"
            />
            <div
                class="absolute inset-0 -z-10 bg-gradient-to-r from-primary via-primary/85 to-primary/40"
                aria-hidden="true"
            />
        </template>
        <div
            v-else
            class="pointer-events-none absolute -right-24 -bottom-24 -z-10 size-96 rounded-full border-[3rem] border-white/5"
            aria-hidden="true"
        />
        <div class="max-w-2xl">
            <p
                v-if="str('eyebrow')"
                class="mb-4 text-sm font-medium opacity-80"
            >
                {{ str('eyebrow') }}
            </p>
            <h2 class="sb-heading sb-heading--lg">{{ str('title') }}</h2>
            <p v-if="str('body')" class="sb-text sb-text--lead mt-4 opacity-85">
                {{ str('body') }}
            </p>
            <div class="mt-9 flex flex-col gap-3 @2xl/sbpage:flex-row">
                <component
                    :is="linkTag"
                    :href="editing ? undefined : primaryHref"
                    class="inline-flex h-12 items-center justify-center gap-2 rounded-full bg-white px-7 text-base font-medium text-primary transition-colors hover:bg-linen"
                >
                    {{ str('primary_cta_label') || 'Solicita una asesoría' }}
                    <ArrowRight class="size-4" aria-hidden="true" />
                </component>
                <component
                    :is="linkTag"
                    v-if="
                        str('secondary_cta_label') && str('secondary_cta_href')
                    "
                    :href="editing ? undefined : str('secondary_cta_href')"
                    class="inline-flex h-12 items-center justify-center rounded-full border border-white/40 px-7 text-base font-medium text-white transition-colors hover:bg-white/10"
                >
                    {{ str('secondary_cta_label') }}
                </component>
            </div>
        </div>
    </div>
</template>
