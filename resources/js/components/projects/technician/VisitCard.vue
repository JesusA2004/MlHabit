<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronRight, MapPin } from '@lucide/vue';
import StatusBadge from '@/components/ds/StatusBadge.vue';
import TimeBlock from './TimeBlock.vue';
import visitas from '@/routes/tecnico/visitas';
import type { AddressInfo, EnumValue } from '@/types/projects';

export type TechnicianVisit = {
    uuid: string;
    folio: string;
    status: EnumValue | null;
    starts_at: string | null;
    ends_at: string | null;
    customer_first_name: string | null;
    address: AddressInfo | null;
};

/** Tarjeta de visita para la app de campo (toda la tarjeta es el enlace). */
const props = withDefaults(
    defineProps<{ visit: TechnicianVisit; showDate?: boolean }>(),
    { showDate: false },
);
</script>

<template>
    <Link
        :href="visitas.show(props.visit.uuid).url"
        class="group flex items-stretch gap-4 rounded-xl border bg-card p-4 transition-colors hover:border-primary/40 hover:bg-accent/40 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none active:scale-[0.995]"
    >
        <TimeBlock :at="props.visit.starts_at" :show-date="props.showDate" />
        <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                <p class="text-base font-semibold text-foreground">
                    {{ props.visit.customer_first_name ?? 'Cliente' }}
                </p>
                <StatusBadge
                    v-if="props.visit.status"
                    :label="props.visit.status.label"
                    :tone="props.visit.status.tone"
                    size="sm"
                />
            </div>
            <p
                v-if="props.visit.address"
                class="mt-1 flex items-start gap-1.5 text-sm text-muted-foreground"
            >
                <MapPin class="mt-0.5 size-4 shrink-0" aria-hidden="true" />
                <span class="line-clamp-2">{{
                    props.visit.address.one_line
                }}</span>
            </p>
            <p class="mt-1 text-xs text-muted-foreground">
                Visita {{ props.visit.folio }}
            </p>
        </div>
        <ChevronRight
            class="size-5 shrink-0 self-center text-muted-foreground transition-transform group-hover:translate-x-0.5"
            aria-hidden="true"
        />
    </Link>
</template>
