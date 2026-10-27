<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    CircleCheck,
    ClipboardCheck,
    MessageSquareQuote,
    Plus,
    ShieldAlert,
    Trash2,
    Wrench,
} from '@lucide/vue';
import { computed, reactive, ref } from 'vue';
import ConfirmDialog from '@/components/ds/ConfirmDialog.vue';
import DateText from '@/components/ds/DateText.vue';
import FormSheet from '@/components/ds/FormSheet.vue';
import PageContainer from '@/components/ds/PageContainer.vue';
import PageHeader from '@/components/ds/PageHeader.vue';
import SearchSelect from '@/components/ds/SearchSelect.vue';
import SectionCard from '@/components/ds/SectionCard.vue';
import StatusBadge from '@/components/ds/StatusBadge.vue';
import InputError from '@/components/InputError.vue';
import ChecklistEditor from '@/components/operations/ChecklistEditor.vue';
import {
    checklistComplete,
    checklistFrom,
} from '@/components/operations/checklist';
import MediaGrid from '@/components/operations/MediaGrid.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';
import calidad from '@/routes/admin/calidad';
import instalaciones from '@/routes/admin/instalaciones';
import type {
    ChecklistEntry,
    EnumOption,
    InstallationDetail,
    InstallationItem,
} from '@/types/operations';

const props = defineProps<{
    order: InstallationDetail;
    incidents: {
        id: number;
        folio: string;
        title: string;
        status: EnumOption;
        severity: EnumOption;
        origin: EnumOption;
    }[];
    criteria: { value: string; label: string }[];
    checkResults: EnumOption[];
    categories: EnumOption[];
    severities: EnumOption[];
    canReview: boolean;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Panel', href: '/admin' },
            { title: 'Calidad', href: '/admin/calidad' },
            { title: 'Revisión', href: '#' },
        ],
    },
});

const items = computed(() =>
    props.order.items.filter((i) => i.countsTowardsTotal),
);
const approvedCount = computed(
    () =>
        items.value.filter((i) => i.qualityCheck?.result.value === 'approved')
            .length,
);
const allApproved = computed(
    () => items.value.length > 0 && approvedCount.value === items.value.length,
);

// ---------------------------------------------------------------- Revisión por partida

type Review = {
    open: boolean;
    result: 'approved' | 'requires_fixes';
    observations: string;
    client_contacted: boolean;
    checklist: ChecklistEntry[];
    processing: boolean;
    errors: Record<string, string>;
};

const reviews = reactive<Record<number, Review>>({});

function reviewFor(item: InstallationItem): Review {
    if (!reviews[item.id]) {
        const qc = item.qualityCheck;
        reviews[item.id] = {
            open: !qc || qc.result.value === 'pending',
            result:
                qc?.result.value === 'requires_fixes'
                    ? 'requires_fixes'
                    : 'approved',
            observations: qc?.observations ?? '',
            client_contacted: qc?.clientContacted ?? false,
            checklist: checklistFrom(props.criteria, qc?.checks),
            processing: false,
            errors: {},
        };
    }

    return reviews[item.id];
}

function submitReview(item: InstallationItem) {
    const review = reviewFor(item);
    review.processing = true;
    review.errors = {};

    router.post(
        calidad.partidas.revisar([props.order.id, item.id]).url,
        {
            result: review.result,
            observations: review.observations || null,
            client_contacted: review.client_contacted,
            checklist: review.checklist.map((e) => ({
                criterion: e.criterion,
                result: e.result,
                notes: e.notes || null,
            })),
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                review.open = false;
            },
            onError: (errors) => (review.errors = errors),
            onFinish: () => (review.processing = false),
        },
    );
}

function firstError(
    errors: Record<string, string>,
    prefix: string,
): string | null {
    return (
        Object.entries(errors).find(([k]) => k.startsWith(prefix))?.[1] ?? null
    );
}

// ---------------------------------------------------------------- Aprobar

const approveOpen = ref(false);
const approveNotes = ref('');
const approveContacted = ref(false);
const confirmApprove = () =>
    new Promise<void>((resolve, reject) => {
        router.post(
            calidad.aprobar(props.order.id).url,
            {
                observations: approveNotes.value || null,
                client_contacted: approveContacted.value,
            },
            {
                onSuccess: () => resolve(),
                onError: (errors) =>
                    reject(
                        new Error(
                            Object.values(errors)[0] ?? 'No se pudo aprobar.',
                        ),
                    ),
            },
        );
    });

// ---------------------------------------------------------------- Correcciones

type Issue = {
    title: string;
    description: string;
    category: string;
    severity: string;
    item_id: number | null;
    visible_to_client: boolean;
};

const fixesOpen = ref(false);
const fixesForm = useForm({
    observations: '',
    client_contacted: false,
    issues: [] as Issue[],
});
const itemOptions = computed(() =>
    props.order.items.map((i) => ({
        value: i.id,
        label: `${i.code} · ${i.description}`,
    })),
);
const categoryOptions = computed(() =>
    props.categories.map((c) => ({ value: c.value, label: c.label })),
);
const severityOptions = computed(() =>
    props.severities.map((c) => ({ value: c.value, label: c.label })),
);

function newIssue(item?: InstallationItem): Issue {
    const review = item ? reviews[item.id] : undefined;

    return {
        title: item ? `Corregir ${item.code}` : '',
        description: review?.observations ?? '',
        category: 'installation_error',
        severity: 'medium',
        item_id: item?.id ?? null,
        visible_to_client: true,
    };
}

function openFixes() {
    fixesForm.clearErrors();
    const flagged = items.value.filter(
        (i) => i.qualityCheck?.result.value === 'requires_fixes',
    );
    fixesForm.issues = flagged.length
        ? flagged.map((i) => newIssue(i))
        : [newIssue()];
    fixesOpen.value = true;
}

function submitFixes() {
    fixesForm.post(calidad.correcciones(props.order.id).url, {
        onSuccess: () => (fixesOpen.value = false),
    });
}

function issueError(index: number, field: string): string | undefined {
    return (fixesForm.errors as Record<string, string>)[
        `issues.${index}.${field}`
    ];
}
</script>

<template>
    <Head :title="`Calidad ${order.folio}`" />

    <PageContainer>
        <PageHeader
            :title="`Revisión de ${order.folio}`"
            :description="`${order.project.name}${order.project.customer ? ' · ' + order.project.customer : ''}`"
        >
            <template #meta>
                <StatusBadge
                    :tone="order.status.tone"
                    :label="order.status.label"
                />
                <span>Instalador: {{ order.leadInstaller?.name ?? '—' }}</span>
                <Link
                    :href="instalaciones.show(order.id).url"
                    class="text-primary underline-offset-4 hover:underline"
                    >Ver orden completa</Link
                >
            </template>
        </PageHeader>

        <p
            v-if="!canReview"
            class="rounded-xl border bg-muted/50 px-4 py-3 text-sm text-muted-foreground"
        >
            {{
                order.status.value === 'quality_review'
                    ? 'Puedes consultar la revisión, pero dictaminar requiere el permiso de calidad.'
                    : 'Esta orden ya no está en revisión de calidad.'
            }}
        </p>

        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
            <div class="flex min-w-0 flex-col gap-5">
                <article
                    v-for="item in items"
                    :key="item.id"
                    class="overflow-hidden rounded-xl border bg-card shadow-xs"
                    :aria-labelledby="`item-${item.id}`"
                >
                    <header
                        class="flex flex-wrap items-start justify-between gap-3 border-b px-4 py-3 sm:px-5"
                    >
                        <div class="min-w-0">
                            <h2 :id="`item-${item.id}`" class="font-medium">
                                {{ item.code }} · {{ item.description }}
                                <StatusBadge
                                    v-if="item.isExtra"
                                    size="sm"
                                    tone="info"
                                    label="Extra"
                                    class="ml-1 align-middle"
                                />
                            </h2>
                            <p class="text-xs text-muted-foreground">
                                {{ Number(item.quantity) }} {{ item.unit }}
                            </p>
                        </div>
                        <StatusBadge
                            v-if="
                                item.qualityCheck &&
                                item.qualityCheck.result.value !== 'pending'
                            "
                            :tone="item.qualityCheck.result.tone"
                            :label="item.qualityCheck.result.label"
                        />
                        <StatusBadge
                            v-else
                            tone="neutral"
                            label="Sin revisar"
                        />
                    </header>

                    <div class="grid gap-0 md:grid-cols-2">
                        <!-- Instalador -->
                        <section
                            class="flex flex-col gap-3 border-b p-4 sm:p-5 md:border-r md:border-b-0"
                            aria-label="Evidencia del instalador"
                        >
                            <h3 class="text-sm font-medium">
                                Evidencia del instalador
                            </h3>
                            <MediaGrid
                                :items="item.evidence"
                                size="sm"
                                empty-text="Sin fotos ni videos."
                            />
                            <template v-if="item.installerCheck">
                                <p
                                    v-if="item.installerCheck.observations"
                                    class="rounded-lg bg-muted/60 p-2.5 text-sm whitespace-pre-line"
                                >
                                    {{ item.installerCheck.observations }}
                                </p>
                                <ul
                                    class="grid grid-cols-1 gap-1 text-sm sm:grid-cols-2"
                                >
                                    <li
                                        v-for="check in item.installerCheck
                                            .checks"
                                        :key="check.criterion"
                                        class="flex items-start justify-between gap-2"
                                    >
                                        <span
                                            :class="
                                                check.result.value === 'fail' &&
                                                'font-medium text-danger-ink'
                                            "
                                            >{{ check.label }}</span
                                        >
                                        <StatusBadge
                                            size="sm"
                                            :tone="check.result.tone"
                                            :label="check.result.label"
                                            :dot="false"
                                        />
                                        <span
                                            v-if="check.notes"
                                            class="col-span-full basis-full text-xs text-muted-foreground"
                                            >{{ check.notes }}</span
                                        >
                                    </li>
                                </ul>
                            </template>
                            <p v-else class="text-sm text-muted-foreground">
                                El instalador no registró autoevaluación.
                            </p>
                        </section>

                        <!-- Calidad -->
                        <section
                            class="flex flex-col gap-3 p-4 sm:p-5"
                            aria-label="Revisión de calidad"
                        >
                            <div
                                class="flex items-center justify-between gap-2"
                            >
                                <h3 class="text-sm font-medium">
                                    Revisión de calidad
                                </h3>
                                <Button
                                    v-if="canReview && !reviewFor(item).open"
                                    size="sm"
                                    variant="ghost"
                                    @click="reviewFor(item).open = true"
                                >
                                    Editar revisión
                                </Button>
                            </div>

                            <template v-if="canReview && reviewFor(item).open">
                                <div
                                    class="grid grid-cols-2 gap-2"
                                    role="radiogroup"
                                    aria-label="Dictamen de la partida"
                                >
                                    <button
                                        type="button"
                                        role="radio"
                                        :aria-checked="
                                            reviewFor(item).result ===
                                            'approved'
                                        "
                                        :class="
                                            cn(
                                                'flex h-11 items-center justify-center gap-2 rounded-lg border text-sm font-medium transition-colors',
                                                reviewFor(item).result ===
                                                    'approved'
                                                    ? 'border-success bg-success text-success-foreground'
                                                    : 'hover:bg-muted',
                                            )
                                        "
                                        @click="
                                            reviewFor(item).result = 'approved'
                                        "
                                    >
                                        <CircleCheck class="size-4" /> Aprobada
                                    </button>
                                    <button
                                        type="button"
                                        role="radio"
                                        :aria-checked="
                                            reviewFor(item).result ===
                                            'requires_fixes'
                                        "
                                        :class="
                                            cn(
                                                'flex h-11 items-center justify-center gap-2 rounded-lg border text-sm font-medium transition-colors',
                                                reviewFor(item).result ===
                                                    'requires_fixes'
                                                    ? 'border-danger bg-danger text-danger-foreground'
                                                    : 'hover:bg-muted',
                                            )
                                        "
                                        @click="
                                            reviewFor(item).result =
                                                'requires_fixes'
                                        "
                                    >
                                        <Wrench class="size-4" /> Requiere
                                        corrección
                                    </button>
                                </div>
                                <ChecklistEditor
                                    v-model="reviewFor(item).checklist"
                                    :criteria="criteria"
                                    :error="
                                        firstError(
                                            reviewFor(item).errors,
                                            'checklist',
                                        )
                                    "
                                />
                                <div class="grid gap-1.5">
                                    <Label :for="`obs-${item.id}`"
                                        >Observaciones{{
                                            reviewFor(item).result ===
                                            'requires_fixes'
                                                ? ''
                                                : ' (opcional)'
                                        }}</Label
                                    >
                                    <Textarea
                                        :id="`obs-${item.id}`"
                                        v-model="reviewFor(item).observations"
                                        rows="2"
                                        :placeholder="
                                            reviewFor(item).result ===
                                            'requires_fixes'
                                                ? 'Qué hay que corregir y cómo'
                                                : ''
                                        "
                                    />
                                    <InputError
                                        :message="
                                            reviewFor(item).errors.observations
                                        "
                                    />
                                </div>
                                <label class="flex items-center gap-3 text-sm">
                                    <Switch
                                        v-model="
                                            reviewFor(item).client_contacted
                                        "
                                    />
                                    Hablamos con el cliente sobre esta partida
                                </label>
                                <InputError
                                    :message="
                                        reviewFor(item).errors.status ??
                                        reviewFor(item).errors.result
                                    "
                                />
                                <Button
                                    :disabled="
                                        reviewFor(item).processing ||
                                        !checklistComplete(
                                            reviewFor(item).checklist,
                                        )
                                    "
                                    @click="submitReview(item)"
                                >
                                    <Spinner
                                        v-if="reviewFor(item).processing"
                                    />
                                    Guardar revisión de {{ item.code }}
                                </Button>
                            </template>

                            <template v-else-if="item.qualityCheck">
                                <p class="text-xs text-muted-foreground">
                                    {{ item.qualityCheck.inspector?.name }} ·
                                    <DateText
                                        :value="item.qualityCheck.inspectedAt"
                                        format="datetime"
                                    />
                                    <template
                                        v-if="item.qualityCheck.clientContacted"
                                    >
                                        · Cliente contactado</template
                                    >
                                </p>
                                <p
                                    v-if="item.qualityCheck.observations"
                                    class="text-sm whitespace-pre-line"
                                >
                                    {{ item.qualityCheck.observations }}
                                </p>
                                <ul class="flex flex-wrap gap-1.5">
                                    <li
                                        v-for="check in item.qualityCheck.checks.filter(
                                            (c) => c.result.value === 'fail',
                                        )"
                                        :key="check.criterion"
                                    >
                                        <StatusBadge
                                            size="sm"
                                            tone="danger"
                                            :label="check.label"
                                        />
                                    </li>
                                </ul>
                            </template>
                            <p v-else class="text-sm text-muted-foreground">
                                Pendiente de revisar.
                            </p>
                        </section>
                    </div>
                </article>
            </div>

            <aside
                class="flex flex-col gap-6 lg:sticky lg:top-20 lg:self-start"
            >
                <SectionCard title="Dictamen" :icon="ClipboardCheck">
                    <div class="flex flex-col gap-4 text-sm">
                        <div>
                            <div class="flex items-baseline justify-between">
                                <span class="text-muted-foreground"
                                    >Partidas aprobadas</span
                                >
                                <span class="font-semibold tabular-nums"
                                    >{{ approvedCount }} /
                                    {{ items.length }}</span
                                >
                            </div>
                            <div
                                class="mt-2 h-2 overflow-hidden rounded-full bg-muted"
                            >
                                <div
                                    class="h-full rounded-full bg-success transition-[width]"
                                    :style="{
                                        width: `${items.length ? (approvedCount / items.length) * 100 : 0}%`,
                                    }"
                                />
                            </div>
                        </div>
                        <template v-if="canReview">
                            <Button
                                :disabled="!allApproved"
                                @click="approveOpen = true"
                                ><CircleCheck class="size-4" /> Aprobar
                                instalación</Button
                            >
                            <p
                                v-if="!allApproved"
                                class="text-xs text-muted-foreground"
                            >
                                Aprueba cada partida para habilitar la
                                aprobación final.
                            </p>
                            <Button
                                variant="outline"
                                class="text-danger-ink"
                                @click="openFixes"
                                ><Wrench class="size-4" /> Requiere
                                correcciones</Button
                            >
                        </template>
                    </div>
                </SectionCard>

                <SectionCard
                    title="Respuesta del cliente"
                    :icon="MessageSquareQuote"
                >
                    <div class="flex flex-col gap-2 text-sm">
                        <StatusBadge
                            v-if="order.clientConformity"
                            :tone="order.clientConformity.tone"
                            :label="order.clientConformity.label"
                            class="self-start"
                        />
                        <p v-else class="text-muted-foreground">
                            El cliente no respondió; se envió a calidad desde
                            operación.
                        </p>
                        <blockquote
                            v-if="order.clientComment"
                            class="border-l-2 border-primary/40 pl-3 whitespace-pre-line"
                        >
                            {{ order.clientComment }}
                        </blockquote>
                        <MediaGrid
                            v-if="order.clientPhotos.length"
                            :items="order.clientPhotos"
                            size="sm"
                        />
                    </div>
                </SectionCard>

                <SectionCard title="Incidencias" :icon="ShieldAlert">
                    <p
                        v-if="incidents.length === 0"
                        class="text-sm text-muted-foreground"
                    >
                        Sin incidencias en esta orden.
                    </p>
                    <ul v-else class="-my-2 divide-y">
                        <li v-for="incident in incidents" :key="incident.id">
                            <Link
                                :href="`/admin/incidencias/${incident.id}`"
                                class="flex flex-col gap-1 py-2.5 hover:text-primary"
                            >
                                <span class="truncate text-sm font-medium">{{
                                    incident.title
                                }}</span>
                                <span
                                    class="flex flex-wrap items-center gap-1.5 text-xs text-muted-foreground"
                                >
                                    {{ incident.folio }}
                                    <StatusBadge
                                        size="sm"
                                        :tone="incident.status.tone"
                                        :label="incident.status.label"
                                    />
                                </span>
                            </Link>
                        </li>
                    </ul>
                </SectionCard>
            </aside>
        </div>
    </PageContainer>

    <ConfirmDialog
        v-model:open="approveOpen"
        title="Aprobar instalación"
        confirm-label="Aprobar instalación"
        loading-label="Aprobando…"
        :confirm="confirmApprove"
    >
        <div class="flex flex-col gap-3">
            <p>
                La orden quedará aprobada. Si es la última del proyecto y no hay
                incidencias abiertas, el proyecto se marcará como completado.
            </p>
            <div class="grid gap-1.5">
                <Label for="approve-notes">Observaciones (opcional)</Label>
                <Textarea id="approve-notes" v-model="approveNotes" rows="2" />
            </div>
            <label class="flex items-center gap-3 text-sm"
                ><Switch v-model="approveContacted" /> Hablamos con el
                cliente</label
            >
        </div>
    </ConfirmDialog>

    <FormSheet
        v-model:open="fixesOpen"
        title="Requiere correcciones"
        description="La orden vuelve al instalador y el proyecto pasa a pendientes por atender."
        size="xl"
    >
        <form
            id="fixes-form"
            class="flex flex-col gap-5"
            @submit.prevent="submitFixes"
        >
            <div class="grid gap-2">
                <Label for="fx-obs">Resumen para el instalador</Label>
                <Textarea
                    id="fx-obs"
                    v-model="fixesForm.observations"
                    rows="3"
                    placeholder="Qué encontramos y qué esperamos en la siguiente visita"
                />
                <InputError :message="fixesForm.errors.observations" />
            </div>
            <label class="flex items-center gap-3 text-sm"
                ><Switch v-model="fixesForm.client_contacted" /> Hablamos con el
                cliente</label
            >

            <section class="flex flex-col gap-3" aria-labelledby="fx-issues">
                <div class="flex items-center justify-between">
                    <h3 id="fx-issues" class="text-sm font-medium">
                        Pendientes (se registran como incidencias)
                    </h3>
                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        @click="fixesForm.issues.push(newIssue())"
                        ><Plus class="size-4" /> Agregar</Button
                    >
                </div>
                <InputError :message="fixesForm.errors.issues" />
                <fieldset
                    v-for="(issue, index) in fixesForm.issues"
                    :key="index"
                    class="flex flex-col gap-3 rounded-xl border p-3 sm:p-4"
                >
                    <legend class="sr-only">Pendiente {{ index + 1 }}</legend>
                    <div class="flex items-start gap-2">
                        <div class="grid flex-1 gap-1.5">
                            <Label :for="`is-title-${index}`">Título</Label>
                            <Input
                                :id="`is-title-${index}`"
                                v-model="issue.title"
                            />
                            <InputError :message="issueError(index, 'title')" />
                        </div>
                        <Button
                            v-if="fixesForm.issues.length > 1"
                            type="button"
                            size="icon"
                            variant="ghost"
                            class="mt-6"
                            :aria-label="`Quitar pendiente ${index + 1}`"
                            @click="fixesForm.issues.splice(index, 1)"
                        >
                            <Trash2 class="size-4 text-destructive" />
                        </Button>
                    </div>
                    <div class="grid gap-1.5">
                        <Label :for="`is-desc-${index}`">Descripción</Label>
                        <Textarea
                            :id="`is-desc-${index}`"
                            v-model="issue.description"
                            rows="2"
                        />
                        <InputError
                            :message="issueError(index, 'description')"
                        />
                    </div>
                    <div class="grid gap-3 sm:grid-cols-3">
                        <div class="grid gap-1.5">
                            <Label :for="`is-cat-${index}`">Categoría</Label>
                            <SearchSelect
                                :id="`is-cat-${index}`"
                                v-model="issue.category"
                                :options="categoryOptions"
                            />
                        </div>
                        <div class="grid gap-1.5">
                            <Label :for="`is-sev-${index}`">Severidad</Label>
                            <SearchSelect
                                :id="`is-sev-${index}`"
                                v-model="issue.severity"
                                :options="severityOptions"
                            />
                        </div>
                        <div class="grid gap-1.5">
                            <Label :for="`is-item-${index}`">Partida</Label>
                            <SearchSelect
                                :id="`is-item-${index}`"
                                v-model="issue.item_id"
                                :options="itemOptions"
                                clearable
                                placeholder="General"
                            />
                        </div>
                    </div>
                    <label class="flex items-center gap-3 text-sm"
                        ><Switch v-model="issue.visible_to_client" /> Visible
                        para el cliente en su portal</label
                    >
                </fieldset>
            </section>
            <InputError
                :message="
                    (fixesForm.errors as Record<string, string | undefined>)
                        .status
                "
            />
        </form>
        <template #footer>
            <Button variant="outline" type="button" @click="fixesOpen = false"
                >Cancelar</Button
            >
            <Button
                type="submit"
                form="fixes-form"
                variant="destructive"
                :disabled="
                    fixesForm.processing || !fixesForm.observations.trim()
                "
            >
                <Spinner v-if="fixesForm.processing" /> Enviar correcciones
            </Button>
        </template>
    </FormSheet>
</template>
