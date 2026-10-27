<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import {
    CloudOff,
    History,
    Lock,
    Plus,
    Ruler,
    Save,
    Send,
    Trash2,
    X,
} from '@lucide/vue';
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    ref,
    watch,
} from 'vue';
import ConfirmDialog from '@/components/ds/ConfirmDialog.vue';
import StatusBadge from '@/components/ds/StatusBadge.vue';
import MeasurementFiles from '@/components/projects/technician/MeasurementFiles.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { formatDate } from '@/lib/format';
import { cn } from '@/lib/utils';
import mediciones from '@/routes/tecnico/mediciones';
import type { AddressInfo, MeasurementData } from '@/types/projects';
import { formatMm } from '@/types/projects';

type Row = {
    key: string;
    area: string;
    kind: string;
    label: string;
    value_mm: number | null;
    notes: string;
    showNotes: boolean;
};

type FormShape = {
    space_type: string;
    access_notes: string;
    existing_installations: string;
    general_notes: string;
    items: Row[];
};

const props = defineProps<{
    measurement: MeasurementData & { editable: boolean };
    visit: {
        uuid: string;
        folio: string;
        customer_first_name: string | null;
        address: AddressInfo | null;
    } | null;
    kinds: { value: string; label: string }[];
    spaceTypes: string[];
}>();

defineOptions({
    layout: (p: {
        visit?: { uuid: string } | null;
        measurement?: { version: number };
    }) => ({
        title: `Medición v${p.measurement?.version ?? ''}`,
        backHref: p.visit
            ? `/tecnico/visitas/${p.visit.uuid}`
            : '/tecnico/visitas',
    }),
});

const QUICK_KINDS = [
    'width',
    'height',
    'depth',
    'outlet',
    'plumbing',
    'window',
    'door',
    'beam_column',
];
const kindLabel = (value: string) =>
    props.kinds.find((k) => k.value === value)?.label ?? value;
const quickKinds = computed(() =>
    props.kinds.filter((k) => QUICK_KINDS.includes(k.value)),
);

let seq = 0;
const newKey = () => `r${Date.now().toString(36)}${(seq++).toString(36)}`;

function serverState(): FormShape {
    return {
        space_type: props.measurement.space_type ?? '',
        access_notes: props.measurement.access_notes ?? '',
        existing_installations: props.measurement.existing_installations ?? '',
        general_notes: props.measurement.general_notes ?? '',
        items: props.measurement.items.map((i) => ({
            key: newKey(),
            area: i.area,
            kind: i.kind,
            label: i.label ?? '',
            value_mm: i.value_mm,
            notes: i.notes ?? '',
            showNotes: !!i.notes,
        })),
    };
}

const form = useForm<FormShape>(serverState());

// ------------------------------------------------------------ Áreas

const extraAreas = ref<string[]>([]);
const areas = computed(() => {
    const list: string[] = [];

    for (const item of form.items) {
        if (!list.includes(item.area)) {
            list.push(item.area);
        }
    }

    for (const area of extraAreas.value) {
        if (!list.includes(area)) {
            list.push(area);
        }
    }

    return list;
});
const rowsOf = (area: string) => form.items.filter((i) => i.area === area);

const newArea = ref('');
const newAreaError = ref<string | null>(null);

function addArea() {
    const name = newArea.value.trim();

    if (!name) {
        newAreaError.value =
            'Escribe un nombre, p. ej. «Muro A» o «Ventana norte».';

        return;
    }

    if (areas.value.some((a) => a.toLowerCase() === name.toLowerCase())) {
        newAreaError.value = 'Ya existe un área con ese nombre.';

        return;
    }

    newAreaError.value = null;
    extraAreas.value.push(name);
    newArea.value = '';
    addRow(name, 'width');
}

function suggestArea(): string {
    const letter = String.fromCharCode(65 + areas.value.length);

    return `Muro ${letter}`;
}

function renameArea(from: string, to: string) {
    const name = to.trim();

    if (!name || name === from) {
        return;
    }

    form.items.forEach((i) => {
        if (i.area === from) {
            i.area = name;
        }
    });
    extraAreas.value = extraAreas.value.map((a) => (a === from ? name : a));
}

function removeArea(area: string) {
    form.items = form.items.filter((i) => i.area !== area);
    extraAreas.value = extraAreas.value.filter((a) => a !== area);
}

// ------------------------------------------------------------ Renglones

const useCm = ref(false);
const rawValues = ref<Record<string, string>>({});

function rawFor(row: Row): string {
    if (row.key in rawValues.value) {
        return rawValues.value[row.key];
    }

    if (row.value_mm === null) {
        return '';
    }

    return useCm.value ? String(row.value_mm / 10) : String(row.value_mm);
}

function onValueInput(row: Row, raw: string) {
    const clean = raw.replace(',', '.').replace(/[^\d.]/g, '');
    rawValues.value = { ...rawValues.value, [row.key]: clean };

    if (clean === '' || clean === '.') {
        row.value_mm = null;

        return;
    }

    const n = Number(clean);
    row.value_mm = Number.isFinite(n)
        ? Math.round(useCm.value ? n * 10 : n)
        : null;
}

watch(useCm, () => {
    rawValues.value = {};
});

function helper(row: Row): string {
    if (row.value_mm === null) {
        return useCm.value
            ? 'En centímetros; se guarda en milímetros'
            : 'En milímetros';
    }

    return useCm.value
        ? `= ${row.value_mm.toLocaleString('es-MX')} mm`
        : `= ${(row.value_mm / 10).toLocaleString('es-MX', { maximumFractionDigits: 1 })} cm`;
}

async function addRow(area: string, kind: string) {
    const row: Row = {
        key: newKey(),
        area,
        kind,
        label: '',
        value_mm: null,
        notes: '',
        showNotes: false,
    };
    const lastIndex = form.items.map((i) => i.area).lastIndexOf(area);
    form.items.splice(
        lastIndex === -1 ? form.items.length : lastIndex + 1,
        0,
        row,
    );
    extraAreas.value = extraAreas.value.filter((a) => a !== area);
    await nextTick();
    document.getElementById(`valor-${row.key}`)?.focus();
}

function removeRow(row: Row) {
    if (rowsOf(row.area).length === 1) {
        extraAreas.value.push(row.area);
    }

    form.items = form.items.filter((i) => i.key !== row.key);
}

function errorFor(row: Row, field: string): string | undefined {
    const index = form.items.indexOf(row);
    const errors = form.errors as Record<string, string | undefined>;

    return errors[`items.${index}.${field}`];
}

const itemsError = computed(
    () =>
        (form.errors as Record<string, string | undefined>).items ??
        (form.errors as Record<string, string | undefined>).measurement,
);

// ------------------------------------------------------------ Borrador local

const STORAGE_KEY = `mlhabitat.medicion.${props.measurement.id}`;
type LocalDraft = { savedAt: number; data: FormShape };

const localDraft = ref<LocalDraft | null>(null);
const online = ref(true);

function payloadOf(state: FormShape) {
    return {
        space_type: state.space_type || null,
        access_notes: state.access_notes || null,
        existing_installations: state.existing_installations || null,
        general_notes: state.general_notes || null,
        items: state.items.map((i) => ({
            area: i.area,
            kind: i.kind,
            label: i.label || null,
            value_mm: i.value_mm,
            notes: i.notes || null,
        })),
    };
}

const signature = (state: FormShape) => JSON.stringify(payloadOf(state));

function readLocal(): LocalDraft | null {
    try {
        const raw = localStorage.getItem(STORAGE_KEY);

        return raw ? (JSON.parse(raw) as LocalDraft) : null;
    } catch {
        return null;
    }
}

function writeLocal() {
    try {
        localStorage.setItem(
            STORAGE_KEY,
            JSON.stringify({ savedAt: Date.now(), data: form.data() }),
        );
    } catch {
        // Almacenamiento lleno o bloqueado: el guardado en servidor sigue funcionando.
    }
}

function clearLocal() {
    try {
        localStorage.removeItem(STORAGE_KEY);
    } catch {
        // Sin acceso al almacenamiento local.
    }

    localDraft.value = null;
}

const autosave = useDebounceFn(() => {
    if (props.measurement.editable && form.isDirty) {
        writeLocal();
    }
}, 600);

watch(() => signature(form), autosave);

function recoverLocal() {
    if (!localDraft.value) {
        return;
    }

    const data = localDraft.value.data;
    form.space_type = data.space_type ?? '';
    form.access_notes = data.access_notes ?? '';
    form.existing_installations = data.existing_installations ?? '';
    form.general_notes = data.general_notes ?? '';
    form.items = (data.items ?? []).map((i) => ({ ...i, key: newKey() }));
    rawValues.value = {};
    localDraft.value = null;
}

const updateOnline = () => (online.value = navigator.onLine);

onMounted(() => {
    updateOnline();
    window.addEventListener('online', updateOnline);
    window.addEventListener('offline', updateOnline);

    if (!props.measurement.editable) {
        clearLocal();

        return;
    }

    const draft = readLocal();
    const serverTime = Date.parse(props.measurement.recorded_at ?? '') || 0;

    if (
        draft &&
        draft.savedAt > serverTime &&
        signature(draft.data) !== signature(serverState())
    ) {
        localDraft.value = draft;
    } else if (draft) {
        clearLocal();
    }
});

onBeforeUnmount(() => {
    window.removeEventListener('online', updateOnline);
    window.removeEventListener('offline', updateOnline);
});

// ------------------------------------------------------------ Guardar / enviar

function save(onDone?: () => void, onFail?: () => void) {
    if (!online.value) {
        writeLocal();
        onFail?.();

        return;
    }

    form.transform((data) => payloadOf(data as FormShape)).put(
        mediciones.update(props.measurement.id).url,
        {
            preserveScroll: true,
            onSuccess: () => {
                clearLocal();
                form.defaults();
                onDone?.();
            },
            onError: () => onFail?.(),
        },
    );
}

const confirmSubmit = ref(false);
const submitting = ref(false);

function submit(): Promise<void> {
    return new Promise((resolve, reject) => {
        const send = () => {
            submitting.value = true;
            router.post(
                mediciones.enviar(props.measurement.id).url,
                {},
                {
                    onSuccess: () => {
                        clearLocal();
                        resolve();
                    },
                    onError: (errors) =>
                        reject(
                            new Error(
                                Object.values(errors)[0] ??
                                    'No se pudo enviar la medición.',
                            ),
                        ),
                    onFinish: () => (submitting.value = false),
                },
            );
        };

        if (!online.value) {
            reject(
                new Error(
                    'Sin conexión. Envía la medición cuando vuelvas a tener señal.',
                ),
            );

            return;
        }

        if (form.isDirty) {
            save(send, () =>
                reject(
                    new Error('Revisa los campos marcados antes de enviar.'),
                ),
            );
        } else {
            send();
        }
    });
}

const revising = ref(false);

function revise() {
    router.post(
        mediciones.corregir(props.measurement.id).url,
        {},
        {
            onStart: () => (revising.value = true),
            onFinish: () => (revising.value = false),
        },
    );
}

const filledCount = computed(
    () => form.items.filter((i) => i.value_mm !== null).length,
);
</script>

<template>
    <Head :title="`Medición v${props.measurement.version}`" />

    <div
        class="mx-auto flex w-full max-w-3xl flex-col gap-5 px-4 py-5 pb-32 md:py-8"
    >
        <header class="flex flex-col gap-1">
            <div class="flex items-center gap-2">
                <StatusBadge
                    :label="props.measurement.status.label"
                    :tone="props.measurement.status.tone"
                />
                <span class="text-xs text-muted-foreground"
                    >Versión {{ props.measurement.version }}</span
                >
            </div>
            <h1 class="font-display text-2xl font-semibold tracking-tight">
                Medición{{
                    props.visit?.customer_first_name
                        ? ` · ${props.visit.customer_first_name}`
                        : ''
                }}
            </h1>
            <p
                v-if="props.visit?.address"
                class="text-sm text-muted-foreground"
            >
                {{ props.visit.address.one_line }}
            </p>
        </header>

        <div
            v-if="!online"
            role="status"
            class="flex items-start gap-2 rounded-lg border border-warning/30 bg-warning-soft p-3 text-sm text-warning-ink"
        >
            <CloudOff class="mt-0.5 size-4 shrink-0" aria-hidden="true" />
            Sin conexión — tus datos están guardados en este dispositivo.
        </div>

        <div
            v-if="localDraft"
            role="alert"
            class="flex flex-col gap-3 rounded-lg border border-info/30 bg-info-soft p-4 text-sm text-info-ink"
        >
            <p class="flex items-start gap-2">
                <History class="mt-0.5 size-4 shrink-0" aria-hidden="true" />
                Encontramos cambios sin guardar en este dispositivo ({{
                    formatDate(new Date(localDraft.savedAt), 'datetime')
                }}).
            </p>
            <div class="flex gap-2">
                <Button size="sm" class="h-10" @click="recoverLocal"
                    >Recuperar borrador local</Button
                >
                <Button
                    size="sm"
                    variant="ghost"
                    class="h-10"
                    @click="clearLocal"
                    >Descartar</Button
                >
            </div>
        </div>

        <!-- ===================== Solo lectura ===================== -->
        <template v-if="!props.measurement.editable">
            <div
                class="flex items-start gap-2 rounded-lg border bg-muted/40 p-3 text-sm text-muted-foreground"
            >
                <Lock class="mt-0.5 size-4 shrink-0" aria-hidden="true" />
                <span>
                    Esta versión ya se envió y no se puede editar.
                    <template
                        v-if="props.measurement.status.value === 'submitted'"
                        >Si hay que corregir algo, crea una nueva versión; esta
                        se conserva.</template
                    >
                </span>
            </div>

            <dl
                class="grid gap-3 rounded-xl border bg-card p-4 text-sm sm:grid-cols-2"
            >
                <div>
                    <dt class="font-medium">Espacio</dt>
                    <dd class="text-muted-foreground">
                        {{ props.measurement.space_type ?? '—' }}
                    </dd>
                </div>
                <div>
                    <dt class="font-medium">Enviada</dt>
                    <dd class="text-muted-foreground">
                        {{
                            formatDate(
                                props.measurement.submitted_at,
                                'datetime',
                            ) ?? '—'
                        }}
                    </dd>
                </div>
                <div
                    v-if="props.measurement.access_notes"
                    class="sm:col-span-2"
                >
                    <dt class="font-medium">Accesos</dt>
                    <dd class="whitespace-pre-line text-muted-foreground">
                        {{ props.measurement.access_notes }}
                    </dd>
                </div>
                <div
                    v-if="props.measurement.existing_installations"
                    class="sm:col-span-2"
                >
                    <dt class="font-medium">Instalaciones existentes</dt>
                    <dd class="whitespace-pre-line text-muted-foreground">
                        {{ props.measurement.existing_installations }}
                    </dd>
                </div>
                <div
                    v-if="props.measurement.general_notes"
                    class="sm:col-span-2"
                >
                    <dt class="font-medium">Notas</dt>
                    <dd class="whitespace-pre-line text-muted-foreground">
                        {{ props.measurement.general_notes }}
                    </dd>
                </div>
            </dl>

            <section
                v-for="area in areas"
                :key="area"
                class="rounded-xl border bg-card"
            >
                <h2 class="border-b px-4 py-3 font-semibold">{{ area }}</h2>
                <ul class="divide-y">
                    <li
                        v-for="row in rowsOf(area)"
                        :key="row.key"
                        class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1 px-4 py-3 text-sm"
                    >
                        <span>
                            <span class="font-medium">{{
                                kindLabel(row.kind)
                            }}</span>
                            <span
                                v-if="row.label"
                                class="text-muted-foreground"
                            >
                                · {{ row.label }}</span
                            >
                        </span>
                        <span class="tabular-nums">{{
                            formatMm(row.value_mm)
                        }}</span>
                        <p
                            v-if="row.notes"
                            class="w-full text-muted-foreground"
                        >
                            {{ row.notes }}
                        </p>
                    </li>
                </ul>
            </section>

            <section class="flex flex-col gap-3">
                <h2 class="text-sm font-semibold text-muted-foreground">
                    Fotos
                </h2>
                <MeasurementFiles
                    :measurement-id="props.measurement.id"
                    role="photo"
                    :files="props.measurement.photos"
                    :editable="false"
                />
                <h2 class="text-sm font-semibold text-muted-foreground">
                    Croquis
                </h2>
                <MeasurementFiles
                    :measurement-id="props.measurement.id"
                    role="sketch"
                    :files="props.measurement.sketches"
                    :editable="false"
                />
            </section>

            <Button
                v-if="props.measurement.status.value === 'submitted'"
                size="lg"
                variant="outline"
                class="h-14 text-base"
                :disabled="revising"
                @click="revise"
            >
                <Spinner v-if="revising" /> <History v-else class="size-5" />
                Corregir (nueva versión)
            </Button>
        </template>

        <!-- ===================== Captura ===================== -->
        <form
            v-else
            class="flex flex-col gap-5"
            novalidate
            @submit.prevent="save()"
        >
            <section
                class="flex flex-col gap-4 rounded-xl border bg-card p-4"
                aria-labelledby="med-espacio"
            >
                <h2 id="med-espacio" class="font-semibold">Espacio</h2>
                <div
                    class="flex flex-wrap gap-2"
                    role="radiogroup"
                    aria-label="Tipo de espacio"
                >
                    <button
                        v-for="type in props.spaceTypes"
                        :key="type"
                        type="button"
                        role="radio"
                        :aria-checked="form.space_type === type"
                        :class="
                            cn(
                                'h-11 rounded-full border px-4 text-sm font-medium transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                                form.space_type === type
                                    ? 'border-primary bg-primary text-primary-foreground'
                                    : 'bg-background hover:bg-accent',
                            )
                        "
                        @click="
                            form.space_type =
                                form.space_type === type ? '' : type
                        "
                    >
                        {{ type }}
                    </button>
                </div>
                <div class="grid gap-2">
                    <Label for="med-acceso">Accesos</Label>
                    <Textarea
                        id="med-acceso"
                        v-model="form.access_notes"
                        rows="2"
                        placeholder="Elevador, escaleras, horario de acceso…"
                    />
                    <p
                        v-if="form.errors.access_notes"
                        class="text-sm text-danger"
                    >
                        {{ form.errors.access_notes }}
                    </p>
                </div>
                <div class="grid gap-2">
                    <Label for="med-existentes">Instalaciones existentes</Label>
                    <Textarea
                        id="med-existentes"
                        v-model="form.existing_installations"
                        rows="2"
                        placeholder="Muebles a retirar, tomas, tubería expuesta…"
                    />
                </div>
            </section>

            <div
                class="flex items-center justify-between gap-3 rounded-xl border bg-card px-4 py-3"
            >
                <Label for="med-unidad" class="flex flex-col gap-0.5">
                    <span>Escribir en centímetros</span>
                    <span class="text-xs font-normal text-muted-foreground"
                        >Siempre se guarda en milímetros enteros.</span
                    >
                </Label>
                <Switch id="med-unidad" v-model="useCm" />
            </div>

            <section
                v-for="area in areas"
                :key="area"
                class="flex flex-col rounded-xl border bg-card"
                :aria-label="`Área ${area}`"
            >
                <div class="flex items-center gap-2 border-b px-3 py-2">
                    <Ruler
                        class="size-4 shrink-0 text-primary"
                        aria-hidden="true"
                    />
                    <Input
                        :model-value="area"
                        :aria-label="`Nombre del área ${area}`"
                        class="h-10 border-transparent bg-transparent px-1 text-base font-semibold shadow-none focus-visible:border-input"
                        @change="
                            (e: Event) =>
                                renameArea(
                                    area,
                                    (e.target as HTMLInputElement).value,
                                )
                        "
                    />
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        class="size-11 shrink-0"
                        :aria-label="`Quitar área ${area}`"
                        @click="removeArea(area)"
                    >
                        <Trash2 class="size-4" />
                    </Button>
                </div>

                <ul class="flex flex-col divide-y">
                    <li
                        v-for="row in rowsOf(area)"
                        :key="row.key"
                        class="flex flex-col gap-2 px-3 py-3"
                    >
                        <div class="flex items-start gap-2">
                            <Select v-model="row.kind">
                                <SelectTrigger
                                    class="h-12 w-40 shrink-0"
                                    :aria-label="'Tipo de medida'"
                                >
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="k in props.kinds"
                                        :key="k.value"
                                        :value="k.value"
                                        >{{ k.label }}</SelectItem
                                    >
                                </SelectContent>
                            </Select>
                            <div class="min-w-0 flex-1">
                                <div class="relative">
                                    <Input
                                        :id="`valor-${row.key}`"
                                        :model-value="rawFor(row)"
                                        :inputmode="
                                            useCm ? 'decimal' : 'numeric'
                                        "
                                        autocomplete="off"
                                        enterkeyhint="next"
                                        :aria-label="`${kindLabel(row.kind)} en ${useCm ? 'centímetros' : 'milímetros'}`"
                                        :aria-invalid="
                                            !!errorFor(row, 'value_mm')
                                        "
                                        :aria-describedby="`ayuda-${row.key}`"
                                        class="h-12 pr-12 text-lg tabular-nums"
                                        placeholder="0"
                                        @update:model-value="
                                            (v: string | number) =>
                                                onValueInput(row, String(v))
                                        "
                                    />
                                    <span
                                        class="pointer-events-none absolute top-1/2 right-3 -translate-y-1/2 text-sm text-muted-foreground"
                                    >
                                        {{ useCm ? 'cm' : 'mm' }}
                                    </span>
                                </div>
                                <p
                                    :id="`ayuda-${row.key}`"
                                    class="mt-1 text-xs text-muted-foreground tabular-nums"
                                >
                                    {{ helper(row) }}
                                </p>
                            </div>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                class="size-12 shrink-0"
                                :aria-label="`Quitar ${kindLabel(row.kind)}`"
                                @click="removeRow(row)"
                            >
                                <X class="size-5" />
                            </Button>
                        </div>
                        <p
                            v-if="errorFor(row, 'value_mm')"
                            class="text-sm text-danger"
                        >
                            {{ errorFor(row, 'value_mm') }}
                        </p>
                        <p
                            v-if="errorFor(row, 'kind')"
                            class="text-sm text-danger"
                        >
                            {{ errorFor(row, 'kind') }}
                        </p>
                        <p
                            v-if="errorFor(row, 'area')"
                            class="text-sm text-danger"
                        >
                            {{ errorFor(row, 'area') }}
                        </p>
                        <div class="flex items-center gap-2">
                            <Input
                                v-model="row.label"
                                class="h-11"
                                :aria-label="`Descripción de ${kindLabel(row.kind)}`"
                                placeholder="Descripción (opcional): p. ej. a 1100 mm del piso"
                            />
                            <Button
                                type="button"
                                variant="ghost"
                                class="h-11 shrink-0"
                                :aria-expanded="row.showNotes"
                                @click="row.showNotes = !row.showNotes"
                            >
                                Nota
                            </Button>
                        </div>
                        <Textarea
                            v-if="row.showNotes"
                            v-model="row.notes"
                            rows="2"
                            :aria-label="`Nota de ${kindLabel(row.kind)}`"
                            placeholder="Observaciones de esta medida"
                        />
                    </li>
                </ul>

                <div
                    class="flex flex-wrap gap-2 border-t px-3 py-3"
                    :aria-label="`Agregar medida en ${area}`"
                >
                    <Button
                        v-for="k in quickKinds"
                        :key="k.value"
                        type="button"
                        variant="outline"
                        class="h-11 rounded-full"
                        @click="addRow(area, k.value)"
                    >
                        <Plus class="size-4" /> {{ k.label }}
                    </Button>
                    <Button
                        type="button"
                        variant="ghost"
                        class="h-11 rounded-full"
                        @click="addRow(area, 'other')"
                    >
                        <Plus class="size-4" /> Otra
                    </Button>
                </div>
            </section>

            <section
                class="flex flex-col gap-2 rounded-xl border border-dashed bg-card/60 p-4"
                aria-labelledby="med-nueva-area"
            >
                <Label id="med-nueva-area" for="med-area">{{
                    areas.length ? 'Agregar otra área' : 'Empieza por un área'
                }}</Label>
                <div class="flex gap-2">
                    <Input
                        id="med-area"
                        v-model="newArea"
                        class="h-12"
                        :placeholder="suggestArea()"
                        enterkeyhint="done"
                        @keydown.enter.prevent="addArea"
                    />
                    <Button type="button" class="h-12 shrink-0" @click="addArea"
                        ><Plus class="size-4" /> Área</Button
                    >
                </div>
                <div class="flex flex-wrap gap-2">
                    <button
                        v-for="s in [
                            'Muro A',
                            'Muro B',
                            'Muro C',
                            'Ventana',
                            'Puerta de acceso',
                        ]"
                        v-show="!areas.includes(s)"
                        :key="s"
                        type="button"
                        class="h-10 rounded-full border bg-background px-3 text-sm hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                        @click="
                            newArea = s;
                            addArea();
                        "
                    >
                        {{ s }}
                    </button>
                </div>
                <p v-if="newAreaError" class="text-sm text-danger">
                    {{ newAreaError }}
                </p>
            </section>

            <p
                v-if="itemsError"
                class="rounded-lg border border-danger/30 bg-danger-soft p-3 text-sm text-danger-ink"
                role="alert"
            >
                {{ itemsError }}
            </p>

            <section class="flex flex-col gap-2 rounded-xl border bg-card p-4">
                <Label for="med-notas">Notas generales</Label>
                <Textarea
                    id="med-notas"
                    v-model="form.general_notes"
                    rows="3"
                    placeholder="Desniveles, muros fuera de escuadra, pendientes por confirmar…"
                />
            </section>

            <section
                class="flex flex-col gap-3 rounded-xl border bg-card p-4"
                aria-labelledby="med-fotos"
            >
                <h2 id="med-fotos" class="font-semibold">Fotos del espacio</h2>
                <MeasurementFiles
                    :measurement-id="props.measurement.id"
                    role="photo"
                    :files="props.measurement.photos"
                    editable
                />
            </section>
            <section
                class="flex flex-col gap-3 rounded-xl border bg-card p-4"
                aria-labelledby="med-croquis"
            >
                <h2 id="med-croquis" class="font-semibold">Croquis</h2>
                <MeasurementFiles
                    :measurement-id="props.measurement.id"
                    role="sketch"
                    :files="props.measurement.sketches"
                    editable
                />
            </section>

            <div
                class="fixed inset-x-0 bottom-[calc(4rem+env(safe-area-inset-bottom))] z-20 border-t bg-background/95 px-4 py-3 backdrop-blur md:bottom-0"
            >
                <div class="mx-auto flex max-w-3xl items-center gap-2">
                    <p
                        class="hidden flex-1 text-sm text-muted-foreground sm:block"
                        aria-live="polite"
                    >
                        {{ filledCount }}
                        {{
                            filledCount === 1
                                ? 'medida capturada'
                                : 'medidas capturadas'
                        }}
                        <template v-if="form.isDirty">
                            · cambios sin guardar</template
                        >
                        <template v-else-if="form.recentlySuccessful">
                            · guardado</template
                        >
                    </p>
                    <Button
                        type="submit"
                        variant="outline"
                        class="h-12 flex-1 sm:flex-none"
                        :disabled="form.processing"
                    >
                        <Spinner v-if="form.processing" />
                        <Save v-else class="size-4" />
                        Guardar
                    </Button>
                    <Button
                        type="button"
                        class="h-12 flex-1 sm:flex-none"
                        :disabled="
                            form.processing || submitting || filledCount === 0
                        "
                        @click="confirmSubmit = true"
                    >
                        <Send class="size-4" /> Enviar medición
                    </Button>
                </div>
            </div>
        </form>
    </div>

    <ConfirmDialog
        v-model:open="confirmSubmit"
        title="¿Enviar la medición?"
        description="Al enviarla, el equipo de diseño la usará y esta versión ya no se podrá editar. Si después hay que corregir algo, se crea una nueva versión."
        confirm-label="Enviar medición"
        loading-label="Enviando…"
        :confirm="submit"
    />
</template>
