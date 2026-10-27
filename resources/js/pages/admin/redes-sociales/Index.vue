<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { ChevronDown, ChevronUp, ExternalLink, Pencil, Plus, Share2, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';
import EmptyState from '@/components/admin/EmptyState.vue';
import InputError from '@/components/InputError.vue';
import SocialIcon from '@/components/public/SocialIcon.vue';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import redesSociales from '@/routes/admin/redes-sociales';
import type { BreadcrumbItem } from '@/types';

interface SocialLinkRow {
    id: number;
    platform: string;
    username: string | null;
    url: string;
    is_visible: boolean;
    open_new_tab: boolean;
    order: number;
}

const props = defineProps<{
    links: SocialLinkRow[];
    can: { create: boolean };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Panel', href: '/admin' },
            { title: 'Redes sociales', href: redesSociales.index() },
        ] as BreadcrumbItem[],
    },
});

const platforms = [
    { value: 'instagram', label: 'Instagram', example: 'https://instagram.com/tu_usuario' },
    { value: 'tiktok', label: 'TikTok', example: 'https://tiktok.com/@tu_usuario' },
    { value: 'facebook', label: 'Facebook', example: 'https://facebook.com/tu_pagina' },
    { value: 'youtube', label: 'YouTube', example: 'https://youtube.com/@tu_canal' },
    { value: 'linkedin', label: 'LinkedIn', example: 'https://linkedin.com/in/tu_perfil' },
    { value: 'otro', label: 'Otra', example: 'https://…' },
];

function platformLabel(value: string): string {
    return platforms.find((p) => p.value === value)?.label ?? value;
}

const visibleCount = computed(() => props.links.filter((l) => l.is_visible).length);

/** Acciones de fila: 36 px con ratón, 44 px en pantallas táctiles. */
const rowAction = 'size-9 pointer-coarse:size-11';

// --- Crear / editar ---
const dialogOpen = ref(false);
const editingId = ref<number | null>(null);

const form = useForm({
    platform: 'instagram',
    username: '',
    url: '',
    is_visible: true,
    open_new_tab: true,
    order: 0,
});

const urlExample = computed(() => platforms.find((p) => p.value === form.platform)?.example ?? 'https://…');

function openCreate() {
    editingId.value = null;
    form.reset();
    form.clearErrors();
    form.order = props.links.length;
    dialogOpen.value = true;
}

function openEdit(link: SocialLinkRow) {
    editingId.value = link.id;
    form.platform = link.platform;
    form.username = link.username ?? '';
    form.url = link.url;
    form.is_visible = link.is_visible;
    form.open_new_tab = link.open_new_tab;
    form.order = link.order;
    form.clearErrors();
    dialogOpen.value = true;
}

function submit() {
    const options = { preserveScroll: true, onSuccess: () => (dialogOpen.value = false) };
    if (editingId.value) {
        form.put(redesSociales.update(editingId.value).url, options);
    } else {
        form.post(redesSociales.store().url, options);
    }
}

// --- Acciones en línea ---
function payload(link: SocialLinkRow) {
    return {
        platform: link.platform,
        username: link.username,
        url: link.url,
        is_visible: link.is_visible,
        open_new_tab: link.open_new_tab,
        order: link.order,
    };
}

function patch(link: SocialLinkRow, changes: Partial<SocialLinkRow>) {
    router.put(redesSociales.update(link.id).url, { ...payload(link), ...changes }, { preserveScroll: true });
}

const reordering = ref(false);

/**
 * Mueve una red arriba/abajo y guarda el nuevo orden (0..n) solo en las
 * filas cuyo valor cambia, una petición tras otra.
 */
function move(index: number, delta: -1 | 1) {
    const target = index + delta;
    if (target < 0 || target >= props.links.length || reordering.value) return;

    const list = [...props.links];
    [list[index], list[target]] = [list[target], list[index]];
    const changed = list.map((link, order) => ({ link, order })).filter(({ link, order }) => link.order !== order);

    reordering.value = true;
    const next = (k: number) => {
        if (k >= changed.length) {
            reordering.value = false;

            return;
        }
        const { link, order } = changed[k];
        router.put(
            redesSociales.update(link.id).url,
            { ...payload(link), order },
            { preserveScroll: true, onSuccess: () => next(k + 1), onError: () => (reordering.value = false), onCancel: () => (reordering.value = false) },
        );
    };
    next(0);
}

const toDelete = ref<SocialLinkRow | null>(null);

function destroy() {
    if (!toDelete.value) return;
    router.delete(redesSociales.destroy(toDelete.value.id).url, { preserveScroll: true });
    toDelete.value = null;
}
</script>

<template>
    <Head title="Redes sociales" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6 lg:p-8">
        <AdminPageHeader
            eyebrow="Sitio web"
            title="Redes sociales"
            description="Los íconos del encabezado, del pie y de los bloques de redes en Studio. Solo las redes visibles aparecen en público; usa siempre las cuentas reales."
        >
            <template v-if="links.length" #meta>
                <p class="mt-2 text-xs text-muted-foreground">{{ visibleCount }} de {{ links.length }} visibles en el sitio</p>
            </template>
            <template #actions>
                <Button v-if="can.create" @click="openCreate"><Plus class="size-4" /> Agregar red</Button>
            </template>
        </AdminPageHeader>

        <EmptyState
            v-if="links.length === 0"
            :icon="Share2"
            title="Aún no hay redes sociales"
            description="Agrega tus perfiles para que la gente te encuentre. Ej.: Instagram con https://instagram.com/tu_usuario."
        >
            <Button v-if="can.create" @click="openCreate"><Plus class="size-4" /> Agregar la primera</Button>
        </EmptyState>

        <div v-else class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_280px]">
            <ol class="flex flex-col divide-y overflow-hidden rounded-xl border bg-card" aria-label="Redes sociales en orden de aparición" :aria-busy="reordering">
                <li
                    v-for="(link, index) in links"
                    :key="link.id"
                    class="flex flex-col gap-3 p-4 sm:flex-row sm:items-center"
                    :class="!link.is_visible && 'bg-muted/30'"
                >
                    <div class="flex min-w-0 flex-1 items-center gap-3">
                        <div class="flex flex-col" role="group" :aria-label="`Mover ${platformLabel(link.platform)}`">
                            <Button
                                variant="ghost"
                                size="icon"
                                class="h-6 w-9 pointer-coarse:h-8 pointer-coarse:w-11"
                                :disabled="index === 0 || reordering"
                                :aria-label="`Subir ${platformLabel(link.platform)}`"
                                @click="move(index, -1)"
                            >
                                <ChevronUp class="size-4" />
                            </Button>
                            <Button
                                variant="ghost"
                                size="icon"
                                class="h-6 w-9 pointer-coarse:h-8 pointer-coarse:w-11"
                                :disabled="index === links.length - 1 || reordering"
                                :aria-label="`Bajar ${platformLabel(link.platform)}`"
                                @click="move(index, 1)"
                            >
                                <ChevronDown class="size-4" />
                            </Button>
                        </div>
                        <span class="grid size-11 shrink-0 place-items-center rounded-full bg-muted text-foreground" :class="!link.is_visible && 'opacity-50'">
                            <SocialIcon :platform="link.platform" />
                        </span>
                        <div class="min-w-0">
                            <p class="flex items-center gap-2 font-medium">
                                {{ platformLabel(link.platform) }}
                                <span v-if="link.username" class="truncate text-sm font-normal text-muted-foreground">{{ link.username }}</span>
                            </p>
                            <a
                                :href="link.url"
                                target="_blank"
                                rel="noopener"
                                class="inline-flex max-w-full items-center gap-1 truncate text-xs text-muted-foreground hover:text-foreground hover:underline"
                            >
                                <span class="truncate">{{ link.url }}</span>
                                <ExternalLink class="size-3 shrink-0" aria-hidden="true" />
                            </a>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center justify-end gap-1">
                        <label :for="`visible-${link.id}`" class="flex min-h-11 cursor-pointer items-center gap-2 rounded-md px-2 text-xs text-muted-foreground">
                            <Switch :id="`visible-${link.id}`" :model-value="link.is_visible" @update:model-value="(v: boolean) => patch(link, { is_visible: v })" />
                            Visible
                        </label>
                        <label :for="`newtab-${link.id}`" class="flex min-h-11 cursor-pointer items-center gap-2 rounded-md px-2 text-xs text-muted-foreground">
                            <Switch :id="`newtab-${link.id}`" :model-value="link.open_new_tab" @update:model-value="(v: boolean) => patch(link, { open_new_tab: v })" />
                            Nueva pestaña
                        </label>
                        <Button variant="ghost" size="icon" :class="rowAction" :aria-label="`Editar ${platformLabel(link.platform)}`" @click="openEdit(link)">
                            <Pencil class="size-4" />
                        </Button>
                        <Button variant="ghost" size="icon" :class="rowAction" :aria-label="`Eliminar ${platformLabel(link.platform)}`" @click="toDelete = link">
                            <Trash2 class="size-4" />
                        </Button>
                    </div>
                </li>
            </ol>

            <!-- Vista previa de cómo se ven en el sitio -->
            <aside class="flex flex-col gap-3 self-start rounded-xl border bg-card p-5 lg:sticky lg:top-6">
                <h2 class="text-sm font-semibold">Así aparecen en el sitio</h2>
                <p class="text-xs text-muted-foreground">En este orden, en el encabezado y el pie de página. Las ocultas no se muestran.</p>
                <div class="flex min-h-14 flex-wrap items-center gap-2 rounded-lg bg-ink p-3 text-paper">
                    <template v-for="link in links" :key="link.id">
                        <span v-if="link.is_visible" class="grid size-9 place-items-center rounded-full border border-line" :title="platformLabel(link.platform)">
                            <SocialIcon :platform="link.platform" />
                        </span>
                    </template>
                    <span v-if="visibleCount === 0" class="text-xs text-mist">Ninguna red visible</span>
                </div>
            </aside>
        </div>
    </div>

    <Dialog v-model:open="dialogOpen">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>{{ editingId ? 'Editar red social' : 'Agregar red social' }}</DialogTitle>
                <DialogDescription>Copia la dirección completa de tu perfil desde el navegador.</DialogDescription>
            </DialogHeader>
            <form class="grid gap-4" @submit.prevent="submit">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="platform">Plataforma</Label>
                        <Select v-model="form.platform">
                            <SelectTrigger id="platform" class="w-full"><SelectValue /></SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="p in platforms" :key="p.value" :value="p.value">{{ p.label }}</SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="form.errors.platform" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="username">Usuario <span class="font-normal text-muted-foreground">(opcional)</span></Label>
                        <Input id="username" v-model="form.username" maxlength="100" placeholder="@mrinsight" />
                        <InputError :message="form.errors.username" />
                    </div>
                </div>
                <div class="grid gap-2">
                    <Label for="url">Dirección completa</Label>
                    <Input id="url" v-model="form.url" type="url" required maxlength="500" :placeholder="urlExample" :aria-invalid="!!form.errors.url" />
                    <p class="text-xs text-muted-foreground">Debe empezar con https://. Ej.: {{ urlExample }}</p>
                    <InputError :message="form.errors.url" />
                </div>
                <div class="grid gap-1 rounded-lg border p-2">
                    <label for="form-visible" class="flex min-h-11 cursor-pointer items-center gap-3 px-2 text-sm">
                        <Switch id="form-visible" :model-value="form.is_visible" @update:model-value="(v: boolean) => (form.is_visible = v)" />
                        Visible en el sitio público
                    </label>
                    <label for="form-newtab" class="flex min-h-11 cursor-pointer items-center gap-3 px-2 text-sm">
                        <Switch id="form-newtab" :model-value="form.open_new_tab" @update:model-value="(v: boolean) => (form.open_new_tab = v)" />
                        Abrir en una pestaña nueva
                    </label>
                </div>
                <DialogFooter>
                    <Button type="button" variant="ghost" @click="dialogOpen = false">Cancelar</Button>
                    <Button type="submit" :disabled="form.processing" :aria-busy="form.processing">{{ editingId ? 'Guardar cambios' : 'Agregar red' }}</Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <AlertDialog :open="toDelete !== null" @update:open="(v: boolean) => !v && (toDelete = null)">
        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle>¿Eliminar {{ toDelete ? platformLabel(toDelete.platform) : '' }}?</AlertDialogTitle>
                <AlertDialogDescription>El ícono dejará de aparecer en el sitio. Si solo quieres ocultarla, desactiva «Visible».</AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
                <AlertDialogCancel>Cancelar</AlertDialogCancel>
                <AlertDialogAction class="bg-destructive text-white hover:bg-destructive/90" @click="destroy">Eliminar</AlertDialogAction>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>
</template>
