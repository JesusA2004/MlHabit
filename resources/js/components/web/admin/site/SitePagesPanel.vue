<script setup lang="ts">
import { Link, router, useForm } from '@inertiajs/vue3';
import {
    Copy,
    Ellipsis,
    ExternalLink,
    Eye,
    EyeOff,
    FilePlus2,
    LayoutTemplate,
    PanelsTopLeft,
    Plus,
    Settings2,
    Trash2,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import ConfirmDialog from '@/components/ds/ConfirmDialog.vue';
import DataView from '@/components/ds/DataView.vue';
import DateText from '@/components/ds/DateText.vue';
import EmptyState from '@/components/ds/EmptyState.vue';
import FormSheet from '@/components/ds/FormSheet.vue';
import SectionCard from '@/components/ds/SectionCard.vue';
import StatusBadge from '@/components/ds/StatusBadge.vue';
import type { DataColumn } from '@/components/ds/types';
import { templateIcon } from '@/components/site-builder/editor/templateIcons';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';
import paginasWeb from '@/routes/admin/sitio/paginas-web';
import type { MediaRef } from '@/types/web';
import MediaField from '../MediaField.vue';

export type SitePageRow = {
    id: number;
    title: string;
    slug: string;
    path: string;
    publicUrl: string;
    seoTitle: string | null;
    seoDescription: string | null;
    ogMediaId: number | null;
    template: string | null;
    isPublished: boolean;
    publishedAt: string | null;
    lastVersion: number | null;
    hasChanges: boolean;
    sections: number;
    updatedAt: string | null;
    updatedBy: string | null;
    editorUrl: string;
};

export type PageTemplateOption = {
    id: string;
    label: string;
    description: string;
    icon: string;
    sections: string[];
};

const props = defineProps<{
    pages: SitePageRow[];
    templates: PageTemplateOption[];
    media: Record<string, MediaRef | null>;
    canPublish: boolean;
    canCreate: boolean;
}>();

const origin = typeof window !== 'undefined' ? window.location.origin : '';

const columns: DataColumn<SitePageRow>[] = [
    { key: 'title', label: 'Página', primary: true },
    { key: 'status', label: 'Estado' },
    { key: 'sections', label: 'Secciones', align: 'right', hideOnMobile: true },
    { key: 'updatedAt', label: 'Última edición' },
];

function status(row: SitePageRow) {
    if (!row.isPublished)
        return { tone: 'neutral' as const, label: 'Borrador' };
    if (row.hasChanges)
        return { tone: 'warning' as const, label: 'Cambios sin publicar' };

    return { tone: 'success' as const, label: 'Publicada' };
}

// ----- Nueva página -----------------------------------------------------
const createOpen = ref(false);
const createForm = useForm({
    title: '',
    slug: '',
    template: 'blank',
});
const slugPreview = computed(() =>
    (createForm.slug || createForm.title)
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '')
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '')
        .slice(0, 120),
);

function openCreate() {
    createForm.reset();
    createForm.clearErrors();
    createOpen.value = true;
}

function submitCreate() {
    createForm
        .transform((data) => ({ ...data, slug: data.slug || null }))
        .post(paginasWeb.store().url, {
            preserveScroll: true,
            onSuccess: () => (createOpen.value = false),
        });
}

// ----- Ajustes y SEO ----------------------------------------------------
const settingsOpen = ref(false);
const editing = ref<SitePageRow | null>(null);
const settingsForm = useForm({
    title: '',
    slug: '',
    seo_title: '',
    seo_description: '',
    og_media_id: null as number | null,
});
const ogMedia = ref<MediaRef | null>(null);

function openSettings(row: SitePageRow) {
    editing.value = row;
    settingsForm.title = row.title;
    settingsForm.slug = row.slug;
    settingsForm.seo_title = row.seoTitle ?? '';
    settingsForm.seo_description = row.seoDescription ?? '';
    settingsForm.og_media_id = row.ogMediaId;
    ogMedia.value =
        row.ogMediaId !== null
            ? (props.media[String(row.ogMediaId)] ?? null)
            : null;
    settingsForm.clearErrors();
    settingsOpen.value = true;
}

function submitSettings() {
    if (!editing.value) return;
    settingsForm
        .transform((data) => ({
            ...data,
            seo_title: data.seo_title || null,
            seo_description: data.seo_description || null,
        }))
        .put(paginasWeb.update(editing.value.id).url, {
            preserveScroll: true,
            onSuccess: () => (settingsOpen.value = false),
        });
}

// ----- Acciones ---------------------------------------------------------
function duplicate(row: SitePageRow) {
    router.post(paginasWeb.duplicar(row.id).url, {}, { preserveScroll: true });
}

function togglePublish(row: SitePageRow) {
    router.post(
        row.isPublished
            ? paginasWeb.despublicar(row.id).url
            : paginasWeb.publicar(row.id).url,
        {},
        { preserveScroll: true },
    );
}

const removing = ref<SitePageRow | null>(null);
const removeOpen = ref(false);
function askRemove(row: SitePageRow) {
    removing.value = row;
    removeOpen.value = true;
}
function remove(): Promise<void> {
    return new Promise((resolve, reject) => {
        if (!removing.value) return resolve();
        router.delete(paginasWeb.destroy(removing.value.id).url, {
            preserveScroll: true,
            onSuccess: () => resolve(),
            onError: () => reject(new Error('No se pudo eliminar.')),
        });
    });
}
</script>

<template>
    <SectionCard
        title="Páginas del sitio"
        description="Crea páginas propias (servicios, proyectos, nosotros, campañas) con el mismo editor visual de la portada. Cada una empieza con el estilo de ML Habitat y se publica en su propia dirección."
    >
        <template #actions>
            <Button v-if="props.canCreate" size="sm" @click="openCreate">
                <Plus class="size-4" /> Nueva página
            </Button>
        </template>

        <DataView
            v-if="props.pages.length"
            :columns="columns"
            :rows="props.pages"
            :row-href="(row: SitePageRow) => row.editorUrl"
        >
            <template #cell-title="{ row }">
                <span class="flex items-center gap-2 font-medium"
                    ><PanelsTopLeft
                        class="size-4 text-muted-foreground"
                        aria-hidden="true"
                    />{{ row.title }}</span
                >
                <span class="block text-xs text-muted-foreground">{{
                    row.path
                }}</span>
            </template>
            <template #cell-status="{ row }">
                <StatusBadge
                    :tone="status(row).tone"
                    :label="status(row).label"
                    size="sm"
                />
            </template>
            <template #cell-sections="{ row }">{{ row.sections }}</template>
            <template #cell-updatedAt="{ row }">
                <DateText :value="row.updatedAt" relative />
                <span
                    v-if="row.updatedBy"
                    class="block text-xs text-muted-foreground"
                    >{{ row.updatedBy }}</span
                >
            </template>
            <template #actions="{ row }">
                <div class="flex items-center justify-end gap-1">
                    <Button as-child variant="outline" size="sm">
                        <Link :href="row.editorUrl">Editar</Link>
                    </Button>
                    <DropdownMenu>
                        <DropdownMenuTrigger as-child>
                            <Button
                                variant="ghost"
                                size="icon"
                                class="size-8"
                                :aria-label="`Más acciones de ${row.title}`"
                            >
                                <Ellipsis class="size-4" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" class="w-56">
                            <DropdownMenuItem @select="openSettings(row)">
                                <Settings2 class="size-4" /> Datos y SEO
                            </DropdownMenuItem>
                            <DropdownMenuItem as-child>
                                <a
                                    :href="row.publicUrl"
                                    target="_blank"
                                    rel="noopener"
                                >
                                    <ExternalLink class="size-4" />
                                    {{
                                        row.isPublished
                                            ? 'Ver en el sitio'
                                            : 'Ver vista previa'
                                    }}
                                </a>
                            </DropdownMenuItem>
                            <DropdownMenuItem
                                v-if="props.canCreate"
                                @select="duplicate(row)"
                            >
                                <Copy class="size-4" /> Duplicar
                            </DropdownMenuItem>
                            <DropdownMenuItem
                                v-if="props.canPublish"
                                @select="togglePublish(row)"
                            >
                                <component
                                    :is="row.isPublished ? EyeOff : Eye"
                                    class="size-4"
                                />
                                {{
                                    row.isPublished ? 'Despublicar' : 'Publicar'
                                }}
                            </DropdownMenuItem>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem
                                class="text-danger-ink focus:text-danger-ink"
                                :disabled="row.isPublished"
                                @select="askRemove(row)"
                            >
                                <Trash2 class="size-4" />
                                {{
                                    row.isPublished
                                        ? 'Despublica para eliminar'
                                        : 'Eliminar'
                                }}
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
            </template>
        </DataView>
        <EmptyState
            v-else
            compact
            :icon="FilePlus2"
            title="Aún no tienes páginas propias"
            description="Crea una página de servicio, la galería de un proyecto o una página «Nosotros». Elige una plantilla y personalízala en el editor."
        >
            <template v-if="props.canCreate" #action>
                <Button size="sm" @click="openCreate"
                    ><Plus class="size-4" /> Nueva página</Button
                >
            </template>
        </EmptyState>

        <!-- Nueva página -->
        <FormSheet
            v-model:open="createOpen"
            title="Nueva página"
            description="Elige con qué empezar. Todo se puede cambiar después en el editor visual."
            size="lg"
        >
            <form
                id="site-page-create"
                class="grid gap-5"
                @submit.prevent="submitCreate"
            >
                <div class="grid gap-1.5">
                    <Label for="sp-title">Título de la página</Label>
                    <Input
                        id="sp-title"
                        v-model="createForm.title"
                        maxlength="160"
                        placeholder="Ej. Cocinas integrales"
                        required
                    />
                    <InputError :message="createForm.errors.title" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="sp-slug">Dirección (opcional)</Label>
                    <div
                        class="flex items-center rounded-md border border-input bg-background focus-within:ring-[3px] focus-within:ring-ring/40"
                    >
                        <span
                            class="pl-3 text-sm whitespace-nowrap text-muted-foreground"
                            >{{ origin }}/</span
                        >
                        <input
                            id="sp-slug"
                            v-model="createForm.slug"
                            class="h-9 min-w-0 flex-1 bg-transparent pr-3 text-sm outline-none"
                            maxlength="120"
                            :placeholder="slugPreview || 'cocinas-integrales'"
                            aria-describedby="sp-slug-help"
                        />
                    </div>
                    <p id="sp-slug-help" class="text-xs text-muted-foreground">
                        Solo minúsculas, números y guiones. Si la dejas vacía se
                        arma con el título.
                    </p>
                    <InputError :message="createForm.errors.slug" />
                </div>

                <fieldset class="grid gap-2">
                    <legend class="mb-1 text-sm font-medium">Plantilla</legend>
                    <div class="grid gap-2 sm:grid-cols-2" role="radiogroup">
                        <label
                            v-for="t in props.templates"
                            :key="t.id"
                            :class="
                                cn(
                                    'group relative flex cursor-pointer flex-col gap-2 rounded-xl border p-3 transition-[border-color,box-shadow,background-color] has-focus-visible:ring-[3px] has-focus-visible:ring-ring/40',
                                    createForm.template === t.id
                                        ? 'border-primary bg-primary-soft/60 shadow-xs'
                                        : 'border-border hover:border-primary/50',
                                )
                            "
                        >
                            <input
                                v-model="createForm.template"
                                type="radio"
                                name="template"
                                :value="t.id"
                                class="sr-only"
                            />
                            <span class="flex items-center gap-2.5">
                                <span
                                    :class="
                                        cn(
                                            'grid size-9 place-items-center rounded-lg transition-colors',
                                            createForm.template === t.id
                                                ? 'bg-primary text-primary-foreground'
                                                : 'bg-muted text-foreground',
                                        )
                                    "
                                    aria-hidden="true"
                                >
                                    <component
                                        :is="templateIcon(t.icon)"
                                        class="size-4"
                                    />
                                </span>
                                <span class="text-sm font-semibold">{{
                                    t.label
                                }}</span>
                            </span>
                            <span class="text-xs text-muted-foreground">{{
                                t.description
                            }}</span>
                            <span class="flex flex-wrap gap-1">
                                <span
                                    v-for="s in t.sections"
                                    :key="s"
                                    class="rounded-full bg-background px-2 py-0.5 text-[11px] text-muted-foreground ring-1 ring-border"
                                    >{{ s }}</span
                                >
                            </span>
                        </label>
                    </div>
                    <InputError :message="createForm.errors.template" />
                </fieldset>
            </form>
            <template #footer>
                <Button variant="outline" @click="createOpen = false"
                    >Cancelar</Button
                >
                <Button
                    type="submit"
                    form="site-page-create"
                    :disabled="createForm.processing"
                >
                    <Spinner v-if="createForm.processing" />
                    <LayoutTemplate v-else class="size-4" />
                    {{
                        createForm.processing
                            ? 'Creando…'
                            : 'Crear y abrir el editor'
                    }}
                </Button>
            </template>
        </FormSheet>

        <!-- Datos y SEO -->
        <FormSheet
            v-model:open="settingsOpen"
            :title="
                editing ? `Datos de «${editing.title}»` : 'Datos de la página'
            "
            description="Título, dirección y cómo se ve la página en buscadores y al compartirla."
            size="lg"
        >
            <form
                id="site-page-settings"
                class="grid gap-4"
                @submit.prevent="submitSettings"
            >
                <div class="grid gap-1.5">
                    <Label for="sps-title">Título de la página</Label>
                    <Input
                        id="sps-title"
                        v-model="settingsForm.title"
                        maxlength="160"
                        required
                    />
                    <p class="text-xs text-muted-foreground">
                        Se usa en el panel, en el menú y como título en
                        buscadores si no escribes otro.
                    </p>
                    <InputError :message="settingsForm.errors.title" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="sps-slug">Dirección</Label>
                    <div
                        class="flex items-center rounded-md border border-input bg-background focus-within:ring-[3px] focus-within:ring-ring/40"
                    >
                        <span
                            class="pl-3 text-sm whitespace-nowrap text-muted-foreground"
                            >{{ origin }}/</span
                        >
                        <input
                            id="sps-slug"
                            v-model="settingsForm.slug"
                            class="h-9 min-w-0 flex-1 bg-transparent pr-3 text-sm outline-none"
                            maxlength="120"
                            required
                        />
                    </div>
                    <p
                        v-if="editing?.isPublished"
                        class="text-xs text-warning-ink"
                    >
                        La página ya está publicada: si cambias la dirección,
                        los enlaces anteriores dejarán de funcionar.
                    </p>
                    <InputError :message="settingsForm.errors.slug" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="sps-seo-title">Título para buscadores</Label>
                    <Input
                        id="sps-seo-title"
                        v-model="settingsForm.seo_title"
                        maxlength="160"
                        :placeholder="settingsForm.title"
                    />
                    <p class="text-xs text-muted-foreground tabular-nums">
                        {{ settingsForm.seo_title.length }}/60 recomendados
                    </p>
                    <InputError :message="settingsForm.errors.seo_title" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="sps-seo-desc"
                        >Descripción para buscadores</Label
                    >
                    <Textarea
                        id="sps-seo-desc"
                        v-model="settingsForm.seo_description"
                        maxlength="320"
                        rows="3"
                        placeholder="Explica en una o dos frases qué encontrará el visitante."
                    />
                    <p class="text-xs text-muted-foreground tabular-nums">
                        {{ settingsForm.seo_description.length }}/160
                        recomendados
                    </p>
                    <InputError
                        :message="settingsForm.errors.seo_description"
                    />
                </div>
                <div class="grid gap-1.5">
                    <Label>Imagen al compartir (1200 × 630)</Label>
                    <MediaField
                        v-model="settingsForm.og_media_id"
                        :media="ogMedia"
                        collection="sitio/paginas"
                        aspect="aspect-[1200/630]"
                        @change="(m) => (ogMedia = m)"
                    />
                    <p class="text-xs text-muted-foreground">
                        Si no eliges una, se genera con el título y la marca.
                    </p>
                    <InputError :message="settingsForm.errors.og_media_id" />
                </div>
            </form>
            <template #footer>
                <Button variant="outline" @click="settingsOpen = false"
                    >Cancelar</Button
                >
                <Button
                    type="submit"
                    form="site-page-settings"
                    :disabled="settingsForm.processing"
                    ><Spinner v-if="settingsForm.processing" /> Guardar
                    cambios</Button
                >
            </template>
        </FormSheet>

        <ConfirmDialog
            v-model:open="removeOpen"
            :title="`¿Eliminar «${removing?.title ?? 'esta página'}»?`"
            description="Se borran su contenido, el borrador y el historial de versiones. Los enlaces del menú hacia ella se ocultan. No se puede deshacer."
            confirm-label="Eliminar página"
            loading-label="Eliminando…"
            tone="destructive"
            :confirm="remove"
        />
    </SectionCard>
</template>
