<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';
import type { CardBrand } from './openpay';
import { BRAND_LABEL } from './openpay';

/**
 * Marcas de tarjeta aceptadas (insignias tipográficas simples, sin logotipos
 * oficiales). Con `active` resalta la marca detectada y atenúa las demás.
 */
const props = withDefaults(
    defineProps<{
        active?: CardBrand;
        size?: 'sm' | 'md';
        class?: HTMLAttributes['class'];
    }>(),
    { active: null, size: 'sm', class: undefined },
);

const brands: Exclude<CardBrand, null>[] = [
    'visa',
    'mastercard',
    'amex',
    'carnet',
];
</script>

<template>
    <ul
        :class="cn('flex items-center gap-1.5', props.class)"
        aria-label="Tarjetas aceptadas: Visa, Mastercard, American Express y Carnet"
    >
        <li
            v-for="brand in brands"
            :key="brand"
            :class="
                cn(
                    'brand-mark grid place-items-center rounded-[5px] border bg-white shadow-xs transition-all duration-300',
                    size === 'md' ? 'h-7 w-11' : 'h-6 w-9',
                    active &&
                        active !== brand &&
                        'scale-90 opacity-30 grayscale',
                    active === brand && 'ring-2 ring-primary/40',
                )
            "
            :title="BRAND_LABEL[brand]"
        >
            <svg
                v-if="brand === 'visa'"
                viewBox="0 0 48 16"
                class="h-[55%] w-auto"
                aria-hidden="true"
            >
                <text
                    x="24"
                    y="13"
                    text-anchor="middle"
                    font-family="Arial, Helvetica, sans-serif"
                    font-size="15"
                    font-style="italic"
                    font-weight="900"
                    fill="#1a1f71"
                    letter-spacing="0.5"
                >
                    VISA
                </text>
            </svg>
            <svg
                v-else-if="brand === 'mastercard'"
                viewBox="0 0 32 20"
                class="h-[70%] w-auto"
                aria-hidden="true"
            >
                <circle cx="12" cy="10" r="8" fill="#eb001b" />
                <circle cx="20" cy="10" r="8" fill="#f79e1b" />
                <path
                    d="M16 3.07a8 8 0 0 1 0 13.86 8 8 0 0 1 0-13.86Z"
                    fill="#ff5f00"
                />
            </svg>
            <svg
                v-else-if="brand === 'amex'"
                viewBox="0 0 40 24"
                class="h-full w-full rounded-[4px]"
                aria-hidden="true"
            >
                <rect width="40" height="24" fill="#2e77bc" />
                <text
                    x="20"
                    y="15.5"
                    text-anchor="middle"
                    font-family="Arial, Helvetica, sans-serif"
                    font-size="9"
                    font-weight="800"
                    fill="#fff"
                    letter-spacing="0.4"
                >
                    AMEX
                </text>
            </svg>
            <svg
                v-else
                viewBox="0 0 48 16"
                class="h-[50%] w-auto"
                aria-hidden="true"
            >
                <text
                    x="24"
                    y="12.5"
                    text-anchor="middle"
                    font-family="Arial, Helvetica, sans-serif"
                    font-size="12"
                    font-weight="800"
                    fill="#00843d"
                >
                    carnet
                </text>
            </svg>
            <span class="sr-only">{{ BRAND_LABEL[brand] }}</span>
        </li>
    </ul>
</template>
