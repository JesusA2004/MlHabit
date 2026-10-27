<script setup lang="ts">
import { Plus } from '@lucide/vue';
import {
    Accordion,
    AccordionContent,
    AccordionItem,
    AccordionTrigger,
} from '@/components/ui/accordion';
import type { FaqData } from '@/types/web';

/** Preguntas frecuentes en acordeón (varias abiertas a la vez). */
const props = defineProps<{ faqs: FaqData[] }>();
</script>

<template>
    <Accordion
        type="multiple"
        class="divide-y divide-border border-y border-border"
    >
        <AccordionItem
            v-for="faq in props.faqs"
            :key="faq.id"
            :value="String(faq.id)"
            class="border-0"
        >
            <AccordionTrigger
                class="py-5 text-base font-medium text-foreground hover:no-underline sm:text-lg [&[data-state=open]>svg]:rotate-45"
            >
                {{ faq.question }}
                <template #icon>
                    <Plus
                        class="pointer-events-none mt-1 size-5 shrink-0 text-oak-ink transition-transform duration-200"
                        aria-hidden="true"
                    />
                </template>
            </AccordionTrigger>
            <AccordionContent
                class="pb-6 text-[0.975rem] leading-relaxed whitespace-pre-line text-muted-foreground"
            >
                {{ faq.answer }}
            </AccordionContent>
        </AccordionItem>
    </Accordion>
</template>
