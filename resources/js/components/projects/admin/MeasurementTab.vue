<script setup lang="ts">
import { Ruler } from '@lucide/vue';
import { computed, ref } from 'vue';
import DateText from '@/components/ds/DateText.vue';
import EmptyState from '@/components/ds/EmptyState.vue';
import SectionCard from '@/components/ds/SectionCard.vue';
import StatusBadge from '@/components/ds/StatusBadge.vue';
import { cn } from '@/lib/utils';
import { formatMm } from '@/types/projects';
import type { MeasurementData, MeasurementItemRow } from '@/types/projects';
import type { HubVisit } from './types';

const props = defineProps<{
    measurements: MeasurementData[];
    visits: HubVisit[];
}>();

const selectedId = ref<number | null>(
    props.measurements.find((m) => m.status.value === 'submitted')?.id ??
        props.measurements[0]?.id ??
        null,
);
const selected = computed(
    () => props.measurements.find((m) => m.id === selectedId.value) ?? null,
);

const areas = computed(() => {
    const groups = new Map<string, MeasurementItemRow[]>();
    for (const item of selected.value?.items ?? []) {
        groups.set(item.area, [...(groups.get(item.area) ?? []), item]);
    }

    return [...groups.entries()];
});
</script>

<template>
    <div class="grid gap-6 lg:grid-cols-[280px_minmax(0,1fr)]">
        <div class="flex flex-col gap-6">
            <SectionCard title="Versiones" flush>
                <EmptyState
                    v-if="!measurements.length"
                    compact
                    :bordered="false"
                    :icon="Ruler"
                    title="Sin medición todavía"
                    description="El técnico la registra desde su app durante la visita."
                />
                <ul v-else class="flex flex-col divide-y">
                    <li v-for="m in measurements" :key="m.id">
                        <button
                            type="button"
                            :aria-pressed="m.id === selectedId"
                            :class="
                                cn(
                                    'flex w-full items-center justify-between gap-3 px-4 py-3 text-left hover:bg-muted/60 pointer-coarse:min-h-12',
                                    m.id === selectedId && 'bg-muted',
                                )
                            "
                            @click="selectedId = m.id"
                        >
                            <span class="flex flex-col">
                                <span class="font-medium"
                                    >Versión {{ m.version }}</span
                                >
                                <span class="text-xs text-muted-foreground">
                                    {{ m.technician?.name ?? 'Técnico' }} ·
                                    <DateText
                                        :value="m.submitted_at ?? m.recorded_at"
                                    />
                                </span>
                            </span>
                            <StatusBadge
                                :tone="m.status.tone"
                                :label="m.status.label"
                                size="sm"
                            />
                        </button>
                    </li>
                </ul>
            </SectionCard>

            <SectionCard title="Visitas" flush>
                <p
                    v-if="!visits.length"
                    class="px-4 py-3 text-sm text-muted-foreground"
                >
                    No hay visitas ligadas a este proyecto.
                </p>
                <ul v-else class="flex flex-col divide-y text-sm">
                    <li
                        v-for="v in visits"
                        :key="v.uuid"
                        class="flex items-center justify-between gap-3 px-4 py-3"
                    >
                        <a
                            :href="`/admin/visitas/${v.uuid}`"
                            class="flex flex-col hover:underline"
                        >
                            <span class="text-xs tabular-nums">{{
                                v.folio
                            }}</span>
                            <DateText
                                :value="v.starts_at"
                                format="datetime"
                                class="text-muted-foreground"
                            />
                        </a>
                        <StatusBadge
                            v-if="v.status"
                            :tone="v.status.tone"
                            :label="v.status.label"
                            size="sm"
                        />
                    </li>
                </ul>
            </SectionCard>
        </div>

        <SectionCard
            v-if="selected"
            :title="`Medición v${selected.version}`"
            :description="selected.space_type ?? undefined"
        >
            <div class="flex flex-col gap-6">
                <p
                    v-if="selected.status.value === 'draft'"
                    class="rounded-lg bg-warning-soft p-3 text-sm text-warning-ink"
                >
                    Borrador en captura: puede cambiar hasta que el técnico lo
                    envíe.
                </p>
                <p
                    v-else-if="selected.status.value === 'superseded'"
                    class="rounded-lg bg-muted p-3 text-sm"
                >
                    Esta versión fue reemplazada por una corrección posterior.
                    Se conserva como historial.
                </p>

                <dl class="grid gap-4 text-sm sm:grid-cols-3">
                    <div>
                        <dt class="text-muted-foreground">Accesos</dt>
                        <dd class="mt-1 whitespace-pre-line">
                            {{ selected.access_notes || '—' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground">
                            Instalaciones existentes
                        </dt>
                        <dd class="mt-1 whitespace-pre-line">
                            {{ selected.existing_installations || '—' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground">Notas</dt>
                        <dd class="mt-1 whitespace-pre-line">
                            {{ selected.general_notes || '—' }}
                        </dd>
                    </div>
                </dl>

                <div
                    v-for="[area, rows] in areas"
                    :key="area"
                    class="overflow-hidden rounded-lg border"
                >
                    <h3 class="bg-muted/60 px-4 py-2 text-sm font-semibold">
                        {{ area }}
                    </h3>
                    <table class="w-full text-sm">
                        <caption class="sr-only">
                            Medidas de
                            {{
                                area
                            }}
                        </caption>
                        <thead class="text-left text-xs text-muted-foreground">
                            <tr>
                                <th class="px-4 py-2 font-medium">Tipo</th>
                                <th class="px-4 py-2 font-medium">
                                    Descripción
                                </th>
                                <th class="px-4 py-2 text-right font-medium">
                                    Medida
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-for="row in rows" :key="row.id">
                                <td class="px-4 py-2">{{ row.kind_label }}</td>
                                <td class="px-4 py-2">
                                    {{ row.label || '—' }}
                                    <span
                                        v-if="row.notes"
                                        class="block text-xs text-muted-foreground"
                                        >{{ row.notes }}</span
                                    >
                                </td>
                                <td class="px-4 py-2 text-right tabular-nums">
                                    {{ formatMm(row.value_mm) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p v-if="!areas.length" class="text-sm text-muted-foreground">
                    Esta versión aún no tiene medidas.
                </p>

                <div
                    v-for="group in [
                        {
                            key: 'photos',
                            title: 'Fotos',
                            files: selected.photos,
                        },
                        {
                            key: 'sketches',
                            title: 'Croquis',
                            files: selected.sketches,
                        },
                    ]"
                    :key="group.key"
                >
                    <h3 class="mb-2 text-sm font-semibold">
                        {{ group.title }}
                    </h3>
                    <p
                        v-if="!group.files.length"
                        class="text-sm text-muted-foreground"
                    >
                        Sin {{ group.title.toLowerCase() }}.
                    </p>
                    <ul v-else class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                        <li v-for="f in group.files" :key="f.id">
                            <a
                                :href="f.url ?? undefined"
                                target="_blank"
                                rel="noopener"
                                class="block overflow-hidden rounded-lg border bg-muted"
                            >
                                <img
                                    v-if="f.is_image && f.url"
                                    :src="f.url"
                                    :alt="f.caption || f.name"
                                    loading="lazy"
                                    class="aspect-square w-full object-cover"
                                />
                                <span
                                    v-else
                                    class="flex aspect-square items-center justify-center p-2 text-center text-xs"
                                    >{{ f.name }}</span
                                >
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </SectionCard>
    </div>
</template>
