<script setup lang="ts">
import {
    BadgePercent,
    CalendarCheck,
    Clock,
    MapPin,
    UserRound,
} from '@lucide/vue';
import type { HTMLAttributes } from 'vue';
import { formatMoney } from '@/lib/format';
import { cn } from '@/lib/utils';

/**
 * Resumen de la visita (checkout y pago): qué, cuándo, dónde y cuánto.
 * Solo presenta: todos los montos vienen calculados del backend.
 */
const props = withDefaults(
    defineProps<{
        when?: string | null;
        durationMinutes?: number | null;
        address?: string | null;
        technician?: string | null;
        fee: string;
        isCreditable?: boolean;
        creditAmount?: string | null;
        creditValidityDays?: number | null;
        title?: string;
        class?: HTMLAttributes['class'];
    }>(),
    { title: 'Tu visita técnica', isCreditable: false },
);
</script>

<template>
    <div :class="cn('rounded-xl border bg-card p-5 shadow-xs', props.class)">
        <h2 class="text-sm font-medium">{{ title }}</h2>

        <ul class="mt-4 space-y-3 text-sm">
            <li class="flex gap-3">
                <CalendarCheck
                    class="mt-0.5 size-4 shrink-0 text-primary"
                    aria-hidden="true"
                />
                <span
                    :class="
                        when
                            ? 'first-letter:uppercase'
                            : 'text-muted-foreground'
                    "
                    >{{ when ?? 'Elige día y horario' }}</span
                >
            </li>
            <li v-if="durationMinutes" class="flex gap-3">
                <Clock
                    class="mt-0.5 size-4 shrink-0 text-primary"
                    aria-hidden="true"
                />
                <span
                    >Aprox.
                    {{
                        durationMinutes >= 60
                            ? `${Math.floor(durationMinutes / 60)} h${durationMinutes % 60 ? ` ${durationMinutes % 60} min` : ''}`
                            : `${durationMinutes} min`
                    }}
                    en tu domicilio</span
                >
            </li>
            <li v-if="address" class="flex gap-3">
                <MapPin
                    class="mt-0.5 size-4 shrink-0 text-primary"
                    aria-hidden="true"
                />
                <span>{{ address }}</span>
            </li>
            <li v-if="technician" class="flex gap-3">
                <UserRound
                    class="mt-0.5 size-4 shrink-0 text-primary"
                    aria-hidden="true"
                />
                <span>Te visitará {{ technician }}</span>
            </li>
        </ul>

        <div class="mt-5 flex items-baseline justify-between border-t pt-4">
            <span class="text-sm text-muted-foreground">Total a pagar</span>
            <span
                class="font-display text-2xl font-semibold tracking-tight tabular-nums"
                >{{ formatMoney(fee) }}</span
            >
        </div>
        <p class="mt-1 text-right text-xs text-muted-foreground">
            MXN, IVA incluido
        </p>

        <div
            v-if="isCreditable && creditAmount"
            class="mt-4 flex gap-2.5 rounded-lg bg-primary-soft p-3 text-sm text-primary"
        >
            <BadgePercent class="mt-0.5 size-4 shrink-0" aria-hidden="true" />
            <p>
                Si contratas tu proyecto, abonamos
                <strong class="font-semibold">{{
                    formatMoney(creditAmount)
                }}</strong>
                a tu anticipo<template v-if="creditValidityDays"
                    >, vigente {{ creditValidityDays }} días</template
                >.
            </p>
        </div>

        <slot />
    </div>
</template>
