<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import {
    ArrowDown,
    ArrowUp,
    ExternalLink,
    Eye,
    EyeOff,
    GripVertical,
    Pencil,
    RotateCcw,
} from '@lucide/vue';
import { ref, watch } from 'vue';
import { VueDraggable } from 'vue-draggable-plus';
import ConfirmDialog from '@/components/ds/ConfirmDialog.vue';
import DateText from '@/components/ds/DateText.vue';
import FormSheet from '@/components/ds/FormSheet.vue';
import StatusBadge from '@/components/ds/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { Switch } from '@/components/ui/switch';
import secciones from '@/routes/admin/sitio/secciones';
import type { AdminSection, MediaRef, VisitPolicyData } from '@/types/web';
import SchemaFieldInput from '../SchemaFieldInput.vue';

/**
 * Secciones de la portada: reordenar (arrastrar o flechas), mostrar u
 * ocultar y editar cada una en una hoja lateral con campos tipados.
 */
const props = defineProps<{
    sections: AdminSection[];
    media: Record<string, MediaRef | null>;
    canPublish: boolean;
    visitPolicy: VisitPolicyData | null;
}>();

const list = ref<AdminSection[]>([...props.sections]);
watch(
    () => props.sections,
    (value) => (list.value = [...value]),
);

const savingOrder = ref(false);
function persistOrder() {
    savingOrder.value = true;
    router.put(
        secciones.orden().url,
        { ids: list.value.map((s) => s.id) },
        {
            preserveScroll: true,
            onFinish: () => (savingOrder.value = false),
        },
    );
}

function move(index: number, delta: number) {
    const target = index + delta;
    if (target < 0 || target >= list.value.length) return;
    const next = [...list.value];
    [next[index], next[target]] = [next[target], next[index]];
    list.value = next;
    persistOrder();
}

function toggle(section: AdminSection, visible: boolean) {
    router.patch(
        secciones.visibilidad(section.id).url,
        { is_visible: visible },
        { preserveScroll: true },
    );
}

// Edición
const editing = ref<AdminSection | null>(null);
const sheetOpen = ref(false);
const draft = ref<Record<string, unknown>>({});
const errors = ref<Record<string, string | undefined>>({});
const saving = ref(false);
const resetOpen = ref(false);

function edit(section: AdminSection) {
    editing.value = section;
    draft.value = JSON.parse(JSON.stringify(section.content)) as Record<
        string,
        unknown
    >;
    errors.value = {};
    sheetOpen.value = true;
}

function save() {
    if (!editing.value) return;
    saving.value = true;
    router.put(
        secciones.update(editing.value.id).url,
        { content: draft.value } as never,
        {
            preserveScroll: true,
            onSuccess: () => (sheetOpen.value = false),
            onError: (e) => (errors.value = e),
            onFinish: () => (saving.value = false),
        },
    );
}

function reset(): Promise<void> {
    return new Promise((resolve, reject) => {
        if (!editing.value) return resolve();
        router.post(
            secciones.restablecer(editing.value.id).url,
            {},
            {
                preserveScroll: true,
                onSuccess: () => {
                    sheetOpen.value = false;
                    resolve();
                },
                onError: () =>
                    reject(new Error('No se pudo restablecer la sección.')),
            },
        );
    });
}
</script>

<template>
    <div class="flex flex-col gap-4">
        <div
            class="flex flex-col gap-3 rounded-xl border border-border bg-card p-4 sm:flex-row sm:items-center sm:justify-between"
        >
            <p class="text-sm text-muted-foreground">
                Arrastra las secciones o usa las flechas para cambiar el orden
                de la portada. Los cambios se publican al guardar.
            </p>
            <div class="flex items-center gap-2">
                <Spinner v-if="savingOrder" class="size-4" />
                <Button
                    as="a"
                    href="/"
                    target="_blank"
                    rel="noopener"
                    variant="outline"
                    size="sm"
                >
                    <ExternalLink class="size-4" /> Ver portada
                </Button>
            </div>
        </div>

        <VueDraggable
            v-model="list"
            :animation="150"
            handle="[data-handle]"
            tag="ol"
            class="flex flex-col gap-2"
            @end="persistOrder"
        >
            <li
                v-for="(section, index) in list"
                :key="section.id"
                class="flex flex-col gap-3 rounded-xl border border-border bg-card p-4 sm:flex-row sm:items-center"
                :class="!section.isVisible && 'bg-muted/40'"
            >
                <div class="flex min-w-0 flex-1 items-start gap-3">
                    <span
                        data-handle
                        class="mt-1 hidden cursor-grab touch-none text-muted-foreground sm:block"
                        aria-hidden="true"
                        ><GripVertical class="size-4"
                    /></span>
                    <span
                        class="mt-0.5 grid size-7 shrink-0 place-items-center rounded-md bg-muted text-xs font-semibold tabular-nums"
                        >{{ index + 1 }}</span
                    >
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <h3 class="font-medium">
                                {{ section.schema.label }}
                            </h3>
                            <StatusBadge
                                v-if="!section.isVisible"
                                tone="neutral"
                                label="Oculta"
                                :icon="EyeOff"
                                size="sm"
                            />
                        </div>
                        <p
                            class="mt-0.5 truncate text-sm text-muted-foreground"
                        >
                            {{
                                (section.content.title as string) ||
                                section.schema.description
                            }}
                        </p>
                        <p
                            v-if="section.updatedAt"
                            class="mt-1 text-xs text-muted-foreground"
                        >
                            Editada
                            <DateText :value="section.updatedAt" relative />
                            <template v-if="section.updatedBy">
                                por {{ section.updatedBy }}</template
                            >
                        </p>
                    </div>
                </div>
                <div class="flex items-center justify-end gap-1">
                    <Button
                        variant="ghost"
                        size="icon"
                        class="size-8"
                        :disabled="index === 0"
                        :aria-label="`Subir ${section.schema.label}`"
                        @click="move(index, -1)"
                        ><ArrowUp class="size-4"
                    /></Button>
                    <Button
                        variant="ghost"
                        size="icon"
                        class="size-8"
                        :disabled="index === list.length - 1"
                        :aria-label="`Bajar ${section.schema.label}`"
                        @click="move(index, 1)"
                        ><ArrowDown class="size-4"
                    /></Button>
                    <label
                        class="ml-2 flex items-center gap-2 text-sm"
                        :title="
                            props.canPublish
                                ? undefined
                                : 'Necesitas permiso para publicar contenido'
                        "
                    >
                        <Switch
                            :model-value="section.isVisible"
                            :disabled="!props.canPublish"
                            :aria-label="`Mostrar ${section.schema.label}`"
                            @update:model-value="
                                (v: boolean) => toggle(section, v)
                            "
                        />
                        <Eye
                            v-if="section.isVisible"
                            class="size-4 text-muted-foreground"
                            aria-hidden="true"
                        />
                    </label>
                    <Button
                        variant="outline"
                        size="sm"
                        class="ml-2"
                        @click="edit(section)"
                        ><Pencil class="size-4" /> Editar</Button
                    >
                </div>
            </li>
        </VueDraggable>

        <FormSheet
            v-model:open="sheetOpen"
            :title="editing?.schema.label ?? 'Sección'"
            :description="editing?.schema.description"
            size="xl"
        >
            <form
                v-if="editing"
                id="section-form"
                class="grid gap-5"
                @submit.prevent="save"
            >
                <p
                    v-if="editing.type === 'home_visit'"
                    class="rounded-lg bg-info-soft p-3 text-sm text-info-ink"
                >
                    <template v-if="props.visitPolicy">
                        Costo vigente de la visita:
                        <strong>{{ props.visitPolicy.feeFormatted }}</strong
                        >{{
                            props.visitPolicy.isCreditable
                                ? ' (acreditable)'
                                : ''
                        }}. Se toma de la política de visitas activa.
                    </template>
                    <template v-else
                        >No hay una política de visitas activa, así que el sitio
                        no muestra ningún costo.</template
                    >
                </p>
                <p
                    v-if="editing.type === 'payment_options'"
                    class="rounded-lg bg-warning-soft p-3 text-sm text-warning-ink"
                >
                    Nunca prometas aprobaciones: las opciones de pago a plazos
                    siempre están sujetas a evaluación.
                </p>
                <SchemaFieldInput
                    v-for="field in editing.schema.fields"
                    :key="field.key"
                    v-model="draft[field.key]"
                    :field="field"
                    :media="props.media"
                    :errors="errors"
                />
            </form>
            <template #footer>
                <Button
                    type="button"
                    variant="ghost"
                    class="mr-auto"
                    @click="resetOpen = true"
                    ><RotateCcw class="size-4" /> Texto sugerido</Button
                >
                <Button
                    type="button"
                    variant="outline"
                    @click="sheetOpen = false"
                    >Cancelar</Button
                >
                <Button type="submit" form="section-form" :disabled="saving"
                    ><Spinner v-if="saving" /> Guardar y publicar</Button
                >
            </template>
        </FormSheet>

        <ConfirmDialog
            v-model:open="resetOpen"
            title="¿Restablecer el texto sugerido?"
            description="Se reemplaza el contenido de esta sección por el texto sugerido original. Las imágenes elegidas se quitan."
            confirm-label="Restablecer"
            tone="destructive"
            :confirm="reset"
        />
    </div>
</template>
