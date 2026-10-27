<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import {
    CalendarClock,
    FolderKanban,
    Inbox,
    Mail,
    MapPin,
    Pencil,
    Phone,
    Plus,
    Send,
    Trash2,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import ConfirmDialog from '@/components/ds/ConfirmDialog.vue';
import DateText from '@/components/ds/DateText.vue';
import EmptyState from '@/components/ds/EmptyState.vue';
import FormSheet from '@/components/ds/FormSheet.vue';
import KeyValue from '@/components/ds/KeyValue.vue';
import type { KeyValueItem } from '@/components/ds/KeyValue.vue';
import PageContainer from '@/components/ds/PageContainer.vue';
import PageHeader from '@/components/ds/PageHeader.vue';
import SectionCard from '@/components/ds/SectionCard.vue';
import StatusBadge from '@/components/ds/StatusBadge.vue';
import type { Tone } from '@/components/ds/tones';
import InputError from '@/components/InputError.vue';
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
import { Textarea } from '@/components/ui/textarea';
import { apiFetch } from '@/lib/http';

type Option = { value: string; label: string; tone?: string };
type Address = {
    id: number;
    label: string | null;
    street: string;
    exteriorNumber: string;
    interiorNumber: string | null;
    neighborhood: string;
    postalCode: string;
    municipality: string;
    state: string;
    references: string | null;
    oneLine: string;
    coverage: { covered: boolean; zone: string | null; visitsEnabled: boolean };
};

const props = defineProps<{
    customer: {
        uuid: string;
        name: string;
        firstName: string;
        lastName: string | null;
        email: string | null;
        phone: string | null;
        preferredContact: string | null;
        hasPortal: boolean;
        notes: string | null;
        privacyAcceptedAt: string | null;
        createdAt: string | null;
        portal: { email: string; lastLoginAt: string | null } | null;
    };
    addresses: Address[];
    leads: {
        uuid: string;
        folio: string;
        status: Option;
        assignee: { id: number; name: string } | null;
        createdAt: string | null;
        url: string | null;
    }[];
    projects: {
        uuid: string;
        folio: string;
        name: string;
        status: Option;
        url: string | null;
    }[];
    consultations: {
        uuid: string;
        folio: string;
        startsAt: string;
        status: Option;
        channel: Option;
        advisor: { name: string } | null;
    }[];
    options: { preferredContacts: Option[] };
    can: { update: boolean; invite: boolean };
    links: { invite: string; coverage: string };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Panel', href: '/admin' },
            { title: 'Clientes', href: '/admin/clientes' },
            { title: 'Ficha', href: '' },
        ],
    },
});

const base = computed(() => `/admin/clientes/${props.customer.uuid}`);
const tone = (o: { tone?: string } | null | undefined) =>
    (o?.tone ?? 'neutral') as Tone;
const contactLabel = computed(
    () =>
        props.options.preferredContacts.find(
            (o) => o.value === props.customer.preferredContact,
        )?.label ?? 'Sin indicar',
);

const contactItems = computed<KeyValueItem[]>(() => [
    { key: 'phone', label: 'Teléfono', value: props.customer.phone },
    { key: 'email', label: 'Correo', value: props.customer.email },
    {
        key: 'preferred',
        label: 'Contacto preferido',
        value: contactLabel.value,
    },
    {
        key: 'privacy',
        label: 'Aviso de privacidad',
        value: props.customer.privacyAcceptedAt,
    },
    {
        key: 'portal',
        label: 'Portal del cliente',
        value: props.customer.portal
            ? props.customer.portal.email
            : 'Sin acceso',
    },
    { key: 'notes', label: 'Notas', value: props.customer.notes, full: true },
]);

// ---------------------------------------------------------------- Contacto

const editOpen = ref(false);
const contactForm = useForm({
    first_name: props.customer.firstName,
    last_name: props.customer.lastName ?? '',
    email: props.customer.email ?? '',
    phone: props.customer.phone ?? '',
    preferred_contact: props.customer.preferredContact,
    notes: props.customer.notes ?? '',
});

function openEdit() {
    contactForm.defaults({
        first_name: props.customer.firstName,
        last_name: props.customer.lastName ?? '',
        email: props.customer.email ?? '',
        phone: props.customer.phone ?? '',
        preferred_contact: props.customer.preferredContact,
        notes: props.customer.notes ?? '',
    });
    contactForm.reset();
    contactForm.clearErrors();
    editOpen.value = true;
}

function saveContact() {
    contactForm.patch(base.value, {
        preserveScroll: true,
        onSuccess: () => (editOpen.value = false),
    });
}

// ---------------------------------------------------------------- Domicilios

const addressOpen = ref(false);
const editingAddress = ref<Address | null>(null);
const addressForm = useForm({
    label: '',
    street: '',
    exterior_number: '',
    interior_number: '',
    neighborhood: '',
    postal_code: '',
    municipality: '',
    state: '',
    references: '',
});

function openAddress(address: Address | null) {
    editingAddress.value = address;
    addressForm.defaults({
        label: address?.label ?? '',
        street: address?.street ?? '',
        exterior_number: address?.exteriorNumber ?? '',
        interior_number: address?.interiorNumber ?? '',
        neighborhood: address?.neighborhood ?? '',
        postal_code: address?.postalCode ?? '',
        municipality: address?.municipality ?? '',
        state: address?.state ?? '',
        references: address?.references ?? '',
    });
    addressForm.reset();
    addressForm.clearErrors();
    coverage.value = null;
    addressOpen.value = true;
}

function saveAddress() {
    const options = {
        preserveScroll: true,
        onSuccess: () => (addressOpen.value = false),
    };

    if (editingAddress.value) {
        addressForm.put(
            `${base.value}/domicilios/${editingAddress.value.id}`,
            options,
        );
    } else {
        addressForm.post(`${base.value}/domicilios`, options);
    }
}

type CoverageResult = {
    covered: boolean;
    acceptsVisits: boolean;
    zone: { name: string } | null;
};
const coverage = ref<CoverageResult | null>(null);
const checking = ref(false);

const checkCoverage = useDebounceFn(async (cp: string) => {
    checking.value = true;

    try {
        const response = await apiFetch(
            `${props.links.coverage}?cp=${encodeURIComponent(cp)}`,
        );
        coverage.value = response.ok
            ? ((await response.json()) as CoverageResult)
            : null;
    } catch {
        coverage.value = null;
    } finally {
        checking.value = false;
    }
}, 350);

watch(
    () => addressForm.postal_code,
    (cp) => {
        const digits = (cp ?? '').replace(/\D/g, '');

        if (digits.length === 5) {
            void checkCoverage(digits);
        } else {
            coverage.value = null;
        }
    },
);

const coverageText = computed(() => {
    if (!coverage.value) {
        return null;
    }

    if (coverage.value.acceptsVisits) {
        return {
            tone: 'success' as Tone,
            label: `Con cobertura · ${coverage.value.zone?.name ?? ''}`,
        };
    }

    if (coverage.value.covered) {
        return {
            tone: 'info' as Tone,
            label: `Zona ${coverage.value.zone?.name ?? ''}, sin visitas por ahora`,
        };
    }

    return { tone: 'warning' as Tone, label: 'Fuera de cobertura operativa' };
});

function addressBadge(a: Address): { tone: Tone; label: string } {
    if (a.coverage.visitsEnabled) {
        return { tone: 'success', label: `Cobertura · ${a.coverage.zone}` };
    }

    if (a.coverage.covered) {
        return { tone: 'info', label: `${a.coverage.zone} · sin visitas` };
    }

    return { tone: 'warning', label: 'Fuera de cobertura' };
}

const deleting = ref<Address | null>(null);
const deleteOpen = ref(false);

function askDelete(a: Address) {
    deleting.value = a;
    deleteOpen.value = true;
}

function routerPromise(method: 'post' | 'delete', url: string) {
    return new Promise<void>((resolve, reject) => {
        const opts = {
            preserveScroll: true,
            onSuccess: (page: {
                props: { errors?: Record<string, string> };
            }) => {
                const err =
                    page.props.errors && Object.values(page.props.errors)[0];

                if (err) {
                    reject(new Error(err));
                } else {
                    resolve();
                }
            },
            onError: (errors: Record<string, string>) =>
                reject(
                    new Error(
                        Object.values(errors)[0] ??
                            'No se pudo completar la acción.',
                    ),
                ),
        };

        if (method === 'delete') {
            router.delete(url, opts);
        } else {
            router.post(url, {}, opts);
        }
    });
}

// ---------------------------------------------------------------- Portal

const inviteOpen = ref(false);

const fields: {
    key:
        | 'street'
        | 'exterior_number'
        | 'interior_number'
        | 'neighborhood'
        | 'municipality'
        | 'state';
    label: string;
    optional?: boolean;
    span?: string;
}[] = [
    { key: 'street', label: 'Calle', span: 'sm:col-span-2' },
    { key: 'exterior_number', label: 'Número exterior' },
    { key: 'interior_number', label: 'Número interior', optional: true },
    { key: 'neighborhood', label: 'Colonia', span: 'sm:col-span-2' },
    { key: 'municipality', label: 'Municipio o alcaldía' },
    { key: 'state', label: 'Estado' },
];
</script>

<template>
    <Head :title="customer.name" />

    <PageContainer>
        <PageHeader :title="customer.name">
            <template #meta>
                <span
                    >Cliente desde <DateText :value="customer.createdAt"
                /></span>
                <StatusBadge
                    v-if="customer.hasPortal"
                    tone="brand"
                    label="Con acceso al portal"
                    size="sm"
                />
            </template>
            <template #actions>
                <Button v-if="customer.phone" variant="outline" as-child>
                    <a :href="`tel:+52${customer.phone}`"
                        ><Phone class="size-4" /> Llamar</a
                    >
                </Button>
                <Button v-if="customer.email" variant="outline" as-child>
                    <a :href="`mailto:${customer.email}`"
                        ><Mail class="size-4" /> Correo</a
                    >
                </Button>
                <Button v-if="can.invite" @click="inviteOpen = true"
                    ><Send class="size-4" /> Invitar al portal</Button
                >
            </template>
        </PageHeader>

        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
            <div class="flex min-w-0 flex-col gap-6">
                <SectionCard title="Domicilios" :icon="MapPin">
                    <template v-if="can.update" #actions>
                        <Button
                            size="sm"
                            variant="outline"
                            @click="openAddress(null)"
                            ><Plus class="size-4" /> Agregar</Button
                        >
                    </template>
                    <ul v-if="addresses.length" class="divide-y">
                        <li
                            v-for="a in addresses"
                            :key="a.id"
                            class="flex flex-col gap-2 py-3 sm:flex-row sm:items-start sm:justify-between"
                        >
                            <div class="min-w-0">
                                <p class="font-medium">
                                    {{ a.label || 'Domicilio' }}
                                </p>
                                <p class="text-sm text-muted-foreground">
                                    {{ a.oneLine }}
                                </p>
                                <p
                                    v-if="a.references"
                                    class="mt-1 text-xs text-muted-foreground"
                                >
                                    Referencias: {{ a.references }}
                                </p>
                            </div>
                            <div class="flex shrink-0 items-center gap-1">
                                <StatusBadge
                                    size="sm"
                                    v-bind="addressBadge(a)"
                                />
                                <template v-if="can.update">
                                    <Button
                                        variant="ghost"
                                        size="icon-sm"
                                        :aria-label="`Editar ${a.label || 'domicilio'}`"
                                        @click="openAddress(a)"
                                        ><Pencil
                                    /></Button>
                                    <Button
                                        variant="ghost"
                                        size="icon-sm"
                                        :aria-label="`Eliminar ${a.label || 'domicilio'}`"
                                        @click="askDelete(a)"
                                        ><Trash2
                                    /></Button>
                                </template>
                            </div>
                        </li>
                    </ul>
                    <EmptyState
                        v-else
                        compact
                        :bordered="false"
                        title="Sin domicilios"
                        description="Agrega el domicilio donde se instalará el proyecto para validar la cobertura."
                    />
                </SectionCard>

                <SectionCard title="Solicitudes" :icon="Inbox">
                    <ul v-if="leads.length" class="divide-y">
                        <li
                            v-for="l in leads"
                            :key="l.uuid"
                            class="flex items-center justify-between gap-3 py-3"
                        >
                            <div>
                                <component
                                    :is="l.url ? Link : 'span'"
                                    :href="l.url ?? undefined"
                                    class="font-medium tabular-nums hover:text-primary"
                                    >{{ l.folio }}</component
                                >
                                <p class="text-xs text-muted-foreground">
                                    <DateText :value="l.createdAt" /> ·
                                    {{ l.assignee?.name ?? 'Sin responsable' }}
                                </p>
                            </div>
                            <StatusBadge
                                :tone="tone(l.status)"
                                :label="l.status.label"
                                size="sm"
                            />
                        </li>
                    </ul>
                    <EmptyState
                        v-else
                        compact
                        :bordered="false"
                        title="Sin solicitudes"
                    />
                </SectionCard>

                <SectionCard title="Proyectos" :icon="FolderKanban">
                    <ul v-if="projects.length" class="divide-y">
                        <li
                            v-for="p in projects"
                            :key="p.uuid"
                            class="flex items-center justify-between gap-3 py-3"
                        >
                            <div class="min-w-0">
                                <component
                                    :is="p.url ? Link : 'span'"
                                    :href="p.url ?? undefined"
                                    class="font-medium hover:text-primary"
                                    >{{ p.name }}</component
                                >
                                <p
                                    class="text-xs text-muted-foreground tabular-nums"
                                >
                                    {{ p.folio }}
                                </p>
                            </div>
                            <StatusBadge
                                :tone="tone(p.status)"
                                :label="p.status.label"
                                size="sm"
                            />
                        </li>
                    </ul>
                    <EmptyState
                        v-else
                        compact
                        :bordered="false"
                        title="Aún sin proyectos"
                        description="El proyecto se crea desde la solicitud cuando el cliente avanza a medición o diseño."
                    />
                </SectionCard>

                <SectionCard title="Asesorías" :icon="CalendarClock">
                    <ul v-if="consultations.length" class="divide-y">
                        <li v-for="c in consultations" :key="c.uuid">
                            <Link
                                :href="`/admin/asesorias/${c.uuid}`"
                                class="flex items-center justify-between gap-3 py-3 hover:text-primary"
                            >
                                <div>
                                    <p class="font-medium">
                                        <DateText
                                            :value="c.startsAt"
                                            format="datetime"
                                        />
                                    </p>
                                    <p class="text-xs text-muted-foreground">
                                        {{ c.channel.label }} ·
                                        {{ c.advisor?.name ?? 'Sin asesor' }}
                                    </p>
                                </div>
                                <StatusBadge
                                    :tone="tone(c.status)"
                                    :label="c.status.label"
                                    size="sm"
                                />
                            </Link>
                        </li>
                    </ul>
                    <EmptyState
                        v-else
                        compact
                        :bordered="false"
                        title="Sin asesorías"
                    />
                </SectionCard>
            </div>

            <aside class="flex flex-col gap-6">
                <SectionCard title="Contacto">
                    <template v-if="can.update" #actions>
                        <Button size="sm" variant="ghost" @click="openEdit"
                            ><Pencil class="size-4" /> Editar</Button
                        >
                    </template>
                    <KeyValue :items="contactItems" layout="stacked">
                        <template #value-privacy="{ item }">
                            <span v-if="item.value"
                                >Aceptado el
                                <DateText
                                    :value="String(item.value)"
                                    format="datetime"
                            /></span>
                            <span v-else class="text-warning-ink"
                                >Sin registro</span
                            >
                        </template>
                        <template #value-notes="{ item }">
                            <span class="whitespace-pre-line">{{
                                item.value ?? '—'
                            }}</span>
                        </template>
                    </KeyValue>
                    <p
                        v-if="customer.portal?.lastLoginAt"
                        class="mt-3 text-xs text-muted-foreground"
                    >
                        Último acceso al portal
                        <DateText
                            :value="customer.portal.lastLoginAt"
                            relative
                        />
                    </p>
                </SectionCard>
            </aside>
        </div>
    </PageContainer>

    <!-- Editar contacto -->
    <FormSheet v-model:open="editOpen" title="Editar contacto">
        <form
            id="contact-form"
            class="grid gap-4"
            @submit.prevent="saveContact"
        >
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-1.5">
                    <Label for="ct-first">Nombre</Label>
                    <Input
                        id="ct-first"
                        v-model="contactForm.first_name"
                        :aria-invalid="!!contactForm.errors.first_name"
                    />
                    <InputError :message="contactForm.errors.first_name" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="ct-last">Apellidos</Label>
                    <Input id="ct-last" v-model="contactForm.last_name" />
                    <InputError :message="contactForm.errors.last_name" />
                </div>
            </div>
            <div class="grid gap-1.5">
                <Label for="ct-phone">Teléfono</Label>
                <Input
                    id="ct-phone"
                    v-model="contactForm.phone"
                    type="tel"
                    inputmode="tel"
                    placeholder="10 dígitos"
                    :aria-invalid="!!contactForm.errors.phone"
                />
                <InputError :message="contactForm.errors.phone" />
            </div>
            <div class="grid gap-1.5">
                <Label for="ct-email">Correo</Label>
                <Input
                    id="ct-email"
                    v-model="contactForm.email"
                    type="email"
                    :aria-invalid="!!contactForm.errors.email"
                />
                <InputError :message="contactForm.errors.email" />
            </div>
            <div class="grid gap-1.5">
                <Label for="ct-pref">Contacto preferido</Label>
                <Select v-model="contactForm.preferred_contact">
                    <SelectTrigger id="ct-pref" class="w-full"
                        ><SelectValue placeholder="Sin indicar"
                    /></SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="o in options.preferredContacts"
                            :key="o.value"
                            :value="o.value"
                            >{{ o.label }}</SelectItem
                        >
                    </SelectContent>
                </Select>
            </div>
            <div class="grid gap-1.5">
                <Label for="ct-notes">Notas internas</Label>
                <Textarea id="ct-notes" v-model="contactForm.notes" rows="5" />
                <InputError :message="contactForm.errors.notes" />
            </div>
        </form>
        <template #footer>
            <Button variant="outline" @click="editOpen = false"
                >Cancelar</Button
            >
            <Button
                type="submit"
                form="contact-form"
                :disabled="contactForm.processing"
            >
                <Spinner v-if="contactForm.processing" /> Guardar cambios
            </Button>
        </template>
    </FormSheet>

    <!-- Domicilio -->
    <FormSheet
        v-model:open="addressOpen"
        :title="editingAddress ? 'Editar domicilio' : 'Agregar domicilio'"
        size="lg"
    >
        <form
            id="address-form"
            class="grid gap-4"
            @submit.prevent="saveAddress"
        >
            <div class="grid gap-1.5">
                <Label for="ad-label"
                    >Nombre
                    <span class="font-normal text-muted-foreground"
                        >(opcional)</span
                    ></Label
                >
                <Input
                    id="ad-label"
                    v-model="addressForm.label"
                    placeholder="Casa, departamento, oficina…"
                />
            </div>
            <div class="grid gap-1.5">
                <Label for="ad-cp">Código postal</Label>
                <Input
                    id="ad-cp"
                    v-model="addressForm.postal_code"
                    inputmode="numeric"
                    maxlength="5"
                    class="sm:w-40"
                    :aria-invalid="!!addressForm.errors.postal_code"
                    aria-describedby="ad-cp-hint"
                />
                <p
                    id="ad-cp-hint"
                    class="flex min-h-6 items-center gap-2 text-sm"
                    aria-live="polite"
                >
                    <Spinner v-if="checking" class="size-3.5" />
                    <StatusBadge
                        v-else-if="coverageText"
                        size="sm"
                        :tone="coverageText.tone"
                        :label="coverageText.label"
                    />
                </p>
                <InputError :message="addressForm.errors.postal_code" />
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div
                    v-for="field in fields"
                    :key="field.key"
                    :class="['grid gap-1.5', field.span]"
                >
                    <Label :for="`ad-${field.key}`"
                        >{{ field.label }}
                        <span
                            v-if="field.optional"
                            class="font-normal text-muted-foreground"
                            >(opcional)</span
                        ></Label
                    >
                    <Input
                        :id="`ad-${field.key}`"
                        v-model="addressForm[field.key]"
                        :aria-invalid="!!addressForm.errors[field.key]"
                    />
                    <InputError :message="addressForm.errors[field.key]" />
                </div>
            </div>
            <div class="grid gap-1.5">
                <Label for="ad-refs">Referencias</Label>
                <Textarea
                    id="ad-refs"
                    v-model="addressForm.references"
                    rows="2"
                    placeholder="Entre calles, color de fachada, acceso…"
                />
                <InputError :message="addressForm.errors.references" />
            </div>
        </form>
        <template #footer>
            <Button variant="outline" @click="addressOpen = false"
                >Cancelar</Button
            >
            <Button
                type="submit"
                form="address-form"
                :disabled="addressForm.processing"
            >
                <Spinner v-if="addressForm.processing" />
                {{ editingAddress ? 'Guardar cambios' : 'Agregar domicilio' }}
            </Button>
        </template>
    </FormSheet>

    <ConfirmDialog
        v-model:open="deleteOpen"
        title="¿Eliminar este domicilio?"
        :description="deleting ? deleting.oneLine : ''"
        confirm-label="Eliminar"
        loading-label="Eliminando…"
        tone="destructive"
        :confirm="
            () => routerPromise('delete', `${base}/domicilios/${deleting?.id}`)
        "
    />

    <ConfirmDialog
        v-model:open="inviteOpen"
        title="¿Invitar al portal?"
        :description="`Enviaremos a ${customer.email} un enlace para crear su contraseña y seguir su proyecto en línea.`"
        confirm-label="Enviar invitación"
        loading-label="Enviando…"
        :confirm="() => routerPromise('post', links.invite)"
    />
</template>
