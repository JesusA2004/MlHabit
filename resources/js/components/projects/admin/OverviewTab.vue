<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import {
    ArrowRight,
    CircleAlert,
    ExternalLink,
    MapPin,
    Send,
    UserRoundCheck,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import DatePicker from '@/components/ds/DatePicker.vue';
import DateText from '@/components/ds/DateText.vue';
import FormSheet from '@/components/ds/FormSheet.vue';
import KeyValue from '@/components/ds/KeyValue.vue';
import ProgressSteps from '@/components/ds/ProgressSteps.vue';
import SearchSelect from '@/components/ds/SearchSelect.vue';
import SectionCard from '@/components/ds/SectionCard.vue';
import StatusBadge from '@/components/ds/StatusBadge.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import clientes from '@/routes/admin/clientes';
import proyectos from '@/routes/admin/proyectos';
import type { ProjectHubProps } from './types';

const props = defineProps<ProjectHubProps>();
const emit = defineEmits<{ go: [tab: string] }>();

const p = computed(() => props.project);
const cancelled = computed(() => p.value.status.value === 'cancelled');
const completed = computed(() =>
    ['completed', 'warranty_service'].includes(p.value.status.value),
);
const currentStep = computed(() =>
    String(Math.max(1, p.value.status.journey_step)),
);

// ---- Cambio de etapa
const statusOpen = ref(false);
const statusForm = useForm({ status: '', note: '', notify_client: true });
const manualTransitions = computed(() =>
    props.transitions.filter(
        (t) => !['authorized', 'pending_deposit'].includes(t.value),
    ),
);

function openStatus() {
    statusForm.reset();
    statusForm.clearErrors();
    statusForm.status = manualTransitions.value[0]?.value ?? '';
    statusOpen.value = true;
}

function submitStatus() {
    statusForm.post(proyectos.estado(p.value.uuid).url, {
        preserveScroll: true,
        onSuccess: () => (statusOpen.value = false),
    });
}

// ---- Responsables y fechas
const team = useForm({
    name: p.value.name,
    advisor_id: p.value.advisor_id,
    designer_id: p.value.designer_id,
    operations_owner_id: p.value.operations_owner_id,
    target_install_date: p.value.target_install_date,
    address_id: p.value.address_id,
    product_category_id: p.value.product_category_id,
});

function saveTeam() {
    team.patch(proyectos.update(p.value.uuid).url, {
        preserveScroll: true,
        onSuccess: () => team.defaults(),
    });
}

const opt = (list: { id: number; name: string }[]) =>
    list.map((o) => ({ value: o.id, label: o.name }));
const addressOptions = computed(() =>
    props.addresses.map((a) => ({
        value: a.id,
        label: a.label ? `${a.label} · ${a.one_line}` : a.one_line,
    })),
);

// ---- Portal
const inviting = ref(false);
function invite() {
    router.post(
        clientes.invitacion(p.value.customer.uuid).url,
        {},
        {
            preserveScroll: true,
            onStart: () => (inviting.value = true),
            onFinish: () => (inviting.value = false),
        },
    );
}

const attention = computed(() => {
    const items: { key: string; text: string; tab: string }[] = [];
    if (props.summary.pending_technical)
        items.push({
            key: 'tech',
            text: 'Un diseño aprobado por el cliente espera aprobación técnica.',
            tab: 'diseno',
        });
    if (props.summary.pending_client_design)
        items.push({
            key: 'client',
            text: 'El cliente tiene un diseño por revisar.',
            tab: 'diseno',
        });
    if (props.summary.open_quote)
        items.push({
            key: 'quote',
            text: 'Hay una cotización enviada esperando respuesta.',
            tab: 'cotizaciones',
        });
    if (props.summary.deposit_pending)
        items.push({
            key: 'deposit',
            text: 'El contrato espera el anticipo.',
            tab: 'contrato',
        });
    if (!p.value.customer.has_portal)
        items.push({
            key: 'portal',
            text: 'El cliente aún no tiene acceso al portal.',
            tab: 'resumen',
        });

    return items;
});

const dates = computed(() => [
    { key: 'created', label: 'Creado', value: p.value.created_at },
    { key: 'contracted', label: 'Contratado', value: p.value.contracted_at },
    {
        key: 'target',
        label: 'Instalación objetivo',
        value: p.value.target_install_date,
    },
    { key: 'completed', label: 'Entregado', value: p.value.completed_at },
]);
</script>

<template>
    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_340px]">
        <div class="flex min-w-0 flex-col gap-6">
            <SectionCard title="Etapa actual">
                <template #actions>
                    <Button
                        v-if="can.manage && manualTransitions.length"
                        size="sm"
                        variant="outline"
                        @click="openStatus"
                        >Cambiar etapa</Button
                    >
                </template>
                <div class="flex flex-col gap-5">
                    <div class="flex flex-wrap items-center gap-3">
                        <StatusBadge
                            :tone="p.status.tone"
                            :label="p.status.label"
                        />
                        <p class="text-sm text-muted-foreground">
                            El cliente ve: «{{ p.status.client_description }}»
                        </p>
                    </div>
                    <p v-if="cancelled" class="rounded-lg bg-muted p-3 text-sm">
                        Cancelado <DateText :value="p.cancelled_at" />. Motivo:
                        {{ p.cancel_reason || 'sin motivo registrado' }}
                    </p>
                    <ProgressSteps
                        v-else
                        :steps="journey"
                        :current="currentStep"
                        :completed="completed"
                        label="Recorrido del cliente"
                    />
                </div>
            </SectionCard>

            <SectionCard v-if="attention.length" title="Requiere atención">
                <ul class="flex flex-col divide-y">
                    <li
                        v-for="item in attention"
                        :key="item.key"
                        class="flex items-center justify-between gap-3 py-2.5 first:pt-0 last:pb-0"
                    >
                        <span class="flex items-start gap-2 text-sm">
                            <CircleAlert
                                class="mt-0.5 size-4 shrink-0 text-warning"
                                aria-hidden="true"
                            />
                            {{ item.text }}
                        </span>
                        <Button
                            v-if="item.tab !== 'resumen'"
                            variant="ghost"
                            size="sm"
                            @click="emit('go', item.tab)"
                        >
                            Ver <ArrowRight class="size-4" aria-hidden="true" />
                        </Button>
                    </li>
                </ul>
            </SectionCard>

            <SectionCard
                title="Equipo y datos del proyecto"
                description="Quién lleva cada parte y para cuándo."
            >
                <form
                    class="grid gap-4 sm:grid-cols-2"
                    @submit.prevent="saveTeam"
                >
                    <div class="grid gap-1.5 sm:col-span-2">
                        <Label for="p-name">Nombre del proyecto</Label>
                        <Input
                            id="p-name"
                            v-model="team.name"
                            :disabled="!can.manage"
                            maxlength="160"
                        />
                        <InputError :message="team.errors.name" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="p-advisor">Asesor</Label>
                        <SearchSelect
                            id="p-advisor"
                            v-model="team.advisor_id"
                            :options="opt(options.advisors)"
                            placeholder="Sin asignar"
                            clearable
                            :disabled="!can.manage"
                        />
                        <InputError :message="team.errors.advisor_id" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="p-designer">Diseñador</Label>
                        <SearchSelect
                            id="p-designer"
                            v-model="team.designer_id"
                            :options="opt(options.designers)"
                            placeholder="Sin asignar"
                            clearable
                            :disabled="!can.manage"
                        />
                        <InputError :message="team.errors.designer_id" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="p-ops">Responsable de operación</Label>
                        <SearchSelect
                            id="p-ops"
                            v-model="team.operations_owner_id"
                            :options="opt(options.operations)"
                            placeholder="Sin asignar"
                            clearable
                            :disabled="!can.manage"
                        />
                        <InputError
                            :message="team.errors.operations_owner_id"
                        />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="p-target"
                            >Fecha objetivo de instalación</Label
                        >
                        <DatePicker
                            id="p-target"
                            v-model="team.target_install_date"
                            clearable
                            :disabled="!can.manage"
                        />
                        <InputError
                            :message="team.errors.target_install_date"
                        />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="p-category">Categoría</Label>
                        <SearchSelect
                            id="p-category"
                            v-model="team.product_category_id"
                            :options="opt(options.categories)"
                            placeholder="Sin categoría"
                            clearable
                            :disabled="!can.manage"
                        />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="p-address">Domicilio de instalación</Label>
                        <SearchSelect
                            id="p-address"
                            v-model="team.address_id"
                            :options="addressOptions"
                            placeholder="Sin domicilio"
                            clearable
                            :disabled="!can.manage"
                        />
                        <InputError :message="team.errors.address_id" />
                    </div>
                    <div
                        v-if="can.manage"
                        class="flex justify-end sm:col-span-2"
                    >
                        <Button
                            type="submit"
                            :disabled="!team.isDirty || team.processing"
                        >
                            {{
                                team.processing
                                    ? 'Guardando…'
                                    : 'Guardar cambios'
                            }}
                        </Button>
                    </div>
                </form>
            </SectionCard>
        </div>

        <aside class="flex flex-col gap-6">
            <SectionCard title="Cliente">
                <div class="flex flex-col gap-3 text-sm">
                    <p class="text-base font-medium">{{ p.customer.name }}</p>
                    <p
                        v-if="p.customer.email"
                        class="break-all text-muted-foreground"
                    >
                        {{ p.customer.email }}
                    </p>
                    <p v-if="p.customer.phone" class="text-muted-foreground">
                        {{ p.customer.phone }}
                    </p>
                    <div
                        v-if="p.address"
                        class="flex gap-2 rounded-lg bg-muted/60 p-3"
                    >
                        <MapPin
                            class="mt-0.5 size-4 shrink-0 text-muted-foreground"
                            aria-hidden="true"
                        />
                        <div class="min-w-0">
                            <p>{{ p.address.one_line }}</p>
                            <a
                                :href="p.address.maps_url"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="mt-1 inline-flex items-center gap-1 text-primary hover:underline"
                            >
                                Ver en mapa
                                <ExternalLink
                                    class="size-3.5"
                                    aria-hidden="true"
                                />
                            </a>
                        </div>
                    </div>
                    <a
                        v-if="p.lead"
                        :href="`/admin/prospectos/${p.lead.uuid}`"
                        class="text-primary hover:underline"
                        >Prospecto {{ p.lead.folio }}</a
                    >

                    <div class="mt-1 border-t pt-3">
                        <p class="flex items-center gap-2 font-medium">
                            <UserRoundCheck
                                class="size-4 text-muted-foreground"
                                aria-hidden="true"
                            />
                            Portal del cliente
                        </p>
                        <p class="mt-1 text-muted-foreground">
                            {{
                                p.customer.portal_active
                                    ? 'Activo: ya creó su contraseña.'
                                    : p.customer.has_portal
                                      ? 'Invitación enviada; aún no la acepta.'
                                      : p.customer.email
                                        ? 'Sin acceso todavía.'
                                        : 'Agrega un correo al cliente para invitarlo.'
                            }}
                        </p>
                        <Button
                            v-if="
                                can.invite &&
                                p.customer.email &&
                                !p.customer.portal_active
                            "
                            class="mt-3 w-full"
                            variant="outline"
                            :disabled="inviting"
                            @click="invite"
                        >
                            <Send class="size-4" aria-hidden="true" />
                            {{
                                inviting
                                    ? 'Enviando…'
                                    : p.customer.has_portal
                                      ? 'Reenviar invitación'
                                      : 'Invitar al portal'
                            }}
                        </Button>
                    </div>
                </div>
            </SectionCard>

            <SectionCard title="Fechas clave">
                <KeyValue
                    :items="
                        dates.map((d) => ({
                            key: d.key,
                            label: d.label,
                            value: d.value,
                        }))
                    "
                >
                    <template
                        v-for="d in dates"
                        :key="d.key"
                        #[`value-${d.key}`]
                    >
                        <DateText
                            :value="d.value"
                            :format="d.key === 'target' ? 'medium' : 'medium'"
                        />
                    </template>
                </KeyValue>
            </SectionCard>
        </aside>

        <FormSheet
            v-model:open="statusOpen"
            title="Cambiar etapa"
            description="Solo se muestran los pasos permitidos desde la etapa actual. Contrato y anticipo mueven el proyecto solos."
        >
            <form
                id="status-form"
                class="flex flex-col gap-5"
                @submit.prevent="submitStatus"
            >
                <RadioGroup
                    v-model="statusForm.status"
                    class="flex flex-col gap-2"
                    aria-label="Nueva etapa"
                >
                    <Label
                        v-for="t in manualTransitions"
                        :key="t.value"
                        :for="`st-${t.value}`"
                        class="flex cursor-pointer items-center gap-3 rounded-lg border p-3 font-normal has-[[data-state=checked]]:border-primary has-[[data-state=checked]]:bg-primary-soft/40 pointer-coarse:min-h-12"
                    >
                        <RadioGroupItem
                            :id="`st-${t.value}`"
                            :value="t.value"
                        />
                        <StatusBadge
                            :tone="t.tone"
                            :label="t.label"
                            size="sm"
                        />
                    </Label>
                </RadioGroup>
                <InputError :message="statusForm.errors.status" />
                <div class="grid gap-1.5">
                    <Label for="st-note">{{
                        statusForm.status === 'cancelled'
                            ? 'Motivo de la cancelación'
                            : 'Nota (opcional)'
                    }}</Label>
                    <Textarea
                        id="st-note"
                        v-model="statusForm.note"
                        rows="3"
                        maxlength="200"
                    />
                    <InputError :message="statusForm.errors.note" />
                </div>
                <Label
                    for="st-notify"
                    class="flex items-center justify-between gap-3 rounded-lg border p-3 font-normal"
                >
                    <span>
                        <span class="block font-medium">Avisar al cliente</span>
                        <span class="text-sm text-muted-foreground"
                            >Aparece en su portal y le llega un correo.</span
                        >
                    </span>
                    <Switch id="st-notify" v-model="statusForm.notify_client" />
                </Label>
            </form>
            <template #footer>
                <Button variant="outline" @click="statusOpen = false"
                    >Cancelar</Button
                >
                <Button
                    type="submit"
                    form="status-form"
                    :disabled="!statusForm.status || statusForm.processing"
                    :variant="
                        statusForm.status === 'cancelled'
                            ? 'destructive'
                            : 'default'
                    "
                >
                    {{ statusForm.processing ? 'Guardando…' : 'Cambiar etapa' }}
                </Button>
            </template>
        </FormSheet>
    </div>
</template>
