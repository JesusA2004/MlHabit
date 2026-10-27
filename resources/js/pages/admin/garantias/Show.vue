<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { LifeBuoy, Pencil, Plus } from '@lucide/vue';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import {
    DatePicker,
    DateText,
    EmptyState,
    FileDropzone,
    FormSheet,
    KeyValue,
    PageContainer,
    PageHeader,
    SearchSelect,
    SectionCard,
    StatusBadge,
} from '@/components/ds';
import type { KeyValueItem, SearchSelectOption } from '@/components/ds';
import InputError from '@/components/InputError.vue';
import MediaGrid from '@/components/operations/MediaGrid.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import garantias from '@/routes/admin/garantias';
import incidencias from '@/routes/admin/incidencias';
import type { BreadcrumbItem } from '@/types';
import type {
    EnumOption,
    SelectOption,
    WarrantyClaimRow,
    WarrantyDetail,
} from '@/types/operations';

const props = defineProps<{
    warranty: WarrantyDetail;
    claimStatuses: EnumOption[];
    projectIncidents: SelectOption[];
    options: {
        types: EnumOption[];
        statuses: EnumOption[];
        suppliers?: SelectOption[];
    };
    canManage: boolean;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Panel', href: '/admin' },
            { title: 'Garantías', href: '/admin/garantias' },
            { title: 'Detalle', href: '#' },
        ] as BreadcrumbItem[],
    },
});

const isActive = computed(() => props.warranty.status.value === 'active');

const details = computed<KeyValueItem[]>(() => [
    { key: 'project', label: 'Proyecto' },
    { key: 'type', label: 'Tipo', value: props.warranty.type.label },
    { key: 'validity', label: 'Vigencia' },
    {
        key: 'supplier',
        label: 'Proveedor responsable',
        value: props.warranty.supplier?.name ?? 'ML Habitat',
    },
]);

const supplierOptions = computed<SearchSelectOption<number>[]>(() =>
    (props.options.suppliers ?? []).map((s) => ({
        value: s.value,
        label: s.label,
    })),
);

// ------------------------------------------------------------ Edición
const editOpen = ref(false);
const form = useForm({
    supplier_id: props.warranty.supplier?.id ?? null,
    starts_on: props.warranty.startsOn as string | null,
    ends_on: props.warranty.endsOn as string | null,
    scope: props.warranty.scope,
    exclusions: props.warranty.exclusions ?? '',
});

function submitEdit() {
    form.put(garantias.update(props.warranty.id).url, {
        preserveScroll: true,
        onSuccess: () => (editOpen.value = false),
    });
}

// ------------------------------------------------------------ Anular
const voidOpen = ref(false);
const voidForm = useForm({ reason: '' });
function submitVoid() {
    voidForm.post(garantias.anular(props.warranty.id).url, {
        preserveScroll: true,
        onSuccess: () => (voidOpen.value = false),
    });
}

// ------------------------------------------------------------ Reclamos
const claimOpen = ref(false);
const claimForm = useForm({
    description: '',
    incident_id: null as number | null,
    media_ids: [] as number[],
});
function submitClaim() {
    claimForm.post(garantias.reclamos.store(props.warranty.id).url, {
        preserveScroll: true,
        onSuccess: () => {
            claimOpen.value = false;
            claimForm.reset();
        },
    });
}

const updating = ref<WarrantyClaimRow | null>(null);
const updateOpen = ref(false);
const updateForm = useForm({ status: '', resolution: '' });
const needsResolution = computed(() =>
    ['resolved', 'rejected'].includes(updateForm.status),
);

function openUpdate(claim: WarrantyClaimRow) {
    updating.value = claim;
    updateForm.status = claim.status.value;
    updateForm.resolution = claim.resolution ?? '';
    updateForm.clearErrors();
    updateOpen.value = true;
}

function submitUpdate() {
    if (!updating.value) return;
    updateForm.put(
        garantias.reclamos.update({
            warranty: props.warranty.id,
            claim: updating.value.id,
        }).url,
        {
            preserveScroll: true,
            onSuccess: () => (updateOpen.value = false),
            onError: (e) => {
                if (!e.resolution)
                    toast.error(
                        Object.values(e)[0] ??
                            'No se pudo actualizar el reclamo.',
                    );
            },
        },
    );
}

function attachClaimEvidence(claim: WarrantyClaimRow, media: { id: number }[]) {
    if (media.length === 0) return;
    router.post(
        garantias.reclamos.evidencia({
            warranty: props.warranty.id,
            claim: claim.id,
        }).url,
        { media_ids: media.map((m) => m.id) },
        {
            preserveScroll: true,
            onError: (e) =>
                toast.error(
                    Object.values(e)[0] ?? 'No se pudo agregar la evidencia.',
                ),
        },
    );
}
</script>

<template>
    <Head :title="`${warranty.folio} · Garantía`" />

    <PageContainer class="flex flex-col gap-6">
        <PageHeader :title="`Garantía de ${warranty.type.label.toLowerCase()}`">
            <template #meta>
                <div
                    class="mt-2 flex flex-wrap items-center gap-2 text-sm text-muted-foreground"
                >
                    <span class="tabular-nums">{{ warranty.folio }}</span>
                    <StatusBadge
                        :tone="warranty.status.tone"
                        :label="warranty.status.label"
                    />
                    <span v-if="isActive && !warranty.inForce"
                        >Fuera de vigencia</span
                    >
                </div>
            </template>
            <template v-if="canManage" #actions>
                <Button
                    v-if="isActive"
                    variant="outline"
                    @click="editOpen = true"
                    ><Pencil class="size-4" /> Editar</Button
                >
                <Button v-if="warranty.inForce" @click="claimOpen = true"
                    ><Plus class="size-4" /> Abrir reclamo</Button
                >
            </template>
        </PageHeader>

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="flex flex-col gap-6 lg:col-span-2">
                <SectionCard title="Qué cubre">
                    <p class="text-sm leading-relaxed whitespace-pre-line">
                        {{ warranty.scope }}
                    </p>
                </SectionCard>
                <SectionCard title="Exclusiones">
                    <p
                        v-if="warranty.exclusions"
                        class="text-sm leading-relaxed whitespace-pre-line"
                    >
                        {{ warranty.exclusions }}
                    </p>
                    <p v-else class="text-sm text-muted-foreground">
                        No se capturaron exclusiones.
                    </p>
                </SectionCard>

                <SectionCard title="Reclamos" :icon="LifeBuoy">
                    <EmptyState
                        v-if="warranty.claims.length === 0"
                        compact
                        title="Sin reclamos"
                        description="Cuando el cliente reporte un problema cubierto, ábrelo aquí para darle seguimiento."
                    />
                    <ul v-else class="flex flex-col gap-4">
                        <li
                            v-for="claim in warranty.claims"
                            :key="claim.id"
                            class="flex flex-col gap-3 rounded-xl border p-4"
                        >
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="font-medium tabular-nums">{{
                                    claim.folio
                                }}</span>
                                <StatusBadge
                                    :tone="claim.status.tone"
                                    :label="claim.status.label"
                                    size="sm"
                                />
                                <span class="text-xs text-muted-foreground"
                                    >Abierto
                                    <DateText :value="claim.createdAt" /> por
                                    {{ claim.reporter?.name ?? '—' }}</span
                                >
                                <Button
                                    v-if="canManage"
                                    size="sm"
                                    variant="outline"
                                    class="ml-auto"
                                    @click="openUpdate(claim)"
                                    >Actualizar</Button
                                >
                            </div>
                            <p class="text-sm whitespace-pre-line">
                                {{ claim.description }}
                            </p>
                            <a
                                v-if="claim.incident"
                                :href="incidencias.show(claim.incident.id).url"
                                class="text-sm text-primary hover:underline"
                            >
                                Incidencia {{ claim.incident.folio }}
                            </a>
                            <div
                                v-if="claim.resolution"
                                class="rounded-lg bg-muted/60 p-3 text-sm"
                            >
                                <span class="font-medium">Resolución:</span>
                                {{ claim.resolution }}
                            </div>
                            <MediaGrid
                                :items="claim.evidence"
                                size="sm"
                                empty-text="Sin evidencia."
                            />
                            <FileDropzone
                                v-if="
                                    canManage &&
                                    ['open', 'in_progress'].includes(
                                        claim.status.value,
                                    )
                                "
                                compact
                                visibility="private"
                                collection="privado/garantias"
                                :accept="['image/*', 'video/*', '.pdf']"
                                label="Agregar evidencia"
                                @all-completed="
                                    attachClaimEvidence(claim, $event)
                                "
                            />
                        </li>
                    </ul>
                </SectionCard>
            </div>

            <div class="flex flex-col gap-6">
                <SectionCard title="Ficha">
                    <KeyValue :items="details" layout="stacked">
                        <template #value-project>
                            <span class="flex flex-col">
                                <span>{{ warranty.project.name }}</span>
                                <span
                                    class="text-xs text-muted-foreground tabular-nums"
                                    >{{ warranty.project.folio
                                    }}<template
                                        v-if="warranty.project.customer"
                                    >
                                        ·
                                        {{
                                            warranty.project.customer
                                        }}</template
                                    ></span
                                >
                            </span>
                        </template>
                        <template #value-validity>
                            <DateText
                                :value="warranty.startsOn"
                                format="long"
                            />
                            al
                            <DateText :value="warranty.endsOn" format="long" />
                        </template>
                    </KeyValue>
                </SectionCard>
                <Button
                    v-if="canManage && warranty.status.value !== 'voided'"
                    variant="ghost"
                    class="text-destructive"
                    @click="voidOpen = true"
                    >Anular garantía</Button
                >
            </div>
        </div>
    </PageContainer>

    <FormSheet v-model:open="editOpen" title="Editar garantía" size="lg">
        <form
            id="edit-warranty"
            class="flex flex-col gap-4"
            @submit.prevent="submitEdit"
        >
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-1.5">
                    <Label>Inicio de vigencia</Label>
                    <DatePicker v-model="form.starts_on" />
                    <InputError :message="form.errors.starts_on" />
                </div>
                <div class="grid gap-1.5">
                    <Label>Fin de vigencia</Label>
                    <DatePicker
                        v-model="form.ends_on"
                        :min="form.starts_on ?? undefined"
                    />
                    <InputError :message="form.errors.ends_on" />
                </div>
            </div>
            <div class="grid gap-1.5">
                <Label>Proveedor responsable</Label>
                <SearchSelect
                    v-model="form.supplier_id"
                    :options="supplierOptions"
                    clearable
                    placeholder="ML Habitat responde directamente"
                />
            </div>
            <div class="grid gap-1.5">
                <Label for="w-scope">Qué cubre</Label>
                <Textarea id="w-scope" v-model="form.scope" rows="4" />
                <InputError :message="form.errors.scope" />
            </div>
            <div class="grid gap-1.5">
                <Label for="w-excl">Exclusiones</Label>
                <Textarea id="w-excl" v-model="form.exclusions" rows="3" />
            </div>
            <InputError
                :message="
                    (form.errors as Record<string, string | undefined>).status
                "
            />
        </form>
        <template #footer>
            <Button variant="outline" @click="editOpen = false"
                >Cancelar</Button
            >
            <Button
                type="submit"
                form="edit-warranty"
                :disabled="form.processing"
                >{{
                    form.processing ? 'Guardando…' : 'Guardar cambios'
                }}</Button
            >
        </template>
    </FormSheet>

    <FormSheet
        v-model:open="voidOpen"
        title="Anular garantía"
        description="La garantía deja de ser válida. El registro y sus reclamos se conservan."
    >
        <form
            id="void-warranty"
            class="flex flex-col gap-2"
            @submit.prevent="submitVoid"
        >
            <Label for="void-reason">Motivo</Label>
            <Textarea id="void-reason" v-model="voidForm.reason" rows="3" />
            <InputError :message="voidForm.errors.reason" />
        </form>
        <template #footer>
            <Button variant="outline" @click="voidOpen = false">Volver</Button>
            <Button
                type="submit"
                form="void-warranty"
                variant="destructive"
                :disabled="voidForm.processing || !voidForm.reason.trim()"
                >Anular garantía</Button
            >
        </template>
    </FormSheet>

    <FormSheet
        v-model:open="claimOpen"
        title="Abrir reclamo de garantía"
        description="Abrir un reclamo pasa el proyecto a atención en garantía."
        size="lg"
    >
        <form
            id="claim-form"
            class="flex flex-col gap-4"
            @submit.prevent="submitClaim"
        >
            <div class="grid gap-1.5">
                <Label for="claim-desc">Qué reporta el cliente</Label>
                <Textarea
                    id="claim-desc"
                    v-model="claimForm.description"
                    rows="4"
                />
                <InputError :message="claimForm.errors.description" />
            </div>
            <div class="grid gap-1.5">
                <Label>Incidencia relacionada (opcional)</Label>
                <SearchSelect
                    v-model="claimForm.incident_id"
                    :options="projectIncidents"
                    clearable
                    placeholder="Sin vincular"
                    empty-text="El proyecto no tiene incidencias."
                />
                <InputError :message="claimForm.errors.incident_id" />
            </div>
            <div class="grid gap-1.5">
                <Label>Evidencia (opcional)</Label>
                <FileDropzone
                    compact
                    visibility="private"
                    collection="privado/garantias"
                    :accept="['image/*', 'video/*', '.pdf']"
                    @uploaded="claimForm.media_ids.push($event.id)"
                />
                <InputError
                    :message="
                        claimForm.errors.media_ids ??
                        (claimForm.errors as Record<string, string | undefined>)
                            .warranty_id
                    "
                />
            </div>
        </form>
        <template #footer>
            <Button variant="outline" @click="claimOpen = false"
                >Cancelar</Button
            >
            <Button
                type="submit"
                form="claim-form"
                :disabled="
                    claimForm.processing || !claimForm.description.trim()
                "
            >
                {{ claimForm.processing ? 'Abriendo…' : 'Abrir reclamo' }}
            </Button>
        </template>
    </FormSheet>

    <FormSheet
        v-model:open="updateOpen"
        :title="`Reclamo ${updating?.folio ?? ''}`"
        description="Al resolverlo o marcarlo como no procedente, el proyecto vuelve a completado si no hay otros reclamos abiertos."
    >
        <form
            id="claim-update"
            class="flex flex-col gap-4"
            @submit.prevent="submitUpdate"
        >
            <div class="grid gap-1.5">
                <Label>Estado</Label>
                <Select v-model="updateForm.status">
                    <SelectTrigger class="w-full"
                        ><SelectValue
                    /></SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="s in claimStatuses"
                            :key="s.value"
                            :value="s.value"
                            >{{ s.label }}</SelectItem
                        >
                    </SelectContent>
                </Select>
            </div>
            <div class="grid gap-1.5">
                <Label for="claim-res"
                    >Resolución{{ needsResolution ? '' : ' (opcional)' }}</Label
                >
                <Textarea
                    id="claim-res"
                    v-model="updateForm.resolution"
                    rows="4"
                />
                <InputError :message="updateForm.errors.resolution" />
            </div>
        </form>
        <template #footer>
            <Button variant="outline" @click="updateOpen = false"
                >Cancelar</Button
            >
            <Button
                type="submit"
                form="claim-update"
                :disabled="
                    updateForm.processing ||
                    (needsResolution && !updateForm.resolution.trim())
                "
                >Guardar</Button
            >
        </template>
    </FormSheet>
</template>
