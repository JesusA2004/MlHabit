<script setup lang="ts">
import { ArrowRight, Check, Ruler, Video } from '@lucide/vue';
import type { Component } from 'vue';
import { computed } from 'vue';
import SmartLink from '@/components/web/public/SmartLink.vue';
import { ICON_STROKE } from '@/components/web/visual/icons';
import type { Block } from '../types';
import { useBlock } from './useBlock';

/**
 * Precios de los dos primeros pasos: asesoría por videollamada y visita
 * técnica a domicilio. Los montos vienen del servidor (configuración de
 * asesorías y política de visita activa); nunca se escriben a mano. Sin
 * política de visita activa se dice «Costo según tu zona».
 */
const props = defineProps<{ block: Block }>();

const { section, str, bool, editing, dark } = useBlock(() => props.block);

const data = computed(() => section?.value.data[props.block.id] ?? {});
const linkTag = computed(() => (editing.value ? 'span' : SmartLink));

type Card = {
    key: string;
    icon: Component;
    title: string;
    body: string;
    price: string | null;
    unit: string;
    fallback: string;
    points: string[];
    cta: string;
    href: string;
    featured: boolean;
};

const cards = computed<Card[]>(() => {
    const consultation = data.value.consultation ?? null;
    const visit = data.value.visit ?? null;
    const showPrices = bool('show_prices', true);

    return [
        {
            key: 'asesoria',
            icon: Video,
            title: str('consultation_title') || 'Asesoría por videollamada',
            body: str('consultation_body'),
            price: showPrices ? (consultation?.priceFormatted ?? null) : null,
            unit: consultation
                ? `${consultation.durationMinutes} min por videollamada`
                : 'por videollamada',
            fallback: 'Te compartimos el costo al agendar',
            points: [
                'Eliges día y hora en línea',
                'Pago seguro con tarjeta',
                'Recibes el enlace de la videollamada',
            ],
            cta: str('consultation_cta_label') || 'Agenda tu asesoría',
            href: str('consultation_cta_href') || '/solicitar',
            featured: true,
        },
        {
            key: 'visita',
            icon: Ruler,
            title: str('visit_title') || 'Visita técnica a domicilio',
            body: str('visit_body'),
            price: showPrices ? (visit?.feeFormatted ?? null) : null,
            unit: 'por visita',
            fallback: 'Costo según tu zona',
            points: visit?.isCreditable
                ? [
                      'Medición exacta de tu espacio',
                      'Revisión de muros e instalaciones',
                      'Acreditable a tu proyecto',
                  ]
                : [
                      'Medición exacta de tu espacio',
                      'Revisión de muros e instalaciones',
                      'Dudas de materiales resueltas en sitio',
                  ],
            cta: str('visit_cta_label') || 'Agenda visita',
            href: str('visit_cta_href') || '/agendar-visita',
            featured: false,
        },
    ];
});
</script>

<template>
    <ul
        class="grid gap-5 @2xl/sbpage:grid-cols-2"
        data-motion-stagger="120"
        data-motion="rise"
    >
        <li
            v-for="card in cards"
            :key="card.key"
            class="group relative flex flex-col overflow-hidden rounded-[2rem] p-7 transition-[translate,box-shadow] duration-500 hover:-translate-y-1 @2xl/sbpage:p-9"
            :class="
                card.featured
                    ? 'glow-border bg-[linear-gradient(160deg,#24473b,#18261f_70%)] text-white shadow-[0_30px_60px_-30px_rgb(24_38_31/0.8)]'
                    : dark
                      ? 'bg-white/5 text-white ring-1 ring-white/10'
                      : 'bg-card text-foreground shadow-xs ring-1 ring-border hover:shadow-[0_24px_50px_-26px_rgb(35_30_26/0.4)]'
            "
            style="--glow-a: #b07d4f; --glow-b: #8cc0aa; --glow-opacity: 0.7"
        >
            <div class="flex items-start justify-between gap-4">
                <span
                    class="grid size-12 place-items-center rounded-2xl transition-transform duration-500 group-hover:-rotate-6"
                    :class="
                        card.featured || dark
                            ? 'bg-white/10 text-oak'
                            : 'bg-primary-soft text-primary'
                    "
                    aria-hidden="true"
                >
                    <component
                        :is="card.icon"
                        class="size-5"
                        :stroke-width="ICON_STROKE"
                    />
                </span>
                <span
                    v-if="card.featured"
                    class="rounded-full bg-oak/90 px-3 py-1 text-xs font-medium text-white"
                    >Primer paso</span
                >
            </div>
            <h3 class="mt-6 font-display text-2xl font-semibold tracking-tight">
                {{ card.title }}
            </h3>
            <p
                v-if="card.body"
                class="mt-2 text-sm leading-relaxed"
                :class="
                    card.featured || dark
                        ? 'text-white/75'
                        : 'text-muted-foreground'
                "
            >
                {{ card.body }}
            </p>
            <div class="mt-7 flex flex-col gap-1">
                <template v-if="card.price">
                    <span
                        class="font-display text-4xl font-semibold tracking-tight whitespace-nowrap tabular-nums @5xl/sbpage:text-5xl"
                        :data-countup="editing ? undefined : ''"
                        >{{ card.price }}</span
                    >
                    <span
                        class="text-sm"
                        :class="
                            card.featured || dark
                                ? 'text-white/70'
                                : 'text-muted-foreground'
                        "
                        >{{ card.unit }}</span
                    >
                </template>
                <span
                    v-else
                    class="font-display text-2xl font-semibold tracking-tight"
                    >{{ card.fallback }}</span
                >
            </div>
            <ul class="mt-6 grid gap-2.5 text-sm">
                <li
                    v-for="point in card.points"
                    :key="point"
                    class="flex items-center gap-2.5"
                >
                    <Check
                        class="size-4 shrink-0"
                        :class="
                            card.featured || dark ? 'text-oak' : 'text-primary'
                        "
                        aria-hidden="true"
                    />
                    {{ point }}
                </li>
            </ul>
            <component
                :is="linkTag"
                :href="editing ? undefined : card.href"
                :data-magnetic="editing ? undefined : '0.2'"
                class="mt-8 inline-flex h-12 items-center justify-center gap-2 self-start rounded-full px-7 text-base font-medium transition-colors focus-visible:ring-[3px] focus-visible:ring-ring/40 focus-visible:outline-none"
                :class="
                    card.featured
                        ? 'bg-white text-espresso hover:bg-linen'
                        : dark
                          ? 'border border-white/40 text-white hover:bg-white/10'
                          : 'bg-primary text-primary-foreground hover:bg-primary-hover'
                "
            >
                {{ card.cta }}
                <ArrowRight
                    class="size-4 transition-transform duration-300 group-hover:translate-x-0.5"
                    aria-hidden="true"
                />
            </component>
        </li>
    </ul>
</template>
