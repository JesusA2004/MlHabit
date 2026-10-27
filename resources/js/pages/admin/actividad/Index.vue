<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Activity, ArrowRight, ChevronLeft, ChevronRight } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import DatePicker from '@/components/ds/DatePicker.vue';
import DateText from '@/components/ds/DateText.vue';
import EmptyState from '@/components/ds/EmptyState.vue';
import FilterBar from '@/components/ds/FilterBar.vue';
import PageContainer from '@/components/ds/PageContainer.vue';
import PageHeader from '@/components/ds/PageHeader.vue';
import SearchSelect from '@/components/ds/SearchSelect.vue';
import type { SearchSelectOption } from '@/components/ds/SearchSelect.vue';
import StatusBadge from '@/components/ds/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import actividad from '@/routes/admin/actividad';

type DiffRow = { field: string; label: string; before: string; after: string };
type Entry = {
    id: number;
    action: string;
    actionLabel: string;
    module: string;
    moduleLabel: string;
    userName: string;
    userRole: string | null;
    subjectLabel: string | null;
    subjectId: number | null;
    subjectReference: string | null;
    diff: DiffRow[];
    details: { label: string; value: string }[];
    result: string;
    ipAddress: string | null;
    createdAt: string | null;
};
type Option = { value: string; label: string };

const props = defineProps<{
    logs: {
        data: Entry[];
        currentPage: number;
        hasMore: boolean;
        prevUrl: string | null;
        nextUrl: string | null;
    };
    filters: {
        usuario: string;
        modulo: string;
        entidad: string;
        desde: string | null;
        hasta: string | null;
        q: string;
    };
    options: { users: Option[]; modules: Option[]; entities: Option[] };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Panel', href: '/admin' },
            { title: 'Actividad', href: '/admin/actividad' },
        ],
    },
});

const search = ref(props.filters.q);
const user = ref<string | null>(props.filters.usuario || null);
const module = ref<string | null>(props.filters.modulo || null);
const entity = ref<string | null>(props.filters.entidad || null);
const from = ref<string | null>(props.filters.desde);
const to = ref<string | null>(props.filters.hasta);

const activeCount = computed(
    () =>
        [user.value, module.value, entity.value, from.value, to.value].filter(
            Boolean,
        ).length,
);

const asOptions = (items: Option[]): SearchSelectOption<string>[] =>
    items.map((o) => ({ value: o.value, label: o.label }));

function reload() {
    router.get(
        actividad.index().url,
        {
            q: search.value || undefined,
            usuario: user.value || undefined,
            modulo: module.value || undefined,
            entidad: entity.value || undefined,
            desde: from.value || undefined,
            hasta: to.value || undefined,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: ['logs', 'filters'],
        },
    );
}

watch([search, user, module, entity, from, to], reload);

function clearFilters() {
    search.value = '';
    user.value = null;
    module.value = null;
    entity.value = null;
    from.value = null;
    to.value = null;
}

const resultTone = (result: string) =>
    result === 'success'
        ? 'success'
        : result === 'failed' || result === 'error'
          ? 'danger'
          : 'neutral';
const resultLabel = (result: string) =>
    result === 'success'
        ? 'Correcto'
        : result === 'failed' || result === 'error'
          ? 'Fallido'
          : result;
</script>

<template>
    <Head title="Actividad" />

    <PageContainer>
        <PageHeader
            title="Actividad"
            description="Bitácora de lo que pasa en la plataforma: quién hizo qué, cuándo y qué cambió. Las contraseñas, llaves y datos de tarjeta nunca se muestran."
        />

        <FilterBar
            v-model:search="search"
            placeholder="Buscar por acción o persona…"
            :active-count="activeCount"
            @clear="clearFilters"
        >
            <template #filters>
                <SearchSelect
                    v-model="user"
                    :options="asOptions(options.users)"
                    placeholder="Cualquier persona"
                    clearable
                    class="w-full md:w-52"
                />
                <SearchSelect
                    v-model="module"
                    :options="asOptions(options.modules)"
                    placeholder="Cualquier módulo"
                    clearable
                    class="w-full md:w-48"
                />
                <SearchSelect
                    v-model="entity"
                    :options="asOptions(options.entities)"
                    placeholder="Cualquier entidad"
                    clearable
                    class="w-full md:w-52"
                />
                <DatePicker
                    v-model="from"
                    placeholder="Desde"
                    :max="to ?? undefined"
                    clearable
                    class="w-full md:w-44"
                    :display-format="{
                        day: 'numeric',
                        month: 'short',
                        year: 'numeric',
                    }"
                />
                <DatePicker
                    v-model="to"
                    placeholder="Hasta"
                    :min="from ?? undefined"
                    clearable
                    class="w-full md:w-44"
                    :display-format="{
                        day: 'numeric',
                        month: 'short',
                        year: 'numeric',
                    }"
                />
            </template>
        </FilterBar>

        <EmptyState
            v-if="logs.data.length === 0"
            :icon="Activity"
            :title="
                activeCount || filters.q
                    ? 'Sin resultados'
                    : 'Aún no hay actividad registrada'
            "
            :description="
                activeCount || filters.q
                    ? 'Ningún movimiento coincide con los filtros. Prueba con otro rango de fechas.'
                    : 'Cuando el equipo cree, edite o autorice algo, aparecerá aquí.'
            "
        />

        <ol
            v-else
            class="divide-y overflow-hidden rounded-xl border bg-card"
            aria-label="Movimientos"
        >
            <li v-for="entry in logs.data" :key="entry.id">
                <Collapsible v-slot="{ open }">
                    <div
                        class="flex flex-col gap-2 px-4 py-3 sm:flex-row sm:items-start sm:gap-4 sm:px-5"
                    >
                        <div class="min-w-0 flex-1">
                            <p
                                class="flex flex-wrap items-center gap-x-2 gap-y-1"
                            >
                                <span class="font-medium text-foreground">{{
                                    entry.actionLabel
                                }}</span>
                                <StatusBadge
                                    v-if="entry.result !== 'success'"
                                    size="sm"
                                    :tone="resultTone(entry.result)"
                                    :label="resultLabel(entry.result)"
                                />
                            </p>
                            <p class="mt-0.5 text-sm text-muted-foreground">
                                <span>{{ entry.userName }}</span>
                                <span v-if="entry.userRole">
                                    · {{ entry.userRole }}</span
                                >
                                <template v-if="entry.subjectLabel">
                                    · {{ entry.subjectLabel }}
                                    <span class="font-medium text-foreground">{{
                                        entry.subjectReference ??
                                        (entry.subjectId
                                            ? `#${entry.subjectId}`
                                            : '')
                                    }}</span>
                                </template>
                                <span class="hidden sm:inline">
                                    · {{ entry.moduleLabel }}</span
                                >
                            </p>
                        </div>
                        <div
                            class="flex shrink-0 items-center gap-2 sm:flex-col sm:items-end"
                        >
                            <DateText
                                :value="entry.createdAt"
                                format="datetime"
                                class="text-sm text-muted-foreground tabular-nums"
                            />
                            <CollapsibleTrigger
                                v-if="entry.diff.length || entry.details.length"
                                as-child
                            >
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    class="h-7 px-2 text-xs"
                                    :aria-expanded="open"
                                >
                                    {{
                                        open ? 'Ocultar detalle' : 'Ver detalle'
                                    }}
                                </Button>
                            </CollapsibleTrigger>
                        </div>
                    </div>
                    <CollapsibleContent>
                        <div
                            class="grid gap-4 border-t bg-muted/30 px-4 py-4 sm:px-5"
                        >
                            <table
                                v-if="entry.diff.length"
                                class="w-full text-sm"
                            >
                                <caption class="sr-only">
                                    Cambios antes y después
                                </caption>
                                <thead
                                    class="text-left text-xs text-muted-foreground"
                                >
                                    <tr>
                                        <th
                                            scope="col"
                                            class="pb-2 font-medium"
                                        >
                                            Campo
                                        </th>
                                        <th
                                            scope="col"
                                            class="pb-2 font-medium"
                                        >
                                            Antes
                                        </th>
                                        <th scope="col" class="w-6 pb-2">
                                            <span class="sr-only">a</span>
                                        </th>
                                        <th
                                            scope="col"
                                            class="pb-2 font-medium"
                                        >
                                            Después
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="row in entry.diff"
                                        :key="row.field"
                                        class="align-top"
                                    >
                                        <th
                                            scope="row"
                                            class="py-1.5 pr-3 font-normal text-muted-foreground"
                                        >
                                            {{ row.label }}
                                        </th>
                                        <td
                                            class="py-1.5 pr-3 break-words text-danger-ink line-through decoration-danger/40"
                                        >
                                            {{ row.before }}
                                        </td>
                                        <td
                                            class="py-1.5 text-muted-foreground"
                                        >
                                            <ArrowRight
                                                class="size-3.5"
                                                aria-hidden="true"
                                            />
                                        </td>
                                        <td
                                            class="py-1.5 break-words text-success-ink"
                                        >
                                            {{ row.after }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                            <dl
                                v-if="entry.details.length"
                                class="grid gap-x-6 gap-y-1.5 text-sm sm:grid-cols-[max-content_1fr]"
                            >
                                <template
                                    v-for="detail in entry.details"
                                    :key="detail.label"
                                >
                                    <dt class="text-muted-foreground">
                                        {{ detail.label }}
                                    </dt>
                                    <dd class="break-words">
                                        {{ detail.value }}
                                    </dd>
                                </template>
                            </dl>
                            <p
                                v-if="entry.ipAddress"
                                class="text-xs text-muted-foreground"
                            >
                                IP: {{ entry.ipAddress }} · Acción técnica:
                                <code>{{ entry.action }}</code>
                            </p>
                        </div>
                    </CollapsibleContent>
                </Collapsible>
            </li>
        </ol>

        <nav
            v-if="logs.prevUrl || logs.nextUrl"
            class="flex items-center justify-between gap-2"
            aria-label="Paginación de actividad"
        >
            <Button variant="outline" :disabled="!logs.prevUrl" as-child>
                <Link
                    v-if="logs.prevUrl"
                    :href="logs.prevUrl"
                    preserve-scroll
                    :only="['logs', 'filters']"
                    ><ChevronLeft class="size-4" /> Más recientes</Link
                >
                <span v-else><ChevronLeft class="size-4" /> Más recientes</span>
            </Button>
            <span class="text-sm text-muted-foreground tabular-nums"
                >Página {{ logs.currentPage }}</span
            >
            <Button variant="outline" :disabled="!logs.nextUrl" as-child>
                <Link
                    v-if="logs.nextUrl"
                    :href="logs.nextUrl"
                    preserve-scroll
                    :only="['logs', 'filters']"
                    >Anteriores <ChevronRight class="size-4"
                /></Link>
                <span v-else>Anteriores <ChevronRight class="size-4" /></span>
            </Button>
        </nav>
    </PageContainer>
</template>
