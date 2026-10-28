<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    Factory,
    Mail,
    Pencil,
    Phone,
    Plus,
    Star,
    Trash2,
    UserRound,
    Wrench,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import ConfirmDialog from '@/components/ds/ConfirmDialog.vue';
import DateText from '@/components/ds/DateText.vue';
import EmptyState from '@/components/ds/EmptyState.vue';
import FileDropzone from '@/components/ds/FileDropzone.vue';
import FormSheet from '@/components/ds/FormSheet.vue';
import KeyValue from '@/components/ds/KeyValue.vue';
import Money from '@/components/ds/Money.vue';
import PageContainer from '@/components/ds/PageContainer.vue';
import PageHeader from '@/components/ds/PageHeader.vue';
import SectionCard from '@/components/ds/SectionCard.vue';
import StatusBadge from '@/components/ds/StatusBadge.vue';
import InputError from '@/components/InputError.vue';
import MediaGrid from '@/components/operations/MediaGrid.vue';
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
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { usePermissions } from '@/lib/permissions';
import { cn } from '@/lib/utils';
import proveedores from '@/routes/admin/proveedores';
import contactos from '@/routes/admin/proveedores/contactos';
import documentos from '@/routes/admin/proveedores/documentos';
import evaluaciones from '@/routes/admin/proveedores/evaluaciones';
import type { BreadcrumbItem } from '@/types';
import type {
    EnumOption,
    MediaItem,
    SelectOption,
    SupplierRef,
} from '@/types/operations';

type Contact = {
    id: number;
    name: string;
    position: string | null;
    email: string | null;
    phone: string | null;
    is_primary: boolean;
};
type Evaluation = {
    id: number;
    quality: number;
    timeliness: number;
    communication: number;
    comments: string | null;
    evaluator: string | null;
    project: string | null;
    createdAt: string | null;
};
type HistoryRow = {
    id: number;
    kind: 'fabrication' | 'installation';
    folio: string;
    status: EnumOption;
    project: string;
    amount: string | null;
    date: string | null;
};

const props = defineProps<{
    supplier: SupplierRef & {
        legalName: string | null;
        rfc: string | null;
        capabilities: string | null;
        notes: string | null;
        zones: { id: number; name: string }[];
        contacts: Contact[];
        evaluations: Evaluation[];
        technicians: {
            id: number;
            name: string;
            email: string;
            status: EnumOption;
            active: boolean;
        }[];
        documents: MediaItem[];
    };
    history: HistoryRow[];
    options: {
        types: EnumOption[];
        statuses: EnumOption[];
        zones: SelectOption[];
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Panel', href: '/admin' },
            { title: 'Proveedores', href: '/admin/proveedores' },
            { title: 'Detalle', href: '#' },
        ] as BreadcrumbItem[],
    },
});

const { can } = usePermissions();
const canManage = computed(() => can('suppliers.manage'));
const id = computed(() => props.supplier.id);

const details = computed(() => [
    { key: 'legal', label: 'Razón social', value: props.supplier.legalName },
    { key: 'rfc', label: 'RFC', value: props.supplier.rfc },
    { key: 'email', label: 'Correo', value: props.supplier.email },
    { key: 'phone', label: 'Teléfono', value: props.supplier.phone },
    {
        key: 'lead',
        label: 'Tiempo de entrega',
        value:
            props.supplier.leadTimeDays !== null
                ? `${props.supplier.leadTimeDays} días`
                : null,
    },
    {
        key: 'zones',
        label: 'Zonas',
        value: props.supplier.zones.map((z) => z.name).join(', ') || null,
        full: true,
    },
    {
        key: 'cap',
        label: 'Capacidades',
        value: props.supplier.capabilities,
        full: true,
    },
    {
        key: 'notes',
        label: 'Notas internas',
        value: props.supplier.notes,
        full: true,
    },
]);

// ------------------------------------------------------------ Editar proveedor
const editOpen = ref(false);
const editForm = useForm({
    name: props.supplier.name,
    legal_name: props.supplier.legalName ?? '',
    rfc: props.supplier.rfc ?? '',
    type: props.supplier.type.value,
    status: props.supplier.status.value,
    email: props.supplier.email ?? '',
    phone: props.supplier.phone ?? '',
    capabilities: props.supplier.capabilities ?? '',
    lead_time_days: (props.supplier.leadTimeDays ?? '') as string | number,
    notes: props.supplier.notes ?? '',
    zone_ids: props.supplier.zones.map((z) => z.id),
});

function toggleZone(zoneId: number) {
    editForm.zone_ids = editForm.zone_ids.includes(zoneId)
        ? editForm.zone_ids.filter((z) => z !== zoneId)
        : [...editForm.zone_ids, zoneId];
}

function saveSupplier() {
    editForm
        .transform((data) => ({
            ...data,
            lead_time_days:
                data.lead_time_days === '' ? null : Number(data.lead_time_days),
        }))
        .put(proveedores.update(id.value).url, {
            preserveScroll: true,
            onSuccess: () => (editOpen.value = false),
        });
}

const deleteOpen = ref(false);
function deleteSupplier() {
    return new Promise<void>((resolve, reject) =>
        router.delete(proveedores.destroy(id.value).url, {
            onSuccess: () => resolve(),
            onError: () => reject(new Error('No se pudo eliminar.')),
        }),
    );
}

// ------------------------------------------------------------ Contactos
const contactOpen = ref(false);
const editingContact = ref<Contact | null>(null);
const contactForm = useForm({
    name: '',
    position: '',
    email: '',
    phone: '',
    is_primary: false,
});

function openContact(contact: Contact | null) {
    editingContact.value = contact;
    contactForm.clearErrors();
    contactForm.defaults({
        name: contact?.name ?? '',
        position: contact?.position ?? '',
        email: contact?.email ?? '',
        phone: contact?.phone ?? '',
        is_primary: contact?.is_primary ?? props.supplier.contacts.length === 0,
    });
    contactForm.reset();
    contactOpen.value = true;
}

function saveContact() {
    const options = {
        preserveScroll: true,
        onSuccess: () => (contactOpen.value = false),
    };

    if (editingContact.value) {
        contactForm.put(
            contactos.update([id.value, editingContact.value.id]).url,
            options,
        );
    } else {
        contactForm.post(contactos.store(id.value).url, options);
    }
}

const contactToDelete = ref<Contact | null>(null);
const contactDeleteOpen = ref(false);
function askDeleteContact(contact: Contact) {
    contactToDelete.value = contact;
    contactDeleteOpen.value = true;
}
function deleteContact() {
    const contact = contactToDelete.value;

    if (!contact) {
        return;
    }

    return new Promise<void>((resolve, reject) =>
        router.delete(contactos.destroy([id.value, contact.id]).url, {
            preserveScroll: true,
            onSuccess: () => resolve(),
            onError: () =>
                reject(new Error('No se pudo eliminar el contacto.')),
        }),
    );
}

// ------------------------------------------------------------ Evaluaciones
const evalOpen = ref(false);
const evalForm = useForm({
    quality_score: 0,
    timeliness_score: 0,
    communication_score: 0,
    comments: '',
});
const SCORES = [
    { key: 'quality_score', label: 'Calidad del trabajo' },
    { key: 'timeliness_score', label: 'Puntualidad' },
    { key: 'communication_score', label: 'Comunicación' },
] as const;
const evalReady = computed(
    () =>
        evalForm.quality_score > 0 &&
        evalForm.timeliness_score > 0 &&
        evalForm.communication_score > 0,
);

function saveEvaluation() {
    evalForm.post(evaluaciones.store(id.value).url, {
        preserveScroll: true,
        onSuccess: () => {
            evalForm.reset();
            evalOpen.value = false;
        },
    });
}

function average(e: Evaluation): string {
    return ((e.quality + e.timeliness + e.communication) / 3).toFixed(1);
}

// ------------------------------------------------------------ Documentos
const dropzoneKey = ref(0);
function attachDocuments(media: { id: number }[]) {
    if (!media.length) {
        return;
    }

    router.post(
        documentos.store(id.value).url,
        { media_ids: media.map((m) => m.id) },
        { preserveScroll: true, onSuccess: () => dropzoneKey.value++ },
    );
}

const docToDelete = ref<MediaItem | null>(null);
const docDeleteOpen = ref(false);
function askDeleteDoc(media: MediaItem) {
    docToDelete.value = media;
    docDeleteOpen.value = true;
}
function deleteDoc() {
    const media = docToDelete.value;

    if (!media) {
        return;
    }

    return new Promise<void>((resolve, reject) =>
        router.delete(documentos.destroy([id.value, media.id]).url, {
            preserveScroll: true,
            onSuccess: () => resolve(),
            onError: () => reject(new Error('No se pudo quitar el documento.')),
        }),
    );
}

function historyHref(row: HistoryRow): string {
    return row.kind === 'fabrication'
        ? `/admin/fabricacion/${row.id}`
        : `/admin/instalaciones/${row.id}`;
}
</script>

<template>
    <Head :title="supplier.name" />

    <PageContainer>
        <PageHeader :title="supplier.name">
            <template #meta>
                <StatusBadge
                    :label="supplier.status.label"
                    :tone="supplier.status.tone"
                />
                <StatusBadge
                    :label="supplier.type.label"
                    :tone="supplier.type.tone"
                    :dot="false"
                />
                <span
                    v-if="supplier.rating"
                    class="inline-flex items-center gap-1 font-medium text-foreground tabular-nums"
                >
                    <Star
                        class="size-4 fill-warning text-warning"
                        aria-hidden="true"
                    />{{ supplier.rating }}
                    <span class="font-normal text-muted-foreground"
                        >({{ supplier.evaluations.length }} evaluaciones)</span
                    >
                </span>
            </template>
            <template v-if="canManage" #actions>
                <Button variant="outline" @click="editOpen = true"
                    ><Pencil class="size-4" /> Editar</Button
                >
                <Button
                    variant="ghost"
                    class="text-destructive"
                    @click="deleteOpen = true"
                    ><Trash2 class="size-4" /> Eliminar</Button
                >
            </template>
        </PageHeader>

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="flex flex-col gap-6 lg:col-span-2">
                <SectionCard title="Datos generales">
                    <KeyValue :items="details" :columns="2" />
                </SectionCard>

                <SectionCard
                    title="Historial de órdenes"
                    description="Últimas órdenes de fabricación e instalación asignadas."
                    flush
                >
                    <EmptyState
                        v-if="!history.length"
                        compact
                        :bordered="false"
                        :icon="Factory"
                        title="Sin órdenes todavía"
                        description="Cuando asignes fabricación o instalación a este proveedor aparecerán aquí."
                    />
                    <ul v-else class="divide-y">
                        <li
                            v-for="row in history"
                            :key="`${row.kind}-${row.id}`"
                        >
                            <Link
                                :href="historyHref(row)"
                                class="flex items-center gap-3 px-4 py-3 transition-colors hover:bg-muted/50"
                            >
                                <span
                                    class="grid size-9 shrink-0 place-items-center rounded-lg bg-muted text-muted-foreground"
                                >
                                    <Factory
                                        v-if="row.kind === 'fabrication'"
                                        class="size-4"
                                        aria-hidden="true"
                                    />
                                    <Wrench
                                        v-else
                                        class="size-4"
                                        aria-hidden="true"
                                    />
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span class="block text-sm font-medium">{{
                                        row.folio
                                    }}</span>
                                    <span
                                        class="block truncate text-xs text-muted-foreground"
                                        >{{ row.project }}</span
                                    >
                                </span>
                                <span class="hidden text-right sm:block">
                                    <Money
                                        :value="row.amount"
                                        class="block text-sm"
                                    />
                                    <DateText
                                        :value="row.date"
                                        class="text-xs text-muted-foreground"
                                    />
                                </span>
                                <StatusBadge
                                    :label="row.status.label"
                                    :tone="row.status.tone"
                                    size="sm"
                                />
                            </Link>
                        </li>
                    </ul>
                </SectionCard>

                <SectionCard
                    title="Evaluaciones"
                    description="Calidad, puntualidad y comunicación (1 a 5)."
                >
                    <template v-if="canManage" #actions>
                        <Button
                            size="sm"
                            variant="outline"
                            @click="evalOpen = true"
                            ><Plus class="size-4" /> Evaluar</Button
                        >
                    </template>
                    <p
                        v-if="!supplier.evaluations.length"
                        class="text-sm text-muted-foreground"
                    >
                        Aún no hay evaluaciones. Evalúa al proveedor al cerrar
                        cada trabajo para tener una calificación confiable.
                    </p>
                    <ul v-else class="flex flex-col divide-y">
                        <li
                            v-for="e in supplier.evaluations"
                            :key="e.id"
                            class="flex flex-col gap-1 py-3 first:pt-0 last:pb-0"
                        >
                            <div
                                class="flex flex-wrap items-center gap-x-4 gap-y-1 text-sm"
                            >
                                <span
                                    class="inline-flex items-center gap-1 font-semibold tabular-nums"
                                >
                                    <Star
                                        class="size-4 fill-warning text-warning"
                                        aria-hidden="true"
                                    />{{ average(e) }}
                                </span>
                                <span class="text-muted-foreground"
                                    >Calidad {{ e.quality }} · Puntualidad
                                    {{ e.timeliness }} · Comunicación
                                    {{ e.communication }}</span
                                >
                            </div>
                            <p v-if="e.comments" class="text-sm">
                                {{ e.comments }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                {{ e.evaluator ?? 'Equipo'
                                }}<template v-if="e.project">
                                    · {{ e.project }}</template
                                >
                                · <DateText :value="e.createdAt" />
                            </p>
                        </li>
                    </ul>
                </SectionCard>
            </div>

            <div class="flex flex-col gap-6">
                <SectionCard title="Contactos">
                    <template v-if="canManage" #actions>
                        <Button
                            size="sm"
                            variant="outline"
                            @click="openContact(null)"
                            ><Plus class="size-4" /> Agregar</Button
                        >
                    </template>
                    <p
                        v-if="!supplier.contacts.length"
                        class="text-sm text-muted-foreground"
                    >
                        Sin contactos. Agrega a quien coordina pedidos y
                        entregas.
                    </p>
                    <ul v-else class="flex flex-col gap-3">
                        <li
                            v-for="c in supplier.contacts"
                            :key="c.id"
                            class="rounded-lg border p-3"
                        >
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <p
                                        class="flex items-center gap-2 text-sm font-medium"
                                    >
                                        {{ c.name }}
                                        <StatusBadge
                                            v-if="c.is_primary"
                                            label="Principal"
                                            tone="brand"
                                            size="sm"
                                            :dot="false"
                                        />
                                    </p>
                                    <p
                                        v-if="c.position"
                                        class="text-xs text-muted-foreground"
                                    >
                                        {{ c.position }}
                                    </p>
                                </div>
                                <div
                                    v-if="canManage"
                                    class="flex shrink-0 gap-1"
                                >
                                    <Button
                                        size="icon"
                                        variant="ghost"
                                        :aria-label="`Editar ${c.name}`"
                                        @click="openContact(c)"
                                        ><Pencil class="size-4"
                                    /></Button>
                                    <Button
                                        size="icon"
                                        variant="ghost"
                                        class="text-destructive"
                                        :aria-label="`Eliminar ${c.name}`"
                                        @click="askDeleteContact(c)"
                                    >
                                        <Trash2 class="size-4" />
                                    </Button>
                                </div>
                            </div>
                            <div class="mt-2 flex flex-col gap-1 text-sm">
                                <a
                                    v-if="c.phone"
                                    :href="`tel:${c.phone}`"
                                    class="inline-flex items-center gap-2 text-primary hover:underline"
                                    ><Phone class="size-3.5" />{{ c.phone }}</a
                                >
                                <a
                                    v-if="c.email"
                                    :href="`mailto:${c.email}`"
                                    class="inline-flex items-center gap-2 break-all text-primary hover:underline"
                                    ><Mail class="size-3.5" />{{ c.email }}</a
                                >
                            </div>
                        </li>
                    </ul>
                </SectionCard>

                <SectionCard
                    title="Documentos"
                    description="Contratos, constancias y actas. Se guardan de forma privada."
                >
                    <MediaGrid
                        :items="supplier.documents"
                        size="sm"
                        :removable="canManage"
                        empty-text="Sin documentos."
                        @remove="askDeleteDoc"
                    />
                    <FileDropzone
                        v-if="canManage"
                        :key="dropzoneKey"
                        class="mt-4"
                        compact
                        visibility="private"
                        collection="privado/proveedores"
                        :accept="[
                            'application/pdf',
                            'image/*',
                            '.doc',
                            '.docx',
                            '.xls',
                            '.xlsx',
                        ]"
                        :max-size="25 * 1024 * 1024"
                        label="Arrastra documentos o elige desde tu equipo"
                        @all-completed="attachDocuments"
                    />
                </SectionCard>

                <SectionCard title="Técnicos del proveedor">
                    <p
                        v-if="!supplier.technicians.length"
                        class="text-sm text-muted-foreground"
                    >
                        Ningún técnico vinculado a este proveedor.
                    </p>
                    <ul v-else class="flex flex-col gap-2">
                        <li v-for="t in supplier.technicians" :key="t.id">
                            <Link
                                :href="`/admin/tecnicos/${t.id}`"
                                class="flex items-center gap-3 rounded-lg p-2 hover:bg-muted/50"
                            >
                                <UserRound
                                    class="size-4 text-muted-foreground"
                                    aria-hidden="true"
                                />
                                <span
                                    class="min-w-0 flex-1 truncate text-sm font-medium"
                                    >{{ t.name }}</span
                                >
                                <StatusBadge
                                    v-if="!t.active"
                                    label="Sin acceso"
                                    tone="neutral"
                                    size="sm"
                                />
                                <StatusBadge
                                    v-else
                                    :label="t.status.label"
                                    :tone="t.status.tone"
                                    size="sm"
                                />
                            </Link>
                        </li>
                    </ul>
                </SectionCard>
            </div>
        </div>
    </PageContainer>

    <!-- Editar proveedor -->
    <FormSheet v-model:open="editOpen" title="Editar proveedor" size="lg">
        <form
            id="supplier-edit"
            class="grid gap-4"
            @submit.prevent="saveSupplier"
        >
            <div class="grid gap-1.5">
                <Label for="e-name">Nombre comercial</Label>
                <Input id="e-name" v-model="editForm.name" required />
                <InputError :message="editForm.errors.name" />
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-1.5">
                    <Label for="e-legal">Razón social</Label>
                    <Input id="e-legal" v-model="editForm.legal_name" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="e-rfc">RFC</Label>
                    <Input
                        id="e-rfc"
                        v-model="editForm.rfc"
                        class="uppercase"
                        maxlength="13"
                    />
                    <InputError :message="editForm.errors.rfc" />
                </div>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-1.5">
                    <Label>Tipo</Label>
                    <Select v-model="editForm.type">
                        <SelectTrigger class="w-full"
                            ><SelectValue
                        /></SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="t in options.types"
                                :key="t.value"
                                :value="t.value"
                                >{{ t.label }}</SelectItem
                            >
                        </SelectContent>
                    </Select>
                </div>
                <div class="grid gap-1.5">
                    <Label>Estado</Label>
                    <Select v-model="editForm.status">
                        <SelectTrigger class="w-full"
                            ><SelectValue
                        /></SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="s in options.statuses"
                                :key="s.value"
                                :value="s.value"
                                >{{ s.label }}</SelectItem
                            >
                        </SelectContent>
                    </Select>
                </div>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-1.5">
                    <Label for="e-email">Correo</Label>
                    <Input id="e-email" v-model="editForm.email" type="email" />
                    <InputError :message="editForm.errors.email" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="e-phone">Teléfono</Label>
                    <Input id="e-phone" v-model="editForm.phone" type="tel" />
                </div>
            </div>
            <div class="grid gap-1.5">
                <Label for="e-lead">Tiempo de entrega típico (días)</Label>
                <Input
                    id="e-lead"
                    v-model="editForm.lead_time_days"
                    type="number"
                    min="0"
                    max="365"
                    class="sm:w-40"
                />
                <InputError :message="editForm.errors.lead_time_days" />
            </div>
            <div class="grid gap-1.5">
                <Label for="e-cap">Capacidades</Label>
                <Textarea id="e-cap" v-model="editForm.capabilities" rows="3" />
            </div>
            <fieldset class="grid gap-2">
                <legend class="mb-1 text-sm font-medium">
                    Zonas que atiende
                </legend>
                <div class="flex flex-wrap gap-2">
                    <button
                        v-for="zone in options.zones"
                        :key="zone.value"
                        type="button"
                        :aria-pressed="editForm.zone_ids.includes(zone.value)"
                        :class="
                            cn(
                                'h-9 rounded-full border px-3 text-sm transition-colors pointer-coarse:h-11',
                                editForm.zone_ids.includes(zone.value)
                                    ? 'border-primary bg-primary-soft text-primary'
                                    : 'hover:bg-muted',
                            )
                        "
                        @click="toggleZone(zone.value)"
                    >
                        {{ zone.label }}
                    </button>
                </div>
            </fieldset>
            <div class="grid gap-1.5">
                <Label for="e-notes">Notas internas</Label>
                <Textarea id="e-notes" v-model="editForm.notes" rows="2" />
            </div>
        </form>
        <template #footer>
            <Button variant="outline" @click="editOpen = false"
                >Cancelar</Button
            >
            <Button
                type="submit"
                form="supplier-edit"
                :disabled="editForm.processing"
                >{{
                    editForm.processing ? 'Guardando…' : 'Guardar cambios'
                }}</Button
            >
        </template>
    </FormSheet>

    <!-- Contacto -->
    <FormSheet
        v-model:open="contactOpen"
        :title="editingContact ? 'Editar contacto' : 'Nuevo contacto'"
    >
        <form
            id="contact-form"
            class="grid gap-4"
            @submit.prevent="saveContact"
        >
            <div class="grid gap-1.5">
                <Label for="c-name">Nombre</Label>
                <Input id="c-name" v-model="contactForm.name" required />
                <InputError :message="contactForm.errors.name" />
            </div>
            <div class="grid gap-1.5">
                <Label for="c-pos">Puesto</Label>
                <Input
                    id="c-pos"
                    v-model="contactForm.position"
                    placeholder="Coordinación de producción"
                />
            </div>
            <div class="grid gap-1.5">
                <Label for="c-email">Correo</Label>
                <Input id="c-email" v-model="contactForm.email" type="email" />
                <InputError :message="contactForm.errors.email" />
            </div>
            <div class="grid gap-1.5">
                <Label for="c-phone">Teléfono</Label>
                <Input id="c-phone" v-model="contactForm.phone" type="tel" />
            </div>
            <label
                class="flex items-center justify-between gap-3 rounded-lg border p-3"
            >
                <span class="text-sm">
                    <span class="block font-medium">Contacto principal</span>
                    <span class="text-muted-foreground"
                        >A quien llamamos primero.</span
                    >
                </span>
                <Switch v-model="contactForm.is_primary" />
            </label>
        </form>
        <template #footer>
            <Button variant="outline" @click="contactOpen = false"
                >Cancelar</Button
            >
            <Button
                type="submit"
                form="contact-form"
                :disabled="contactForm.processing"
                >{{
                    contactForm.processing ? 'Guardando…' : 'Guardar contacto'
                }}</Button
            >
        </template>
    </FormSheet>

    <!-- Evaluación -->
    <FormSheet
        v-model:open="evalOpen"
        title="Evaluar proveedor"
        description="La calificación del proveedor es el promedio de todas sus evaluaciones."
    >
        <form
            id="eval-form"
            class="grid gap-5"
            @submit.prevent="saveEvaluation"
        >
            <fieldset
                v-for="score in SCORES"
                :key="score.key"
                class="grid gap-2"
            >
                <legend class="text-sm font-medium">{{ score.label }}</legend>
                <div
                    class="grid grid-cols-5 gap-1.5"
                    role="radiogroup"
                    :aria-label="score.label"
                >
                    <button
                        v-for="n in 5"
                        :key="n"
                        type="button"
                        role="radio"
                        :aria-checked="evalForm[score.key] === n"
                        :class="
                            cn(
                                'h-11 rounded-lg border text-sm font-semibold tabular-nums transition-colors',
                                evalForm[score.key] === n
                                    ? 'border-primary bg-primary text-primary-foreground'
                                    : 'hover:bg-muted',
                            )
                        "
                        @click="evalForm[score.key] = n"
                    >
                        {{ n }}
                    </button>
                </div>
                <InputError :message="evalForm.errors[score.key]" />
            </fieldset>
            <div class="grid gap-1.5">
                <Label for="ev-comments">Comentarios</Label>
                <Textarea
                    id="ev-comments"
                    v-model="evalForm.comments"
                    rows="3"
                    placeholder="¿Qué salió bien? ¿Qué debe mejorar?"
                />
            </div>
        </form>
        <template #footer>
            <Button variant="outline" @click="evalOpen = false"
                >Cancelar</Button
            >
            <Button
                type="submit"
                form="eval-form"
                :disabled="evalForm.processing || !evalReady"
                >{{
                    evalForm.processing ? 'Guardando…' : 'Guardar evaluación'
                }}</Button
            >
        </template>
    </FormSheet>

    <ConfirmDialog
        v-model:open="deleteOpen"
        title="¿Eliminar proveedor?"
        description="Solo se puede eliminar si no tiene órdenes. Si ya trabajó con nosotros, márcalo como inactivo."
        confirm-label="Eliminar"
        loading-label="Eliminando…"
        tone="destructive"
        :confirm="deleteSupplier"
    />
    <ConfirmDialog
        v-model:open="contactDeleteOpen"
        :title="`¿Eliminar a ${contactToDelete?.name ?? 'este contacto'}?`"
        confirm-label="Eliminar"
        loading-label="Eliminando…"
        tone="destructive"
        :confirm="deleteContact"
    />
    <ConfirmDialog
        v-model:open="docDeleteOpen"
        title="¿Quitar documento?"
        :description="`«${docToDelete?.name ?? ''}» dejará de estar vinculado a este proveedor.`"
        confirm-label="Quitar"
        loading-label="Quitando…"
        tone="destructive"
        :confirm="deleteDoc"
    />
</template>
