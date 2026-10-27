<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Eye, Lock } from '@lucide/vue';
import { computed, ref } from 'vue';
import EmptyState from '@/components/ds/EmptyState.vue';
import SectionCard from '@/components/ds/SectionCard.vue';
import Timeline from '@/components/ds/Timeline.vue';
import type { TimelineEvent } from '@/components/ds/Timeline.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { cn } from '@/lib/utils';
import notas from '@/routes/admin/proyectos/notas';
import type { ProjectEventItem } from '@/types/projects';
import type { HubCan, HubProject } from './types';

const props = defineProps<{
    project: HubProject;
    events: ProjectEventItem[];
    can: HubCan;
}>();

const filter = ref<'all' | 'client' | 'internal'>('all');

const form = useForm({
    title: '',
    body: '',
    visibility: 'internal' as 'internal' | 'client',
});

function submit() {
    form.post(notas.store(props.project.uuid).url, {
        preserveScroll: true,
        onSuccess: () => form.reset('title', 'body'),
    });
}

const toneFor = (e: ProjectEventItem): TimelineEvent['tone'] => {
    if (e.type === 'status_changed') return 'brand';
    if (
        e.type.includes('paid') ||
        e.type.includes('approved') ||
        e.type === 'quote_accepted'
    )
        return 'success';
    if (
        e.type.includes('rejected') ||
        e.type.includes('refunded') ||
        e.type.includes('changes')
    )
        return 'warning';

    return e.visibility === 'client' ? 'info' : 'neutral';
};

const isClient = (id: string | number) =>
    props.events.find((e) => e.id === id)?.visibility === 'client';

const items = computed<TimelineEvent[]>(() =>
    props.events
        .filter((e) => filter.value === 'all' || e.visibility === filter.value)
        .map((e) => ({
            id: e.id,
            title: e.title,
            description: e.body,
            date: e.occurred_at,
            actor: e.actor,
            tone: toneFor(e),
        })),
);
</script>

<template>
    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_360px]">
        <SectionCard
            title="Cronología"
            description="Todo lo que ha pasado en el proyecto. Lo marcado como «Cliente» también aparece en su portal."
        >
            <template #actions>
                <ToggleGroup
                    v-model="filter"
                    type="single"
                    size="sm"
                    variant="outline"
                    aria-label="Filtrar eventos"
                >
                    <ToggleGroupItem value="all">Todo</ToggleGroupItem>
                    <ToggleGroupItem value="client">Cliente</ToggleGroupItem>
                    <ToggleGroupItem value="internal">Internos</ToggleGroupItem>
                </ToggleGroup>
            </template>
            <EmptyState
                v-if="!items.length"
                compact
                title="Sin eventos en este filtro"
                description="Cambia el filtro o agrega una nota."
            />
            <Timeline v-else :events="items" relative-dates>
                <template #extra="{ event }">
                    <span
                        :class="
                            cn(
                                'mt-1.5 inline-flex items-center gap-1 text-xs',
                                isClient(event.id)
                                    ? 'text-info-ink'
                                    : 'text-muted-foreground',
                            )
                        "
                    >
                        <component
                            :is="isClient(event.id) ? Eye : Lock"
                            class="size-3"
                            aria-hidden="true"
                        />
                        {{
                            isClient(event.id)
                                ? 'Visible para el cliente'
                                : 'Solo equipo'
                        }}
                    </span>
                </template>
            </Timeline>
        </SectionCard>

        <SectionCard v-if="can.manage" title="Agregar a la cronología">
            <form class="flex flex-col gap-4" @submit.prevent="submit">
                <div class="grid gap-1.5">
                    <span class="text-sm font-medium" id="vis-label"
                        >¿Quién lo ve?</span
                    >
                    <ToggleGroup
                        v-model="form.visibility"
                        type="single"
                        variant="outline"
                        class="w-full"
                        aria-labelledby="vis-label"
                    >
                        <ToggleGroupItem value="internal" class="flex-1"
                            ><Lock class="size-4" aria-hidden="true" /> Solo
                            equipo</ToggleGroupItem
                        >
                        <ToggleGroupItem value="client" class="flex-1"
                            ><Eye class="size-4" aria-hidden="true" /> También
                            el cliente</ToggleGroupItem
                        >
                    </ToggleGroup>
                    <p
                        v-if="form.visibility === 'client'"
                        class="text-xs text-muted-foreground"
                    >
                        Escríbelo como se lo dirías al cliente: claro y sin
                        términos internos.
                    </p>
                </div>
                <div class="grid gap-1.5">
                    <Label for="ev-title">Título</Label>
                    <Input
                        id="ev-title"
                        v-model="form.title"
                        maxlength="200"
                        :placeholder="
                            form.visibility === 'client'
                                ? 'Ej. Ya tenemos tus herrajes'
                                : 'Ej. Cliente pidió cambiar jaladeras'
                        "
                    />
                    <InputError :message="form.errors.title" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="ev-body">Detalle (opcional)</Label>
                    <Textarea
                        id="ev-body"
                        v-model="form.body"
                        rows="4"
                        maxlength="4000"
                    />
                    <InputError :message="form.errors.body" />
                </div>
                <Button
                    type="submit"
                    :disabled="!form.title.trim() || form.processing"
                >
                    {{
                        form.processing
                            ? 'Guardando…'
                            : form.visibility === 'client'
                              ? 'Publicar para el cliente'
                              : 'Guardar nota interna'
                    }}
                </Button>
            </form>
        </SectionCard>
    </div>
</template>
