<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import {
    BadgeCheck,
    CircleCheck,
    CircleDashed,
    FileText,
    Plus,
    Send,
    Trash2,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import type { UploadedMediaResult } from '@/composables/useChunkedUpload';
import ConfirmDialog from '@/components/ds/ConfirmDialog.vue';
import DateText from '@/components/ds/DateText.vue';
import EmptyState from '@/components/ds/EmptyState.vue';
import FileDropzone from '@/components/ds/FileDropzone.vue';
import FormSheet from '@/components/ds/FormSheet.vue';
import SearchSelect from '@/components/ds/SearchSelect.vue';
import SectionCard from '@/components/ds/SectionCard.vue';
import StatusBadge from '@/components/ds/StatusBadge.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import disenos from '@/routes/admin/disenos';
import type { DesignData, MeasurementData } from '@/types/projects';
import type { HubCan, HubProject } from './types';

const props = defineProps<{
    project: HubProject;
    designs: DesignData[];
    measurements: MeasurementData[];
    can: HubCan;
}>();

const current = computed(
    () => props.designs.find((d) => d.status.value !== 'superseded') ?? null,
);
const history = computed(() =>
    props.designs.filter((d) => d.id !== current.value?.id),
);
const showHistory = ref(false);

const measurementOptions = computed(() =>
    props.measurements
        .filter((m) => m.status.value !== 'draft')
        .map((m) => ({
            value: m.id,
            label: `Medición v${m.version} · ${m.status.label}`,
        })),
);

// ---- Crear / editar
const sheetOpen = ref(false);
const editing = ref<DesignData | null>(null);
const form = useForm({
    title: '',
    notes: '',
    measurement_id: null as number | null,
});

function openCreate() {
    editing.value = null;
    form.reset();
    form.clearErrors();
    form.measurement_id = measurementOptions.value[0]?.value ?? null;
    sheetOpen.value = true;
}

function openEdit(design: DesignData) {
    editing.value = design;
    form.clearErrors();
    form.title = design.title;
    form.notes = design.notes ?? '';
    form.measurement_id = design.measurement_id;
    sheetOpen.value = true;
}

function submit() {
    const options = {
        preserveScroll: true,
        onSuccess: () => (sheetOpen.value = false),
    };
    if (editing.value) {
        form.put(disenos.update(editing.value.id).url, options);
    } else {
        form.post(disenos.store(props.project.uuid).url, options);
    }
}

const editingSent = computed(
    () => editing.value !== null && editing.value.status.value !== 'draft',
);

// ---- Archivos
const fileRole = ref<'render' | 'file'>('render');

function attach(designId: number, media: UploadedMediaResult) {
    router.post(
        `/admin/disenos/${designId}/archivos`,
        { media_id: media.id, role: fileRole.value },
        { preserveScroll: true },
    );
}

const toRemove = ref<{ design: number; attachment: number } | null>(null);
const removeOpen = computed({
    get: () => toRemove.value !== null,
    set: (v) => !v && (toRemove.value = null),
});

function removeFile() {
    if (!toRemove.value) return;
    const { design, attachment } = toRemove.value;

    return new Promise<void>((resolve, reject) =>
        router.delete(`/admin/disenos/${design}/archivos/${attachment}`, {
            preserveScroll: true,
            onSuccess: () => resolve(),
            onError: () => reject(new Error('No se pudo quitar el archivo.')),
        }),
    );
}

// ---- Enviar y aprobar
const sendOpen = ref(false);
function send() {
    if (!current.value) return;
    const id = current.value.id;

    return new Promise<void>((resolve, reject) =>
        router.post(
            disenos.enviar(id).url,
            {},
            {
                preserveScroll: true,
                onSuccess: () => resolve(),
                onError: (errors) =>
                    reject(
                        new Error(
                            Object.values(errors)[0] ?? 'No se pudo enviar.',
                        ),
                    ),
            },
        ),
    );
}

const techOpen = ref(false);
const techForm = useForm({ notes: '' });
function approveTechnical() {
    if (!current.value) return;
    techForm.post(disenos.aprobacionTecnica(current.value.id).url, {
        preserveScroll: true,
        onSuccess: () => (techOpen.value = false),
    });
}
</script>

<template>
    <div class="flex flex-col gap-6">
        <EmptyState
            v-if="!designs.length"
            :icon="CircleDashed"
            title="Aún no hay diseño"
            description="Crea la primera versión a partir de la medición, sube renders y envíala al cliente para su aprobación."
        >
            <Button v-if="can.designs_manage" @click="openCreate"
                ><Plus class="size-4" aria-hidden="true" /> Crear versión
                1</Button
            >
        </EmptyState>

        <SectionCard
            v-if="current"
            :title="`Diseño v${current.number} · ${current.title}`"
        >
            <template #actions>
                <div class="flex flex-wrap gap-2">
                    <Button
                        v-if="can.designs_manage"
                        size="sm"
                        variant="outline"
                        @click="openEdit(current)"
                    >
                        {{
                            current.status.value === 'draft'
                                ? 'Editar'
                                : 'Nueva versión'
                        }}
                    </Button>
                    <Button
                        v-if="
                            can.designs_manage &&
                            current.status.value === 'draft'
                        "
                        size="sm"
                        :disabled="!current.files.length"
                        @click="sendOpen = true"
                    >
                        <Send class="size-4" aria-hidden="true" /> Enviar al
                        cliente
                    </Button>
                    <Button
                        v-if="
                            can.approve_technical &&
                            current.status.value === 'client_approved'
                        "
                        size="sm"
                        @click="techOpen = true"
                    >
                        <BadgeCheck class="size-4" aria-hidden="true" />
                        Aprobación técnica
                    </Button>
                </div>
            </template>

            <div class="flex flex-col gap-6">
                <div class="flex flex-wrap items-center gap-3">
                    <StatusBadge
                        :tone="current.status.tone"
                        :label="current.status.label"
                    />
                    <span
                        v-if="current.sent_at"
                        class="text-sm text-muted-foreground"
                        >Enviado <DateText :value="current.sent_at" relative
                    /></span>
                    <span
                        v-if="current.created_by"
                        class="text-sm text-muted-foreground"
                        >· por {{ current.created_by }}</span
                    >
                </div>

                <ol class="grid gap-3 sm:grid-cols-2" aria-label="Aprobaciones">
                    <li class="rounded-lg border p-4">
                        <p class="flex items-center gap-2 text-sm font-medium">
                            <component
                                :is="
                                    current.client_decided_at &&
                                    current.status.value !== 'changes_requested'
                                        ? CircleCheck
                                        : CircleDashed
                                "
                                class="size-4"
                                :class="
                                    current.client_decided_at &&
                                    current.status.value !== 'changes_requested'
                                        ? 'text-success'
                                        : 'text-muted-foreground'
                                "
                                aria-hidden="true"
                            />
                            Aprobación del cliente
                        </p>
                        <p class="mt-1 text-sm text-muted-foreground">
                            <template v-if="current.client_decided_at">
                                {{
                                    current.status.value === 'changes_requested'
                                        ? 'Pidió cambios'
                                        : 'Aprobó distribución y acabados'
                                }}
                                ·
                                <DateText :value="current.client_decided_at" />
                            </template>
                            <template
                                v-else-if="
                                    current.status.value === 'sent_to_client'
                                "
                                >Esperando su revisión en el portal.</template
                            >
                            <template v-else>Pendiente de enviar.</template>
                        </p>
                        <p
                            v-if="current.client_comment"
                            class="mt-2 rounded-md bg-muted p-2 text-sm whitespace-pre-line"
                        >
                            «{{ current.client_comment }}»
                        </p>
                    </li>
                    <li class="rounded-lg border p-4">
                        <p class="flex items-center gap-2 text-sm font-medium">
                            <component
                                :is="
                                    current.technical_approved_at
                                        ? BadgeCheck
                                        : CircleDashed
                                "
                                class="size-4"
                                :class="
                                    current.technical_approved_at
                                        ? 'text-success'
                                        : 'text-muted-foreground'
                                "
                                aria-hidden="true"
                            />
                            Aprobación técnica
                        </p>
                        <p class="mt-1 text-sm text-muted-foreground">
                            <template v-if="current.technical_approved_at"
                                >{{ current.technical_approved_by }} ·
                                <DateText
                                    :value="current.technical_approved_at"
                            /></template>
                            <template v-else
                                >Requisito para fabricar. Se otorga después de
                                la aprobación del cliente.</template
                            >
                        </p>
                        <p
                            v-if="current.technical_notes"
                            class="mt-2 text-sm whitespace-pre-line"
                        >
                            {{ current.technical_notes }}
                        </p>
                    </li>
                </ol>

                <p v-if="current.notes" class="text-sm whitespace-pre-line">
                    {{ current.notes }}
                </p>

                <div>
                    <h3 class="mb-3 text-sm font-semibold">
                        Renders y archivos
                    </h3>
                    <p
                        v-if="!current.files.length"
                        class="mb-3 text-sm text-muted-foreground"
                    >
                        Sube al menos un render para poder enviarlo al cliente.
                    </p>
                    <ul
                        v-else
                        class="mb-4 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4"
                    >
                        <li
                            v-for="f in current.files"
                            :key="f.id"
                            class="group relative overflow-hidden rounded-lg border bg-muted"
                        >
                            <a
                                :href="f.url ?? undefined"
                                target="_blank"
                                rel="noopener"
                                class="block"
                            >
                                <img
                                    v-if="f.is_image && f.url"
                                    :src="f.url"
                                    :alt="f.caption || f.name"
                                    loading="lazy"
                                    class="aspect-[4/3] w-full object-cover"
                                />
                                <span
                                    v-else
                                    class="flex aspect-[4/3] flex-col items-center justify-center gap-2 p-3 text-center text-xs"
                                >
                                    <FileText
                                        class="size-6 text-muted-foreground"
                                        aria-hidden="true"
                                    />{{ f.name }}
                                </span>
                            </a>
                            <span
                                class="absolute top-2 left-2 rounded bg-background/90 px-1.5 py-0.5 text-[11px] font-medium"
                                >{{
                                    f.role === 'render' ? 'Render' : 'Archivo'
                                }}</span
                            >
                            <Button
                                v-if="
                                    can.designs_manage &&
                                    current.status.value === 'draft'
                                "
                                size="icon"
                                variant="secondary"
                                class="absolute top-2 right-2 size-8"
                                :aria-label="`Quitar ${f.name}`"
                                @click="
                                    toRemove = {
                                        design: current.id,
                                        attachment: f.id,
                                    }
                                "
                            >
                                <Trash2 class="size-4" aria-hidden="true" />
                            </Button>
                        </li>
                    </ul>

                    <div
                        v-if="
                            can.designs_manage &&
                            current.status.value === 'draft'
                        "
                        class="flex flex-col gap-3"
                    >
                        <ToggleGroup
                            v-model="fileRole"
                            type="single"
                            variant="outline"
                            size="sm"
                            class="w-fit"
                            aria-label="Tipo de archivo"
                        >
                            <ToggleGroupItem value="render"
                                >Render</ToggleGroupItem
                            >
                            <ToggleGroupItem value="file"
                                >Plano / archivo técnico</ToggleGroupItem
                            >
                        </ToggleGroup>
                        <FileDropzone
                            visibility="private"
                            :accept="[
                                'image/*',
                                'application/pdf',
                                '.dwg',
                                '.skp',
                            ]"
                            :max-size="200 * 1024 * 1024"
                            hint="Imágenes, PDF, DWG o SKP. Se guardan como privados."
                            compact
                            @uploaded="attach(current.id, $event)"
                        />
                    </div>
                    <p
                        v-else-if="current.status.value !== 'draft'"
                        class="text-xs text-muted-foreground"
                    >
                        Esta versión ya se envió: para cambiar archivos crea una
                        nueva versión.
                    </p>
                </div>
            </div>
        </SectionCard>

        <div
            v-if="
                current &&
                can.designs_manage &&
                current.status.value !== 'draft'
            "
            class="flex justify-end"
        >
            <Button variant="outline" @click="openCreate"
                ><Plus class="size-4" aria-hidden="true" /> Nueva versión desde
                cero</Button
            >
        </div>

        <SectionCard v-if="history.length" title="Versiones anteriores" flush>
            <template #actions>
                <Button
                    variant="ghost"
                    size="sm"
                    :aria-expanded="showHistory"
                    @click="showHistory = !showHistory"
                    >{{
                        showHistory ? 'Ocultar' : `Ver ${history.length}`
                    }}</Button
                >
            </template>
            <ul v-if="showHistory" class="flex flex-col divide-y">
                <li
                    v-for="d in history"
                    :key="d.id"
                    class="flex flex-wrap items-center justify-between gap-3 px-4 py-3 text-sm"
                >
                    <span
                        ><span class="font-medium">v{{ d.number }}</span> ·
                        {{ d.title }}
                        <span class="text-muted-foreground"
                            >· {{ d.files.length }} archivo(s)</span
                        ></span
                    >
                    <StatusBadge
                        :tone="d.status.tone"
                        :label="d.status.label"
                        size="sm"
                    />
                </li>
            </ul>
        </SectionCard>

        <FormSheet
            v-model:open="sheetOpen"
            :title="
                editing
                    ? editingSent
                        ? `Nueva versión a partir de v${editing.number}`
                        : `Editar v${editing.number}`
                    : 'Nueva versión de diseño'
            "
            :description="
                editingSent
                    ? 'La versión enviada se conserva; esta será la siguiente y copiará sus archivos.'
                    : 'Después podrás subir renders y enviarla al cliente.'
            "
        >
            <form
                id="design-form"
                class="flex flex-col gap-4"
                @submit.prevent="submit"
            >
                <div class="grid gap-1.5">
                    <Label for="d-title">Título</Label>
                    <Input
                        id="d-title"
                        v-model="form.title"
                        maxlength="160"
                        placeholder="Ej. Cocina en L con isla"
                    />
                    <InputError :message="form.errors.title" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="d-measurement">Medición de referencia</Label>
                    <SearchSelect
                        id="d-measurement"
                        v-model="form.measurement_id"
                        :options="measurementOptions"
                        placeholder="Sin medición"
                        clearable
                    />
                    <InputError :message="form.errors.measurement_id" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="d-notes"
                        >Notas para el equipo y el cliente</Label
                    >
                    <Textarea
                        id="d-notes"
                        v-model="form.notes"
                        rows="5"
                        maxlength="4000"
                        placeholder="Acabados, herrajes, consideraciones…"
                    />
                    <InputError :message="form.errors.notes" />
                </div>
            </form>
            <template #footer>
                <Button variant="outline" @click="sheetOpen = false"
                    >Cancelar</Button
                >
                <Button
                    type="submit"
                    form="design-form"
                    :disabled="form.processing"
                    >{{
                        form.processing
                            ? 'Guardando…'
                            : editingSent
                              ? 'Crear versión'
                              : 'Guardar'
                    }}</Button
                >
            </template>
        </FormSheet>

        <FormSheet
            v-model:open="techOpen"
            title="Aprobación técnica"
            description="Confirmas que medidas, materiales y herrajes son fabricables. Fabricación solo arranca con esta aprobación."
        >
            <form
                id="tech-form"
                class="flex flex-col gap-4"
                @submit.prevent="approveTechnical"
            >
                <div class="grid gap-1.5">
                    <Label for="t-notes">Notas técnicas (opcional)</Label>
                    <Textarea
                        id="t-notes"
                        v-model="techForm.notes"
                        rows="5"
                        maxlength="2000"
                    />
                    <InputError
                        :message="
                            techForm.errors.notes ||
                            (techForm.errors as Record<string, string>).status
                        "
                    />
                </div>
            </form>
            <template #footer>
                <Button variant="outline" @click="techOpen = false"
                    >Cancelar</Button
                >
                <Button
                    type="submit"
                    form="tech-form"
                    :disabled="techForm.processing"
                    >{{
                        techForm.processing
                            ? 'Aprobando…'
                            : 'Aprobar técnicamente'
                    }}</Button
                >
            </template>
        </FormSheet>

        <ConfirmDialog
            v-model:open="sendOpen"
            title="¿Enviar el diseño al cliente?"
            description="Lo verá en su portal y le llegará un correo. Después ya no podrás editar esta versión; los cambios crearán una nueva."
            confirm-label="Enviar"
            loading-label="Enviando…"
            :confirm="send"
        />
        <ConfirmDialog
            v-model:open="removeOpen"
            title="¿Quitar este archivo del diseño?"
            description="El archivo deja de estar ligado a esta versión."
            confirm-label="Quitar"
            tone="destructive"
            :confirm="removeFile"
        />
    </div>
</template>
