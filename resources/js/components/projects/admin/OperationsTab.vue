<script setup lang="ts">
import { BadgeCheck, Factory, ShieldAlert, Wrench } from '@lucide/vue';
import DateText from '@/components/ds/DateText.vue';
import SectionCard from '@/components/ds/SectionCard.vue';
import StatusBadge from '@/components/ds/StatusBadge.vue';
import type { HubCan, HubOperations } from './types';

defineProps<{ operations: HubOperations; can: HubCan }>();

const str = (v: unknown) => (typeof v === 'string' ? v : null);
</script>

<template>
    <div class="grid gap-6 md:grid-cols-2">
        <SectionCard title="Fabricación" :icon="Factory" flush>
            <template #actions
                ><a
                    href="/admin/fabricacion"
                    class="text-sm text-primary hover:underline"
                    >Ir a fabricación</a
                ></template
            >
            <p
                v-if="!operations.fabrication.length"
                class="px-4 py-4 text-sm text-muted-foreground"
            >
                Sin órdenes. Se emiten con el diseño aprobado técnicamente y el
                anticipo cubierto.
            </p>
            <ul v-else class="flex flex-col divide-y text-sm">
                <li
                    v-for="o in operations.fabrication"
                    :key="o.id"
                    class="flex items-center justify-between gap-3 px-4 py-3"
                >
                    <span>
                        <span class="text-xs tabular-nums">{{ o.folio }}</span>
                        <span
                            v-if="str(o.due_on)"
                            class="block text-muted-foreground"
                            >Compromiso <DateText :value="str(o.due_on)"
                        /></span>
                    </span>
                    <StatusBadge
                        v-if="o.status"
                        :tone="o.status.tone"
                        :label="o.status.label"
                        size="sm"
                    />
                </li>
            </ul>
        </SectionCard>

        <SectionCard title="Instalación" :icon="Wrench" flush>
            <template #actions
                ><a
                    href="/admin/instalaciones"
                    class="text-sm text-primary hover:underline"
                    >Ir a instalaciones</a
                ></template
            >
            <p
                v-if="!operations.installations.length"
                class="px-4 py-4 text-sm text-muted-foreground"
            >
                Sin órdenes de instalación todavía.
            </p>
            <ul v-else class="flex flex-col divide-y text-sm">
                <li
                    v-for="o in operations.installations"
                    :key="o.id"
                    class="flex items-center justify-between gap-3 px-4 py-3"
                >
                    <span>
                        <span class="text-xs tabular-nums">{{ o.folio }}</span>
                        <span
                            v-if="str(o.scheduled_start)"
                            class="block text-muted-foreground"
                            ><DateText
                                :value="str(o.scheduled_start)"
                                format="datetime"
                        /></span>
                    </span>
                    <StatusBadge
                        v-if="o.status"
                        :tone="o.status.tone"
                        :label="o.status.label"
                        size="sm"
                    />
                </li>
            </ul>
        </SectionCard>

        <SectionCard title="Incidencias" :icon="ShieldAlert" flush>
            <template #actions
                ><a
                    href="/admin/incidencias"
                    class="text-sm text-primary hover:underline"
                    >Ir a incidencias</a
                ></template
            >
            <p
                v-if="!operations.incidents.length"
                class="px-4 py-4 text-sm text-muted-foreground"
            >
                Sin incidencias registradas.
            </p>
            <ul v-else class="flex flex-col divide-y text-sm">
                <li
                    v-for="o in operations.incidents"
                    :key="o.id"
                    class="flex items-center justify-between gap-3 px-4 py-3"
                >
                    <span
                        ><span class="text-xs tabular-nums">{{ o.folio }}</span>
                        <span class="block">{{ str(o.title) }}</span></span
                    >
                    <StatusBadge
                        v-if="o.status"
                        :tone="o.status.tone"
                        :label="o.status.label"
                        size="sm"
                    />
                </li>
            </ul>
        </SectionCard>

        <SectionCard title="Garantías" :icon="BadgeCheck" flush>
            <template #actions
                ><a
                    href="/admin/garantias"
                    class="text-sm text-primary hover:underline"
                    >Ir a garantías</a
                ></template
            >
            <p
                v-if="!operations.warranties.length"
                class="px-4 py-4 text-sm text-muted-foreground"
            >
                Las garantías se emiten al entregar el proyecto.
            </p>
            <ul v-else class="flex flex-col divide-y text-sm">
                <li
                    v-for="o in operations.warranties"
                    :key="o.id"
                    class="flex items-center justify-between gap-3 px-4 py-3"
                >
                    <span>
                        <span class="text-xs tabular-nums">{{ o.folio }}</span>
                        <span class="block text-muted-foreground"
                            >Hasta <DateText :value="str(o.ends_on)"
                        /></span>
                    </span>
                    <StatusBadge
                        v-if="o.status"
                        :tone="o.status.tone"
                        :label="o.status.label"
                        size="sm"
                    />
                </li>
            </ul>
        </SectionCard>
    </div>
</template>
