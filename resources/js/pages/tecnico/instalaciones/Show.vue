<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    AlertTriangle,
    CalendarClock,
    CheckCircle2,
    Circle,
    MapPin,
    Navigation,
    Plus,
    Send,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import {
    DateText,
    FormSheet,
    SearchSelect,
    StatusBadge,
} from '@/components/ds';
import type { SearchSelectOption } from '@/components/ds';
import InputError from '@/components/InputError.vue';
import ChecklistEditor from '@/components/operations/ChecklistEditor.vue';
import {
    checklistComplete,
    checklistFrom,
} from '@/components/operations/checklist';
import EvidenceUploader from '@/components/operations/EvidenceUploader.vue';
import MediaGrid from '@/components/operations/MediaGrid.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';
import instalaciones from '@/routes/tecnico/instalaciones';
import type {
    ChecklistEntry,
    EnumOption,
    InstallationDetail,
    InstallationItem,
} from '@/types/operations';

const props = defineProps<{
    order: InstallationDetail;
    criteria: { value: string; label: string }[];
    checkResults: EnumOption[];
    rateItems: {
        id: number;
        code: string;
        description: string;
        unit: string;
    }[];
    maxEvidenceBytes: number;
    openIncidents: {
        id: number;
        folio: string;
        title: string;
        description: string;
        severity: EnumOption;
    }[];
}>();

defineOptions({
    layout: { title: 'Instalación', backHref: '/tecnico/instalaciones' },
});

const status = computed(() => props.order.status.value);
const inProgress = computed(() => status.value === 'in_progress');
const workable = computed(() =>
    props.order.items.filter((i) => i.status.value !== 'rejected'),
);
const doneCount = computed(
    () => workable.value.filter((i) => i.status.value === 'done').length,
);
const allDone = computed(
    () =>
        workable.value.length > 0 && doneCount.value === workable.value.length,
);

const mapsUrl = computed(() =>
    props.order.address
        ? `https://www.google.com/maps/dir/?api=1&destination=${encodeURIComponent(props.order.address)}`
        : null,
);

// ------------------------------------------------------------ Iniciar / enviar
const starting = ref(false);
function start() {
    router.post(
        instalaciones.iniciar(props.order.id).url,
        {},
        {
            preserveScroll: true,
            onStart: () => (starting.value = true),
            onFinish: () => (starting.value = false),
            onError: (e) =>
                toast.error(
                    Object.values(e)[0] ?? 'No se pudo iniciar la instalación.',
                ),
        },
    );
}

const submitting = ref(false);
const submitError = ref<string | null>(null);
function submitEvidence() {
    submitError.value = null;
    router.post(
        instalaciones.enviar(props.order.id).url,
        {},
        {
            preserveScroll: true,
            onStart: () => (submitting.value = true),
            onFinish: () => (submitting.value = false),
            onError: (errors) =>
                (submitError.value =
                    errors.items ??
                    errors.status ??
                    'No se pudo enviar la evidencia.'),
        },
    );
}

// ------------------------------------------------------------ Partida
const itemOpen = ref(false);
const current = ref<InstallationItem | null>(null);
const mediaIds = ref<number[]>([]);
const checklist = ref<ChecklistEntry[]>([]);
const notes = ref('');
const uploading = ref(false);
const saving = ref(false);
const itemErrors = ref<Record<string, string>>({});

function openItem(item: InstallationItem) {
    current.value = item;
    mediaIds.value = [];
    checklist.value = checklistFrom(
        props.criteria,
        item.installerCheck?.checks,
    );
    notes.value = item.installerCheck?.observations ?? '';
    itemErrors.value = {};
    itemOpen.value = true;
}

const hasEvidence = computed(
    () => (current.value?.evidence.length ?? 0) + mediaIds.value.length > 0,
);
const canSaveItem = computed(
    () =>
        inProgress.value &&
        hasEvidence.value &&
        !uploading.value &&
        checklistComplete(checklist.value),
);

function saveItem() {
    if (!current.value) return;
    router.post(
        instalaciones.partidas.completar({
            installationOrder: props.order.id,
            item: current.value.id,
        }).url,
        {
            media_ids: mediaIds.value,
            notes: notes.value || null,
            checklist: checklist.value.map((c) => ({
                criterion: c.criterion,
                result: c.result,
                notes: c.notes || null,
            })),
        },
        {
            preserveScroll: true,
            onStart: () => (saving.value = true),
            onFinish: () => (saving.value = false),
            onSuccess: () => (itemOpen.value = false),
            onError: (errors) => (itemErrors.value = errors),
        },
    );
}

// ------------------------------------------------------------ Extra
const extraOpen = ref(false);
const extra = ref({
    rate_card_item_id: null as number | null,
    description: '',
    unit: '',
    quantity: '1',
    unit_price: '',
});
const extraErrors = ref<Record<string, string>>({});
const savingExtra = ref(false);

const rateOptions = computed<SearchSelectOption<number>[]>(() =>
    props.rateItems.map((i) => ({
        value: i.id,
        label: `${i.code} · ${i.description}`,
        description: i.unit,
    })),
);

function pickRateItem(id: number | null) {
    extra.value.rate_card_item_id = id;
    const item = props.rateItems.find((i) => i.id === id);
    if (item) {
        extra.value.description = item.description;
        extra.value.unit = item.unit;
        extra.value.unit_price = '';
    }
}

function openExtra() {
    extra.value = {
        rate_card_item_id: null,
        description: '',
        unit: '',
        quantity: '1',
        unit_price: '',
    };
    extraErrors.value = {};
    extraOpen.value = true;
}

function saveExtra() {
    router.post(
        instalaciones.extras.store(props.order.id).url,
        {
            ...extra.value,
            unit_price: extra.value.rate_card_item_id
                ? null
                : extra.value.unit_price,
        },
        {
            preserveScroll: true,
            onStart: () => (savingExtra.value = true),
            onFinish: () => (savingExtra.value = false),
            onSuccess: () => (extraOpen.value = false),
            onError: (errors) => (extraErrors.value = errors),
        },
    );
}

const canProposeExtra = computed(() =>
    [
        'scheduled',
        'in_progress',
        'client_review',
        'evidence_submitted',
        'quality_review',
    ].includes(status.value),
);

const WAITING_COPY: Record<string, string> = {
    client_review: 'Enviaste la evidencia. Esperamos la revisión del cliente.',
    evidence_submitted:
        'Enviaste la evidencia. Esperamos la revisión del cliente.',
    quality_review: 'El equipo de calidad está revisando tu trabajo.',
    approved: 'Calidad aprobó esta instalación. ¡Buen trabajo!',
    payment_authorized:
        'Calidad aprobó esta instalación y su pago ya está autorizado.',
    paid: 'Instalación cerrada y pagada.',
};
</script>

<template>
    <Head :title="`Instalación ${order.folio}`" />

    <div class="mx-auto flex w-full max-w-3xl flex-col gap-5 pb-28">
        <header class="flex flex-col gap-2">
            <StatusBadge
                :tone="order.status.tone"
                :label="order.status.label"
                class="self-start"
            />
            <h1 class="font-display text-2xl font-semibold tracking-tight">
                {{ order.project.name }}
            </h1>
            <p class="text-sm text-muted-foreground tabular-nums">
                {{ order.folio
                }}<template v-if="order.project.customer">
                    · {{ order.project.customer }}</template
                >
            </p>
        </header>

        <section class="flex flex-col gap-3 rounded-2xl border bg-card p-4">
            <p
                v-if="order.scheduledStart"
                class="flex items-center gap-2 text-sm"
            >
                <CalendarClock
                    class="size-4 shrink-0 text-muted-foreground"
                    aria-hidden="true"
                />
                <DateText :value="order.scheduledStart" format="datetime" />
                <template v-if="order.scheduledEnd">
                    a <DateText :value="order.scheduledEnd" format="time"
                /></template>
            </p>
            <p v-if="order.address" class="flex items-start gap-2 text-sm">
                <MapPin
                    class="mt-0.5 size-4 shrink-0 text-muted-foreground"
                    aria-hidden="true"
                />
                <span>
                    {{ order.address }}
                    <span
                        v-if="order.addressReferences"
                        class="block text-xs text-muted-foreground"
                        >{{ order.addressReferences }}</span
                    >
                </span>
            </p>
            <a
                v-if="mapsUrl"
                :href="mapsUrl"
                target="_blank"
                rel="noopener"
                class="inline-flex h-12 items-center justify-center gap-2 rounded-xl border text-sm font-medium hover:bg-muted"
            >
                <Navigation class="size-4" aria-hidden="true" /> Cómo llegar
            </a>
            <p
                v-if="order.notes"
                class="rounded-lg bg-muted/60 p-3 text-sm whitespace-pre-line"
            >
                {{ order.notes }}
            </p>
        </section>

        <section
            v-if="openIncidents.length"
            class="flex flex-col gap-3 rounded-2xl border border-warning/40 bg-warning-soft p-4 text-warning-ink"
            aria-labelledby="pendientes"
        >
            <h2 id="pendientes" class="flex items-center gap-2 font-semibold">
                <AlertTriangle class="size-5" aria-hidden="true" /> Pendientes
                por corregir
            </h2>
            <ul class="flex flex-col gap-2">
                <li
                    v-for="incident in openIncidents"
                    :key="incident.id"
                    class="rounded-lg bg-background/70 p-3 text-foreground"
                >
                    <span class="flex items-center gap-2 text-sm font-medium">
                        {{ incident.title }}
                        <StatusBadge
                            :tone="incident.severity.tone"
                            :label="incident.severity.label"
                            size="sm"
                        />
                    </span>
                    <span
                        class="mt-1 block text-sm whitespace-pre-line text-muted-foreground"
                        >{{ incident.description }}</span
                    >
                </li>
            </ul>
        </section>

        <p
            v-if="WAITING_COPY[status]"
            class="rounded-2xl border bg-muted/40 p-4 text-sm"
        >
            {{ WAITING_COPY[status] }}
        </p>

        <section aria-labelledby="partidas" class="flex flex-col gap-3">
            <div class="flex items-end justify-between gap-3">
                <h2
                    id="partidas"
                    class="font-display text-lg font-semibold tracking-tight"
                >
                    Partidas
                </h2>
                <span class="text-sm text-muted-foreground tabular-nums"
                    >{{ doneCount }} de {{ workable.length }} terminadas</span
                >
            </div>
            <ul class="flex flex-col gap-2">
                <li v-for="item in order.items" :key="item.id">
                    <button
                        type="button"
                        :disabled="item.status.value === 'rejected'"
                        :class="
                            cn(
                                'flex min-h-16 w-full items-center gap-3 rounded-2xl border bg-card p-4 text-left transition-colors active:bg-muted/60 disabled:opacity-60',
                                item.status.value === 'done' &&
                                    'border-success/30',
                            )
                        "
                        @click="openItem(item)"
                    >
                        <CheckCircle2
                            v-if="item.status.value === 'done'"
                            class="size-6 shrink-0 text-success"
                            aria-hidden="true"
                        />
                        <Circle
                            v-else
                            class="size-6 shrink-0 text-muted-foreground"
                            aria-hidden="true"
                        />
                        <span class="flex min-w-0 flex-1 flex-col">
                            <span class="font-medium">{{
                                item.description
                            }}</span>
                            <span class="text-xs text-muted-foreground">
                                {{ item.code }} · {{ Number(item.quantity) }}
                                {{ item.unit }}
                                <template v-if="item.evidence.length">
                                    · {{ item.evidence.length }} archivo{{
                                        item.evidence.length > 1 ? 's' : ''
                                    }}</template
                                >
                            </span>
                        </span>
                        <StatusBadge
                            v-if="item.pendingExtra"
                            tone="warning"
                            label="Extra por aprobar"
                            size="sm"
                        />
                        <StatusBadge
                            v-else-if="
                                item.isExtra && item.status.value === 'rejected'
                            "
                            tone="danger"
                            label="Extra rechazado"
                            size="sm"
                        />
                        <StatusBadge
                            v-else-if="item.isExtra"
                            tone="info"
                            label="Extra"
                            size="sm"
                        />
                    </button>
                </li>
            </ul>
            <Button
                v-if="canProposeExtra"
                variant="outline"
                class="h-12"
                @click="openExtra"
                ><Plus class="size-4" /> Proponer trabajo extra</Button
            >
        </section>
    </div>

    <!-- Acción principal fija abajo -->
    <div
        v-if="status === 'scheduled' || inProgress"
        class="fixed inset-x-0 bottom-[calc(4rem+env(safe-area-inset-bottom))] z-20 border-t bg-background/95 p-3 backdrop-blur md:bottom-0"
    >
        <div class="mx-auto flex max-w-3xl flex-col gap-1">
            <Button
                v-if="status === 'scheduled'"
                class="h-12 w-full text-base"
                :disabled="starting"
                @click="start"
            >
                {{ starting ? 'Iniciando…' : 'Iniciar instalación' }}
            </Button>
            <template v-else>
                <Button
                    class="h-12 w-full text-base"
                    :disabled="!allDone || submitting"
                    @click="submitEvidence"
                >
                    <Send class="size-4" />
                    {{
                        submitting ? 'Enviando…' : 'Enviar evidencia al cliente'
                    }}
                </Button>
                <p
                    v-if="!allDone"
                    class="text-center text-xs text-muted-foreground"
                >
                    Termina todas las partidas con su evidencia para enviar.
                </p>
                <p
                    v-if="submitError"
                    class="text-center text-sm text-destructive"
                    role="alert"
                >
                    {{ submitError }}
                </p>
            </template>
        </div>
    </div>

    <FormSheet
        v-model:open="itemOpen"
        :title="current?.description ?? 'Partida'"
        :description="
            current
                ? `${current.code} · ${Number(current.quantity)} ${current.unit}`
                : undefined
        "
        size="lg"
    >
        <div v-if="current" class="flex flex-col gap-6">
            <p v-if="!inProgress" class="rounded-lg bg-muted/60 p-3 text-sm">
                {{
                    status === 'scheduled'
                        ? 'Inicia la instalación para registrar avances.'
                        : 'Esta instalación ya no admite cambios desde la app.'
                }}
            </p>

            <section class="flex flex-col gap-3">
                <h3 class="font-medium">Evidencia</h3>
                <MediaGrid
                    :items="current.evidence"
                    empty-text="Aún no hay fotos de esta partida."
                />
                <EvidenceUploader
                    v-if="inProgress"
                    v-model="mediaIds"
                    :max-size="maxEvidenceBytes"
                    @busy="uploading = $event"
                />
                <InputError :message="itemErrors.media_ids" />
            </section>

            <section class="flex flex-col gap-3">
                <h3 class="font-medium">Autorrevisión</h3>
                <ChecklistEditor
                    v-model="checklist"
                    :criteria="criteria"
                    :disabled="!inProgress"
                    :error="itemErrors.checklist"
                />
            </section>

            <section class="flex flex-col gap-1.5">
                <Label for="item-notes">Notas (opcional)</Label>
                <Textarea
                    id="item-notes"
                    v-model="notes"
                    rows="3"
                    :disabled="!inProgress"
                    placeholder="Algo que la oficina o calidad deban saber"
                />
            </section>

            <InputError :message="itemErrors.status ?? itemErrors.item" />
        </div>
        <template v-if="inProgress" #footer>
            <Button variant="outline" class="h-12" @click="itemOpen = false"
                >Cancelar</Button
            >
            <Button
                class="h-12"
                :disabled="!canSaveItem || saving"
                @click="saveItem"
            >
                {{
                    saving
                        ? 'Guardando…'
                        : uploading
                          ? 'Subiendo archivos…'
                          : 'Marcar como terminada'
                }}
            </Button>
        </template>
    </FormSheet>

    <FormSheet
        v-model:open="extraOpen"
        title="Proponer trabajo extra"
        description="La oficina lo revisa y aprueba antes de que cuente en la orden."
    >
        <form
            id="extra-form"
            class="flex flex-col gap-4"
            @submit.prevent="saveExtra"
        >
            <div class="grid gap-1.5">
                <Label>Concepto del tarifario</Label>
                <SearchSelect
                    :model-value="extra.rate_card_item_id"
                    :options="rateOptions"
                    clearable
                    placeholder="Elegir concepto (o captura uno nuevo abajo)"
                    @update:model-value="pickRateItem"
                />
            </div>
            <div class="grid gap-1.5">
                <Label for="x-desc">Descripción</Label>
                <Input id="x-desc" v-model="extra.description" class="h-12" />
                <InputError :message="extraErrors.description" />
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div class="grid gap-1.5">
                    <Label for="x-qty">Cantidad</Label>
                    <Input
                        id="x-qty"
                        v-model="extra.quantity"
                        inputmode="decimal"
                        class="h-12"
                    />
                    <InputError :message="extraErrors.quantity" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="x-unit">Unidad</Label>
                    <Input
                        id="x-unit"
                        v-model="extra.unit"
                        class="h-12"
                        placeholder="pieza, m, ml…"
                    />
                    <InputError :message="extraErrors.unit" />
                </div>
            </div>
            <div v-if="!extra.rate_card_item_id" class="grid gap-1.5">
                <Label for="x-price">Precio unitario propuesto</Label>
                <Input
                    id="x-price"
                    v-model="extra.unit_price"
                    inputmode="decimal"
                    class="h-12"
                    placeholder="0.00"
                />
                <InputError :message="extraErrors.unit_price" />
            </div>
            <InputError
                :message="extraErrors.status ?? extraErrors.rate_card_item_id"
            />
        </form>
        <template #footer>
            <Button variant="outline" class="h-12" @click="extraOpen = false"
                >Cancelar</Button
            >
            <Button
                type="submit"
                form="extra-form"
                class="h-12"
                :disabled="
                    savingExtra ||
                    !extra.description.trim() ||
                    !extra.unit.trim()
                "
            >
                {{ savingExtra ? 'Enviando…' : 'Enviar a aprobación' }}
            </Button>
        </template>
    </FormSheet>
</template>
