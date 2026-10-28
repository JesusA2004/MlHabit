<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { Palette, Pencil, Plus, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import ConfirmDialog from '@/components/ds/ConfirmDialog.vue';
import EmptyState from '@/components/ds/EmptyState.vue';
import FormSheet from '@/components/ds/FormSheet.vue';
import StatusBadge from '@/components/ds/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import acabados from '@/routes/admin/catalogo/acabados';
import type { MediaRef } from '@/types/web';
import MediaField from '../MediaField.vue';

export type FinishRow = {
    id: number;
    name: string;
    slug: string;
    material: string | null;
    colorHex: string | null;
    description: string | null;
    media: MediaRef | null;
    isActive: boolean;
    productsCount: number;
};

const props = defineProps<{ finishes: FinishRow[] }>();

const sheetOpen = ref(false);
const editing = ref<FinishRow | null>(null);
const form = useForm({
    name: '',
    slug: '',
    material: '',
    color_hex: '',
    description: '',
    media_id: null as number | null,
    is_active: true,
});

function create() {
    editing.value = null;
    form.reset();
    form.clearErrors();
    sheetOpen.value = true;
}

function edit(row: FinishRow) {
    editing.value = row;
    Object.assign(form, {
        name: row.name,
        slug: row.slug,
        material: row.material ?? '',
        color_hex: row.colorHex ?? '',
        description: row.description ?? '',
        media_id: row.media?.id ?? null,
        is_active: row.isActive,
    });
    form.clearErrors();
    sheetOpen.value = true;
}

function submit() {
    const options = {
        preserveScroll: true,
        onSuccess: () => (sheetOpen.value = false),
    };
    form.transform((data) => ({ ...data, color_hex: data.color_hex || null }));
    if (editing.value) form.put(acabados.update(editing.value.id).url, options);
    else form.post(acabados.store().url, options);
}

const removing = ref<FinishRow | null>(null);
const removeOpen = ref(false);
function remove(): Promise<void> {
    return new Promise((resolve, reject) => {
        if (!removing.value) return resolve();
        router.delete(acabados.destroy(removing.value.id).url, {
            preserveScroll: true,
            onSuccess: () => resolve(),
            onError: () => reject(new Error('No se pudo eliminar.')),
        });
    });
}
</script>

<template>
    <div class="flex flex-col gap-4">
        <div class="flex justify-end">
            <Button @click="create"
                ><Plus class="size-4" /> Nuevo acabado</Button
            >
        </div>

        <ul
            v-if="props.finishes.length"
            class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4"
        >
            <li
                v-for="row in props.finishes"
                :key="row.id"
                class="flex items-center gap-3 rounded-xl border border-border bg-card p-3"
            >
                <span
                    class="size-12 shrink-0 overflow-hidden rounded-full ring-1 ring-black/10"
                    :style="
                        row.colorHex
                            ? { backgroundColor: row.colorHex }
                            : undefined
                    "
                >
                    <img
                        v-if="row.media?.thumbnailUrl"
                        :src="row.media.thumbnailUrl"
                        alt=""
                        class="h-full w-full object-cover"
                    />
                </span>
                <div class="min-w-0 flex-1">
                    <p class="truncate font-medium">{{ row.name }}</p>
                    <p class="truncate text-sm text-muted-foreground">
                        {{ row.material || 'Sin material' }} ·
                        {{ row.productsCount }} productos
                    </p>
                    <StatusBadge
                        v-if="!row.isActive"
                        tone="neutral"
                        label="Inactivo"
                        size="sm"
                        class="mt-1"
                    />
                </div>
                <Button
                    variant="ghost"
                    size="icon"
                    class="size-8"
                    :aria-label="`Editar ${row.name}`"
                    @click="edit(row)"
                    ><Pencil class="size-4"
                /></Button>
                <Button
                    variant="ghost"
                    size="icon"
                    class="size-8 text-danger-ink"
                    :aria-label="`Eliminar ${row.name}`"
                    @click="((removing = row), (removeOpen = true))"
                    ><Trash2 class="size-4"
                /></Button>
            </li>
        </ul>
        <EmptyState
            v-else
            :icon="Palette"
            title="Sin acabados"
            description="Registra los materiales y colores que ofreces para mostrarlos en cada producto."
        >
            <Button @click="create"
                ><Plus class="size-4" /> Nuevo acabado</Button
            >
        </EmptyState>

        <FormSheet
            v-model:open="sheetOpen"
            :title="editing ? `Editar ${editing.name}` : 'Nuevo acabado'"
        >
            <form id="finish-form" class="grid gap-4" @submit.prevent="submit">
                <div class="grid gap-1.5">
                    <Label for="fin-name">Nombre</Label>
                    <Input
                        id="fin-name"
                        v-model="form.name"
                        maxlength="120"
                        placeholder="p. ej. Roble natural"
                    />
                    <InputError :message="form.errors.name" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="fin-material">Material</Label>
                    <Input
                        id="fin-material"
                        v-model="form.material"
                        maxlength="120"
                        placeholder="p. ej. Melamina"
                    />
                </div>
                <div class="grid gap-1.5">
                    <Label for="fin-color">Color de referencia</Label>
                    <div class="flex items-center gap-2">
                        <input
                            id="fin-color-picker"
                            type="color"
                            :value="form.color_hex || '#b07d4f'"
                            class="size-10 cursor-pointer rounded-md border border-input bg-transparent p-0.5"
                            aria-label="Elegir color"
                            @input="
                                (e) =>
                                    (form.color_hex = (
                                        e.target as HTMLInputElement
                                    ).value)
                            "
                        />
                        <Input
                            id="fin-color"
                            v-model="form.color_hex"
                            maxlength="7"
                            placeholder="#B07D4F"
                            class="w-32"
                        />
                    </div>
                    <InputError :message="form.errors.color_hex" />
                </div>
                <div class="grid gap-1.5">
                    <Label>Muestra (foto de la textura)</Label>
                    <MediaField
                        v-model="form.media_id"
                        :media="editing?.media ?? null"
                        collection="catalogo/acabados"
                        aspect="aspect-square max-w-40"
                    />
                </div>
                <div class="grid gap-1.5">
                    <Label for="fin-desc">Descripción</Label>
                    <Textarea
                        id="fin-desc"
                        v-model="form.description"
                        rows="3"
                        maxlength="1000"
                    />
                </div>
                <label
                    class="flex items-center justify-between gap-3 rounded-lg border border-border p-3 text-sm"
                >
                    Disponible <Switch v-model="form.is_active" />
                </label>
            </form>
            <template #footer>
                <Button variant="outline" @click="sheetOpen = false"
                    >Cancelar</Button
                >
                <Button
                    type="submit"
                    form="finish-form"
                    :disabled="form.processing"
                    ><Spinner v-if="form.processing" /> Guardar</Button
                >
            </template>
        </FormSheet>

        <ConfirmDialog
            v-model:open="removeOpen"
            :title="`¿Eliminar ${removing?.name ?? 'el acabado'}?`"
            description="Se quitará de todos los productos que lo usan."
            confirm-label="Eliminar"
            tone="destructive"
            :confirm="remove"
        />
    </div>
</template>
