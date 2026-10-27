<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { CalendarClock, Mail, Pencil, Phone, Send, Wrench } from '@lucide/vue';
import { computed, ref } from 'vue';
import ConfirmDialog from '@/components/ds/ConfirmDialog.vue';
import DateText from '@/components/ds/DateText.vue';
import EmptyState from '@/components/ds/EmptyState.vue';
import FileDropzone from '@/components/ds/FileDropzone.vue';
import FormSheet from '@/components/ds/FormSheet.vue';
import KeyValue from '@/components/ds/KeyValue.vue';
import PageContainer from '@/components/ds/PageContainer.vue';
import PageHeader from '@/components/ds/PageHeader.vue';
import SearchSelect from '@/components/ds/SearchSelect.vue';
import SectionCard from '@/components/ds/SectionCard.vue';
import StatusBadge from '@/components/ds/StatusBadge.vue';
import UserAvatar from '@/components/ds/UserAvatar.vue';
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
import tecnicos from '@/routes/admin/tecnicos';
import documentos from '@/routes/admin/tecnicos/documentos';
import type { BreadcrumbItem } from '@/types';
import type {
    EnumOption,
    InstallationRow,
    MediaItem,
    SelectOption,
} from '@/types/operations';

const props = defineProps<{
    technician: {
        id: number;
        userId: number;
        name: string;
        email: string;
        phone: string | null;
        active: boolean;
        status: EnumOption;
        doesVisits: boolean;
        doesInstallations: boolean;
        supplier: { id: number; name: string } | null;
        zones: string[];
        skills: string | null;
        notes: string | null;
        zoneIds: number[];
        documents: MediaItem[];
        lastLoginAt: string | null;
    };
    workload: {
        installations: InstallationRow[];
        upcomingVisits: number | null;
    };
    evaluation: { reviewed: number; approved: number; completedOrders: number };
    options: {
        statuses: EnumOption[];
        suppliers: SelectOption[];
        zones: SelectOption[];
        role: string;
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Panel', href: '/admin' },
            { title: 'Técnicos', href: '/admin/tecnicos' },
            { title: 'Detalle', href: '#' },
        ] as BreadcrumbItem[],
    },
});

const { can } = usePermissions();
const canManage = computed(() => can('suppliers.manage'));
const id = computed(() => props.technician.id);

const approvalRate = computed(() =>
    props.evaluation.reviewed > 0
        ? Math.round(
              (props.evaluation.approved / props.evaluation.reviewed) * 100,
          )
        : null,
);

const details = computed(() => [
    {
        key: 'supplier',
        label: 'Proveedor',
        value: props.technician.supplier?.name ?? 'Personal de ML Habitat',
    },
    {
        key: 'zones',
        label: 'Zonas',
        value: props.technician.zones.join(', ') || 'Sin zonas asignadas',
    },
    {
        key: 'work',
        label: 'Hace',
        value:
            [
                props.technician.doesVisits && 'visitas de medición',
                props.technician.doesInstallations && 'instalaciones',
            ]
                .filter(Boolean)
                .join(' y ') || 'Nada asignado',
    },
    { key: 'login', label: 'Último acceso', value: null },
    {
        key: 'skills',
        label: 'Habilidades',
        value: props.technician.skills,
        full: true,
    },
    {
        key: 'notes',
        label: 'Notas internas',
        value: props.technician.notes,
        full: true,
    },
]);

// ------------------------------------------------------------ Editar
const editOpen = ref(false);
const form = useForm({
    name: props.technician.name,
    phone: props.technician.phone ?? '',
    supplier_id: props.technician.supplier?.id ?? null,
    status: props.technician.status.value,
    does_visits: props.technician.doesVisits,
    does_installations: props.technician.doesInstallations,
    skills: props.technician.skills ?? '',
    notes: props.technician.notes ?? '',
    is_active: props.technician.active,
    zone_ids: [...props.technician.zoneIds],
});

function toggleZone(zoneId: number) {
    form.zone_ids = form.zone_ids.includes(zoneId)
        ? form.zone_ids.filter((z) => z !== zoneId)
        : [...form.zone_ids, zoneId];
}

function save() {
    form.put(tecnicos.update(id.value).url, {
        preserveScroll: true,
        onSuccess: () => (editOpen.value = false),
    });
}

// ------------------------------------------------------------ Invitación
const inviting = ref(false);
function resendInvite() {
    router.post(
        tecnicos.invitacion(id.value).url,
        {},
        {
            preserveScroll: true,
            onStart: () => (inviting.value = true),
            onFinish: () => (inviting.value = false),
        },
    );
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
</script>

<template>
    <Head :title="technician.name" />

    <PageContainer class="flex flex-col gap-6">
        <PageHeader :title="technician.name">
            <template #meta>
                <UserAvatar :name="technician.name" size="sm" />
                <StatusBadge
                    v-if="!technician.active"
                    label="Sin acceso a la app"
                    tone="neutral"
                />
                <StatusBadge
                    v-else
                    :label="technician.status.label"
                    :tone="technician.status.tone"
                />
                <span>{{ options.role }}</span>
            </template>
            <template v-if="canManage" #actions>
                <Button
                    variant="outline"
                    :disabled="inviting || !technician.active"
                    @click="resendInvite"
                >
                    <Send class="size-4" />
                    {{ inviting ? 'Enviando…' : 'Reenviar acceso' }}
                </Button>
                <Button @click="editOpen = true"
                    ><Pencil class="size-4" /> Editar</Button
                >
            </template>
        </PageHeader>

        <div class="grid gap-3 sm:grid-cols-3">
            <div class="rounded-xl border bg-card p-4">
                <p class="text-sm text-muted-foreground">
                    Instalaciones en agenda
                </p>
                <p
                    class="mt-1 font-display text-2xl font-semibold tabular-nums"
                >
                    {{ workload.installations.length }}
                </p>
            </div>
            <div class="rounded-xl border bg-card p-4">
                <p class="text-sm text-muted-foreground">Visitas próximas</p>
                <p
                    class="mt-1 font-display text-2xl font-semibold tabular-nums"
                >
                    {{ workload.upcomingVisits ?? '—' }}
                </p>
            </div>
            <div class="rounded-xl border bg-card p-4">
                <p class="text-sm text-muted-foreground">
                    Partidas aprobadas por calidad
                </p>
                <p
                    class="mt-1 font-display text-2xl font-semibold tabular-nums"
                >
                    {{ approvalRate !== null ? `${approvalRate}%` : '—' }}
                </p>
                <p class="text-xs text-muted-foreground">
                    {{ evaluation.approved }} de
                    {{ evaluation.reviewed }} revisadas ·
                    {{ evaluation.completedOrders }} órdenes cerradas
                </p>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="flex flex-col gap-6 lg:col-span-2">
                <SectionCard
                    title="Carga de trabajo"
                    description="Instalaciones donde es instalador líder (borrador, programadas y en curso)."
                    flush
                >
                    <EmptyState
                        v-if="!workload.installations.length"
                        compact
                        :bordered="false"
                        :icon="Wrench"
                        title="Sin instalaciones asignadas"
                        description="Cuando sea instalador líder de una orden aparecerá aquí."
                    />
                    <ul v-else class="divide-y">
                        <li v-for="o in workload.installations" :key="o.id">
                            <Link
                                :href="`/admin/instalaciones/${o.id}`"
                                class="flex items-center gap-3 px-4 py-3 hover:bg-muted/50"
                            >
                                <CalendarClock
                                    class="size-4 shrink-0 text-muted-foreground"
                                    aria-hidden="true"
                                />
                                <span class="min-w-0 flex-1">
                                    <span class="block text-sm font-medium"
                                        >{{ o.folio }} ·
                                        {{ o.project.name }}</span
                                    >
                                    <span
                                        class="block text-xs text-muted-foreground"
                                    >
                                        <DateText
                                            v-if="o.scheduledStart"
                                            :value="o.scheduledStart"
                                            format="datetime"
                                        />
                                        <template v-else>Sin fecha</template>
                                    </span>
                                </span>
                                <StatusBadge
                                    :label="o.status.label"
                                    :tone="o.status.tone"
                                    size="sm"
                                />
                            </Link>
                        </li>
                    </ul>
                </SectionCard>

                <SectionCard title="Ficha">
                    <KeyValue :items="details" :columns="2">
                        <template #value-login>
                            <DateText
                                :value="technician.lastLoginAt"
                                relative
                                fallback="Nunca ha entrado"
                            />
                        </template>
                    </KeyValue>
                </SectionCard>
            </div>

            <div class="flex flex-col gap-6">
                <SectionCard title="Contacto">
                    <div class="flex flex-col gap-2 text-sm">
                        <a
                            :href="`mailto:${technician.email}`"
                            class="inline-flex items-center gap-2 break-all text-primary hover:underline"
                            ><Mail class="size-4" />{{ technician.email }}</a
                        >
                        <a
                            v-if="technician.phone"
                            :href="`tel:${technician.phone}`"
                            class="inline-flex items-center gap-2 text-primary hover:underline"
                            ><Phone class="size-4" />{{ technician.phone }}</a
                        >
                    </div>
                </SectionCard>

                <SectionCard
                    title="Documentos"
                    description="Identificación, constancias, cursos. Privados."
                >
                    <MediaGrid
                        :items="technician.documents"
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
                        collection="privado/tecnicos"
                        :accept="['application/pdf', 'image/*']"
                        :max-size="25 * 1024 * 1024"
                        label="Arrastra documentos o elige desde tu equipo"
                        @all-completed="attachDocuments"
                    />
                </SectionCard>
            </div>
        </div>
    </PageContainer>

    <FormSheet
        v-model:open="editOpen"
        title="Editar técnico"
        description="El correo de acceso no se cambia desde aquí."
        size="lg"
    >
        <form id="tech-edit" class="grid gap-4" @submit.prevent="save">
            <div class="grid gap-1.5">
                <Label for="te-name">Nombre completo</Label>
                <Input id="te-name" v-model="form.name" required />
                <InputError :message="form.errors.name" />
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-1.5">
                    <Label for="te-phone">Celular</Label>
                    <Input id="te-phone" v-model="form.phone" type="tel" />
                    <InputError :message="form.errors.phone" />
                </div>
                <div class="grid gap-1.5">
                    <Label>Estado</Label>
                    <Select v-model="form.status">
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
            <div class="grid gap-1.5">
                <Label for="te-supplier">Proveedor</Label>
                <SearchSelect
                    id="te-supplier"
                    v-model="form.supplier_id"
                    :options="options.suppliers"
                    placeholder="Personal de ML Habitat"
                    clearable
                />
                <InputError :message="form.errors.supplier_id" />
            </div>
            <div class="grid gap-2 sm:grid-cols-2">
                <label
                    class="flex items-center justify-between gap-3 rounded-lg border p-3"
                >
                    <span class="text-sm font-medium">Hace visitas</span>
                    <Switch v-model="form.does_visits" />
                </label>
                <label
                    class="flex items-center justify-between gap-3 rounded-lg border p-3"
                >
                    <span class="text-sm font-medium">Hace instalaciones</span>
                    <Switch v-model="form.does_installations" />
                </label>
            </div>
            <label
                :class="
                    cn(
                        'flex items-center justify-between gap-3 rounded-lg border p-3',
                        !form.is_active && 'border-warning bg-warning-soft',
                    )
                "
            >
                <span class="text-sm">
                    <span class="block font-medium"
                        >Acceso a la app de campo</span
                    >
                    <span class="text-muted-foreground">{{
                        form.is_active
                            ? 'Puede iniciar sesión.'
                            : 'No podrá iniciar sesión.'
                    }}</span>
                </span>
                <Switch v-model="form.is_active" />
            </label>
            <fieldset class="grid gap-2">
                <legend class="mb-1 text-sm font-medium">
                    Zonas que atiende
                </legend>
                <div class="flex flex-wrap gap-2">
                    <button
                        v-for="z in options.zones"
                        :key="z.value"
                        type="button"
                        :aria-pressed="form.zone_ids.includes(z.value)"
                        :class="
                            cn(
                                'h-9 rounded-full border px-3 text-sm transition-colors pointer-coarse:h-11',
                                form.zone_ids.includes(z.value)
                                    ? 'border-primary bg-primary-soft text-primary'
                                    : 'hover:bg-muted',
                            )
                        "
                        @click="toggleZone(z.value)"
                    >
                        {{ z.label }}
                    </button>
                </div>
            </fieldset>
            <div class="grid gap-1.5">
                <Label for="te-skills">Habilidades</Label>
                <Textarea id="te-skills" v-model="form.skills" rows="2" />
            </div>
            <div class="grid gap-1.5">
                <Label for="te-notes">Notas internas</Label>
                <Textarea id="te-notes" v-model="form.notes" rows="2" />
            </div>
        </form>
        <template #footer>
            <Button variant="outline" @click="editOpen = false"
                >Cancelar</Button
            >
            <Button
                type="submit"
                form="tech-edit"
                :disabled="form.processing"
                >{{
                    form.processing ? 'Guardando…' : 'Guardar cambios'
                }}</Button
            >
        </template>
    </FormSheet>

    <ConfirmDialog
        v-model:open="docDeleteOpen"
        title="¿Quitar documento?"
        :description="`«${docToDelete?.name ?? ''}» dejará de estar vinculado a este técnico.`"
        confirm-label="Quitar"
        loading-label="Quitando…"
        tone="destructive"
        :confirm="deleteDoc"
    />
</template>
