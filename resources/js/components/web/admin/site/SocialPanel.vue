<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import {
    ArrowDown,
    ArrowUp,
    ExternalLink,
    Pencil,
    Plus,
    Share2,
    Trash2,
} from '@lucide/vue';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import ConfirmDialog from '@/components/ds/ConfirmDialog.vue';
import EmptyState from '@/components/ds/EmptyState.vue';
import FormSheet from '@/components/ds/FormSheet.vue';
import SectionCard from '@/components/ds/SectionCard.vue';
import StatusBadge from '@/components/ds/StatusBadge.vue';
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
import redes from '@/routes/admin/sitio/redes';

export type SocialLinkRow = {
    id: number;
    platform: string;
    platformLabel: string;
    username: string | null;
    url: string;
    isVisible: boolean;
    openNewTab: boolean;
    order: number;
};

const props = defineProps<{ links: SocialLinkRow[] }>();

const platforms = [
    { value: 'instagram', label: 'Instagram' },
    { value: 'facebook', label: 'Facebook' },
    { value: 'tiktok', label: 'TikTok' },
    { value: 'youtube', label: 'YouTube' },
    { value: 'pinterest', label: 'Pinterest' },
    { value: 'whatsapp', label: 'WhatsApp' },
    { value: 'linkedin', label: 'LinkedIn' },
    { value: 'otro', label: 'Otro sitio' },
];

const sheetOpen = ref(false);
const editing = ref<SocialLinkRow | null>(null);
const form = useForm({
    platform: 'instagram',
    username: '',
    url: '',
    is_visible: true,
    open_new_tab: true,
});

function create() {
    editing.value = null;
    form.reset();
    form.clearErrors();
    sheetOpen.value = true;
}

function edit(link: SocialLinkRow) {
    editing.value = link;
    form.platform = link.platform;
    form.username = link.username ?? '';
    form.url = link.url;
    form.is_visible = link.isVisible;
    form.open_new_tab = link.openNewTab;
    form.clearErrors();
    sheetOpen.value = true;
}

function submit() {
    const options = {
        preserveScroll: true,
        onSuccess: () => (sheetOpen.value = false),
    };
    if (editing.value) form.put(redes.update(editing.value.id).url, options);
    else form.post(redes.store().url, options);
}

function move(index: number, delta: number) {
    const ids = props.links.map((l) => l.id);
    const target = index + delta;
    if (target < 0 || target >= ids.length) return;
    [ids[index], ids[target]] = [ids[target], ids[index]];
    router.put(redes.orden().url, { ids }, { preserveScroll: true });
}

const removing = ref<SocialLinkRow | null>(null);
const removeOpen = ref(false);
function askRemove(link: SocialLinkRow) {
    removing.value = link;
    removeOpen.value = true;
}
function remove(): Promise<void> {
    return new Promise((resolve, reject) => {
        if (!removing.value) return resolve();
        router.delete(redes.destroy(removing.value.id).url, {
            preserveScroll: true,
            onSuccess: () => resolve(),
            onError: () => reject(new Error('No se pudo eliminar.')),
        });
    });
}
</script>

<template>
    <SectionCard
        title="Redes sociales"
        description="Aparecen en el pie de página y en los datos estructurados del sitio."
    >
        <template #actions>
            <Button size="sm" @click="create"
                ><Plus class="size-4" /> Agregar red</Button
            >
        </template>

        <ul v-if="props.links.length" class="divide-y divide-border">
            <li
                v-for="(link, index) in props.links"
                :key="link.id"
                class="flex flex-col gap-3 py-3 sm:flex-row sm:items-center"
            >
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2">
                        <span class="font-medium">{{
                            link.platformLabel
                        }}</span>
                        <StatusBadge
                            v-if="!link.isVisible"
                            tone="neutral"
                            label="Oculta"
                            size="sm"
                        />
                    </div>
                    <a
                        :href="link.url"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="inline-flex max-w-full items-center gap-1 truncate text-sm text-muted-foreground hover:text-foreground"
                    >
                        {{ link.username || link.url }}
                        <ExternalLink class="size-3" aria-hidden="true" />
                    </a>
                </div>
                <div class="flex justify-end gap-1">
                    <Button
                        variant="ghost"
                        size="icon"
                        class="size-8"
                        :disabled="index === 0"
                        aria-label="Subir"
                        @click="move(index, -1)"
                        ><ArrowUp class="size-4"
                    /></Button>
                    <Button
                        variant="ghost"
                        size="icon"
                        class="size-8"
                        :disabled="index === props.links.length - 1"
                        aria-label="Bajar"
                        @click="move(index, 1)"
                        ><ArrowDown class="size-4"
                    /></Button>
                    <Button
                        variant="ghost"
                        size="icon"
                        class="size-8"
                        :aria-label="`Editar ${link.platformLabel}`"
                        @click="edit(link)"
                        ><Pencil class="size-4"
                    /></Button>
                    <Button
                        variant="ghost"
                        size="icon"
                        class="size-8 text-danger-ink"
                        :aria-label="`Eliminar ${link.platformLabel}`"
                        @click="askRemove(link)"
                        ><Trash2 class="size-4"
                    /></Button>
                </div>
            </li>
        </ul>
        <EmptyState
            v-else
            compact
            :icon="Share2"
            title="Sin redes sociales"
            description="Agrega los perfiles oficiales de la empresa para mostrarlos en el sitio."
        />

        <FormSheet
            v-model:open="sheetOpen"
            :title="editing ? 'Editar red social' : 'Agregar red social'"
        >
            <form id="social-form" class="grid gap-4" @submit.prevent="submit">
                <div class="grid gap-1.5">
                    <Label for="social-platform">Plataforma</Label>
                    <Select v-model="form.platform">
                        <SelectTrigger id="social-platform" class="w-full"
                            ><SelectValue
                        /></SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="p in platforms"
                                :key="p.value"
                                :value="p.value"
                                >{{ p.label }}</SelectItem
                            >
                        </SelectContent>
                    </Select>
                    <InputError :message="form.errors.platform" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="social-url">Enlace del perfil</Label>
                    <Input
                        id="social-url"
                        v-model="form.url"
                        type="url"
                        placeholder="https://instagram.com/…"
                    />
                    <InputError :message="form.errors.url" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="social-username">Usuario (opcional)</Label>
                    <Input
                        id="social-username"
                        v-model="form.username"
                        placeholder="@usuario"
                    />
                    <InputError :message="form.errors.username" />
                </div>
                <label
                    class="flex items-center justify-between gap-3 rounded-lg border border-border p-3 text-sm"
                >
                    Mostrar en el sitio <Switch v-model="form.is_visible" />
                </label>
                <label
                    class="flex items-center justify-between gap-3 rounded-lg border border-border p-3 text-sm"
                >
                    Abrir en otra pestaña <Switch v-model="form.open_new_tab" />
                </label>
            </form>
            <template #footer>
                <Button variant="outline" @click="sheetOpen = false"
                    >Cancelar</Button
                >
                <Button
                    type="submit"
                    form="social-form"
                    :disabled="form.processing"
                    ><Spinner v-if="form.processing" /> Guardar</Button
                >
            </template>
        </FormSheet>

        <ConfirmDialog
            v-model:open="removeOpen"
            :title="`¿Eliminar ${removing?.platformLabel ?? 'esta red'}?`"
            description="Dejará de mostrarse en el sitio. Puedes volver a agregarla cuando quieras."
            confirm-label="Eliminar"
            tone="destructive"
            :confirm="remove"
        />
    </SectionCard>
</template>
