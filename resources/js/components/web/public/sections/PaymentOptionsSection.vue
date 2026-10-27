<script setup lang="ts">
import { Info } from '@lucide/vue';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import type { EditorialItem, HomeSection } from '@/types/web';
import SectionHeading from '../SectionHeading.vue';
import SmartLink from '../SmartLink.vue';
import { items, text } from './content';

/**
 * Formas de pago. El aviso de evaluación es obligatorio y siempre visible:
 * preferir pagar en parcialidades no equivale a una aprobación.
 */
const props = defineProps<{ section: HomeSection }>();

const c = computed(() => props.section.content);
const options = computed(() => items<EditorialItem>(c.value, 'items'));
</script>

<template>
    <section aria-labelledby="pagos-title" class="bg-linen section-y">
        <div class="container-site">
            <div class="grid gap-10 lg:grid-cols-12">
                <SectionHeading
                    id="pagos-title"
                    class="lg:col-span-5"
                    :eyebrow="text(c, 'eyebrow')"
                    :title="text(c, 'title')"
                    :subtitle="text(c, 'body')"
                />
                <div class="lg:col-span-7 lg:pt-10">
                    <ul class="divide-y divide-border border-y border-border">
                        <li
                            v-for="(option, i) in options"
                            :key="i"
                            class="grid gap-2 py-6 sm:grid-cols-[12rem_1fr] sm:gap-8"
                        >
                            <h3
                                class="font-display text-lg font-semibold tracking-tight"
                            >
                                {{ option.title }}
                            </h3>
                            <p v-if="option.body" class="text-muted-foreground">
                                {{ option.body }}
                            </p>
                        </li>
                    </ul>
                    <p
                        v-if="text(c, 'disclaimer')"
                        role="note"
                        class="mt-6 flex items-start gap-3 rounded-xl bg-info-soft p-4 text-sm text-info-ink"
                    >
                        <Info
                            class="mt-0.5 size-4 shrink-0"
                            aria-hidden="true"
                        />
                        {{ text(c, 'disclaimer') }}
                    </p>
                    <Button
                        v-if="text(c, 'cta_label') && text(c, 'cta_href')"
                        as-child
                        variant="outline"
                        size="lg"
                        class="mt-8 h-12 rounded-full px-6"
                    >
                        <SmartLink :href="text(c, 'cta_href')">{{
                            text(c, 'cta_label')
                        }}</SmartLink>
                    </Button>
                </div>
            </div>
        </div>
    </section>
</template>
