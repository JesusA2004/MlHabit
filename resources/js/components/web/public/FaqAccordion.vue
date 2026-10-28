<script setup lang="ts">
import { Plus } from '@lucide/vue';
import {
    Accordion,
    AccordionContent,
    AccordionItem,
    AccordionTrigger,
} from '@/components/ui/accordion';
import type { FaqData } from '@/types/web';

/**
 * Preguntas frecuentes en acordeón (varias abiertas a la vez) con altura
 * animada. `highlight` resalta coincidencias de búsqueda sin usar v-html.
 */
const props = defineProps<{
    faqs: FaqData[];
    highlight?: string | null;
    defaultOpen?: string[];
}>();

/** Abiertas (opcional, controlado): permite "Abrir todas" y abrir coincidencias. */
const open = defineModel<string[]>('open');

function normalize(text: string): string {
    return text
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '')
        .toLowerCase();
}

/** Parte el texto en tramos marcados/no marcados (insensible a acentos). */
function parts(text: string): { text: string; match: boolean }[] {
    const query = normalize(props.highlight?.trim() ?? '');

    if (query.length < 2) {
        return [{ text, match: false }];
    }

    const haystack = normalize(text);
    const result: { text: string; match: boolean }[] = [];
    let index = 0;
    let found = haystack.indexOf(query, index);

    while (found !== -1) {
        if (found > index) {
            result.push({ text: text.slice(index, found), match: false });
        }

        result.push({
            text: text.slice(found, found + query.length),
            match: true,
        });
        index = found + query.length;
        found = haystack.indexOf(query, index);
    }

    if (index < text.length) {
        result.push({ text: text.slice(index), match: false });
    }

    return result;
}
</script>

<template>
    <Accordion
        type="multiple"
        :default-value="open === undefined ? props.defaultOpen : undefined"
        :model-value="open"
        @update:model-value="
            (v) => (open = Array.isArray(v) ? v.map(String) : [])
        "
        class="divide-y divide-border border-y border-border"
    >
        <AccordionItem
            v-for="faq in props.faqs"
            :key="faq.id"
            :value="String(faq.id)"
            class="group/faq relative border-0 transition-colors duration-300 data-[state=open]:bg-card/60"
        >
            <span
                class="absolute inset-y-0 left-0 w-0.5 origin-top scale-y-0 bg-oak transition-transform duration-500 ease-out group-data-[state=open]/faq:scale-y-100"
                aria-hidden="true"
            />
            <AccordionTrigger
                class="gap-6 px-1 py-5 text-base font-medium text-foreground transition-[padding] duration-300 hover:text-primary hover:no-underline data-[state=open]:pl-4 sm:text-lg [&[data-state=open]_.faq-icon]:rotate-45 [&[data-state=open]_.faq-icon]:bg-primary [&[data-state=open]_.faq-icon]:text-primary-foreground"
            >
                <span>
                    <template v-for="(part, i) in parts(faq.question)" :key="i"
                        ><mark
                            v-if="part.match"
                            class="rounded-sm bg-oak/25 px-0.5 text-foreground"
                            >{{ part.text }}</mark
                        ><template v-else>{{ part.text }}</template></template
                    >
                </span>
                <template #icon>
                    <span
                        class="faq-icon pointer-events-none grid size-8 shrink-0 place-items-center rounded-full border border-border text-oak-ink transition-[rotate,background-color,color,border-color] duration-300 group-hover/faq:border-primary/40"
                        aria-hidden="true"
                    >
                        <Plus class="size-4" :stroke-width="1.5" />
                    </span>
                </template>
            </AccordionTrigger>
            <AccordionContent
                class="max-w-prose pr-12 pb-6 pl-4 text-[0.975rem] leading-relaxed whitespace-pre-line text-muted-foreground"
            >
                <template v-for="(part, i) in parts(faq.answer)" :key="i"
                    ><mark
                        v-if="part.match"
                        class="rounded-sm bg-oak/25 px-0.5 text-foreground"
                        >{{ part.text }}</mark
                    ><template v-else>{{ part.text }}</template></template
                >
            </AccordionContent>
        </AccordionItem>
    </Accordion>
</template>
