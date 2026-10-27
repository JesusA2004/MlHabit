<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { BadgeCheck, MessageSquareWarning } from '@lucide/vue';
import {
    DateText,
    EmptyState,
    PageContainer,
    PageHeader,
    SectionCard,
    StatusBadge,
} from '@/components/ds';
import { Button } from '@/components/ui/button';
import type { EnumValue } from '@/types/projects';

defineProps<{
    incidents: {
        id: number;
        folio: string;
        title: string;
        status: EnumValue | null;
        created_at: string | null;
        resolved_at: string | null;
        resolution: string | null;
    }[];
    warranties: {
        id: number;
        folio: string;
        type: EnumValue | null;
        status: EnumValue | null;
        starts_on: string | null;
        ends_on: string | null;
        scope: string | null;
        exclusions: string | null;
    }[];
    reportUrl: string | null;
    hasProjects: boolean;
}>();
</script>

<template>
    <Head title="Incidencias y garantías" />

    <PageContainer width="narrow" class="flex flex-col gap-6">
        <PageHeader
            title="Incidencias y garantías"
            description="Si algo no está bien con tu mueble, aquí lo atendemos."
        >
            <template v-if="reportUrl && hasProjects" #actions>
                <Button as-child
                    ><Link :href="reportUrl">Reportar un problema</Link></Button
                >
            </template>
        </PageHeader>

        <SectionCard title="Tus garantías" :icon="BadgeCheck">
            <ul v-if="warranties.length" class="flex flex-col gap-4">
                <li
                    v-for="w in warranties"
                    :key="w.id"
                    class="rounded-lg border p-4"
                >
                    <div
                        class="flex flex-wrap items-center justify-between gap-2"
                    >
                        <p class="font-medium text-foreground">
                            {{ w.type?.label ?? 'Garantía' }} · {{ w.folio }}
                        </p>
                        <StatusBadge
                            v-if="w.status"
                            :tone="w.status.tone"
                            :label="w.status.label"
                            size="sm"
                        />
                    </div>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Del <DateText :value="w.starts_on" format="long" /> al
                        <DateText :value="w.ends_on" format="long" />
                    </p>
                    <p
                        v-if="w.scope"
                        class="mt-2 text-sm whitespace-pre-line text-foreground"
                    >
                        {{ w.scope }}
                    </p>
                    <p
                        v-if="w.exclusions"
                        class="mt-2 text-sm whitespace-pre-line text-muted-foreground"
                    >
                        <span class="font-medium">No cubre:</span>
                        {{ w.exclusions }}
                    </p>
                </li>
            </ul>
            <p v-else class="text-sm text-muted-foreground">
                Tu garantía aparecerá aquí cuando entreguemos tu proyecto.
            </p>
        </SectionCard>

        <SectionCard title="Reportes" :icon="MessageSquareWarning" flush>
            <ul v-if="incidents.length" class="divide-y">
                <li
                    v-for="i in incidents"
                    :key="i.id"
                    class="px-4 py-3.5 sm:px-5"
                >
                    <div
                        class="flex flex-wrap items-center justify-between gap-2"
                    >
                        <p class="text-sm font-medium text-foreground">
                            {{ i.title }}
                        </p>
                        <StatusBadge
                            v-if="i.status"
                            :tone="i.status.tone"
                            :label="i.status.label"
                            size="sm"
                        />
                    </div>
                    <p class="text-xs text-muted-foreground">
                        {{ i.folio }} · reportado el
                        <DateText :value="i.created_at" format="medium" />
                    </p>
                    <p
                        v-if="i.resolution"
                        class="mt-2 text-sm whitespace-pre-line text-foreground"
                    >
                        {{ i.resolution }}
                    </p>
                </li>
            </ul>
            <EmptyState
                v-else
                compact
                :bordered="false"
                title="No tienes reportes abiertos"
                :description="
                    reportUrl && hasProjects
                        ? 'Si notas algún detalle, repórtalo y te contactamos para resolverlo.'
                        : 'Si notas algún detalle, escríbele a tu asesor desde Ayuda.'
                "
            />
        </SectionCard>
    </PageContainer>
</template>
