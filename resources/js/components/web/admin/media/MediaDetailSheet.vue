<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import {
    Copy,
    ExternalLink,
    FileText,
    RefreshCw,
    Trash2,
    TriangleAlert,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import InputError from '@/components/InputError.vue';
import ConfirmDialog from '@/components/ds/ConfirmDialog.vue';
import DateText from '@/components/ds/DateText.vue';
import FormSheet from '@/components/ds/FormSheet.vue';
import KeyValue from '@/components/ds/KeyValue.vue';
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
import multimedia from '@/routes/admin/multimedia';
import type { MediaItem } from '@/types/web';
import { formatBytes } from './format';

/**
 * Detalle de un archivo: vista previa, metadatos (título, texto
 * alternativo, descripción, origen), reemplazo del archivo conservando sus
 * vínculos y borrado seguro (bloqueado si se usa en algún lugar).
 */
const open = defineModel<boolean>('open', { default: false });
const props = defineProps<{ media: MediaItem | null; accept: string }>();
const emit = defineEmits<{ changed: [] }>();

type Usage = { type: string; label: string; href: string | null };

const title = ref('');
const altText = ref('');
const description = ref('');
const origin = ref<string>('none');
const saving = ref(false);
const errors = ref<Record<string, string>>({});
const usages = ref<Usage[] | null>(null);
const deleteOpen = ref(false);
const replacing = ref(false);
const replaceInput = ref<HTMLInputElement | null>(null);

watch(
    () => props.media,
    (m) => {
        title.value = m?.title ?? '';
        altText.value = m?.altText ?? '';
        description.value = m?.description ?? '';
        origin.value = m?.origin ?? 'none';
        errors.value = {};
        usages.value = null;
    },
    { immediate: true },
);

const isImage = computed(() => props.media?.type === 'image');
const isVideo = computed(() => props.media?.type === 'video');

async function save() {
    if (!props.media) return;
    saving.value = true;
    errors.value = {};
    try {
        const response = await apiFetch(multimedia.update(props.media.id).url, {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                title: title.value || null,
                alt_text: altText.value || null,
                description: description.value || null,
                origin: origin.value === 'none' ? null : origin.value,
            }),
        });
        const body = await response.json().catch(() => ({}));
        if (response.status === 422) {
            errors.value = Object.fromEntries(
                Object.entries(body.errors ?? {}).map(([k, v]) => [
                    k,
                    (v as string[])[0],
                ]),
            );

            return;
        }
        if (!response.ok) throw new Error();
        toast.success('Datos del archivo guardados.');
        emit('changed');
    } catch {
        toast.error('No se pudieron guardar los cambios.');
    } finally {
        saving.value = false;
    }
}

async function loadUsages() {
    if (!props.media) return;
    const response = await apiFetch(multimedia.usos(props.media.id).url);
    if (response.ok)
        usages.value = ((await response.json()) as { usages: Usage[] }).usages;
}

async function onReplace(event: Event) {
    const file = (event.target as HTMLInputElement).files?.[0];
    if (!file || !props.media) return;
    replacing.value = true;
    try {
        const data = new FormData();
        data.append('file', file);
        const response = await apiFetch(
            multimedia.reemplazar(props.media.id).url,
            { method: 'POST', body: data },
        );
        const body = await response.json().catch(() => ({}));
        if (!response.ok) {
            toast.error(body.message ?? 'No se pudo reemplazar el archivo.');

            return;
        }
        toast.success(
            'Archivo reemplazado. Se conservaron sus vínculos y datos.',
        );
        emit('changed');
    } finally {
        replacing.value = false;
        if (replaceInput.value) replaceInput.value.value = '';
    }
}

async function destroy(): Promise<void> {
    if (!props.media) return;
    const response = await apiFetch(multimedia.destroy(props.media.id).url, {
        method: 'DELETE',
    });
    if (response.status === 409) {
        usages.value = ((await response.json()) as { usages: Usage[] }).usages;
        throw new Error(
            'Este archivo se está usando. Quítalo de esos lugares antes de eliminarlo.',
        );
    }
    if (!response.ok) throw new Error('No se pudo eliminar el archivo.');
    toast.success('Archivo eliminado.');
    open.value = false;
    router.reload({ only: ['media', 'stats', 'collections'] });
}

function copyUrl() {
    if (!props.media?.url) return;
    void navigator.clipboard.writeText(
        new URL(props.media.url, window.location.origin).toString(),
    );
    toast.success('Enlace copiado.');
}

const facts = computed(() => {
    const m = props.media;
    if (!m) return [];

    return [
        { key: 'tipo', label: 'Tipo', value: m.mimeType },
        { key: 'tamano', label: 'Tamaño', value: formatBytes(m.size) },
        ...(m.width && m.height
            ? [
                  {
                      key: 'dim',
                      label: 'Dimensiones',
                      value: `${m.width} × ${m.height} px`,
                  },
              ]
            : []),
        {
            key: 'coleccion',
            label: 'Colección',
            value: m.collection?.path ?? '—',
        },
        {
            key: 'visibilidad',
            label: 'Visibilidad',
            value: m.visibility === 'private' ? 'Privado' : 'Público',
        },
        {
            key: 'variantes',
            label: 'Variantes',
            value: m.variants.length
                ? `${m.variants.length} (WebP${m.variants.some((v) => v.type.startsWith('avif')) ? ' y AVIF' : ''})`
                : 'Ninguna',
        },
    ];
});
</script>

<template>
    <FormSheet
        v-model:open="open"
        :title="props.media?.filename ?? 'Archivo'"
        size="xl"
    >
        <div v-if="props.media" class="grid gap-6">
            <div class="overflow-hidden rounded-lg bg-muted">
                <img
                    v-if="isImage && props.media.url"
                    :src="
                        props.media.variants.length
                            ? (props.media.thumbnailUrl ?? props.media.url)
                            : props.media.url
                    "
                    :alt="props.media.altText ?? ''"
                    class="mx-auto max-h-80 object-contain"
                />
                <video
                    v-else-if="isVideo && props.media.url"
                    :src="props.media.url"
                    :poster="props.media.posterUrl ?? undefined"
                    controls
                    preload="metadata"
                    class="max-h-80 w-full"
                />
                <div
                    v-else
                    class="grid h-40 place-items-center text-muted-foreground"
                >
                    <FileText class="size-8" />
                </div>
            </div>

            <div class="flex flex-wrap gap-2">
                <Button
                    v-if="props.media.url"
                    as="a"
                    :href="props.media.url"
                    target="_blank"
                    rel="noopener"
                    variant="outline"
                    size="sm"
                    ><ExternalLink class="size-4" /> Abrir</Button
                >
                <Button
                    v-if="
                        props.media.url && props.media.visibility === 'public'
                    "
                    variant="outline"
                    size="sm"
                    @click="copyUrl"
                    ><Copy class="size-4" /> Copiar enlace</Button
                >
                <Button
                    variant="outline"
                    size="sm"
                    :disabled="replacing"
                    @click="replaceInput?.click()"
                >
                    <Spinner v-if="replacing" /><RefreshCw
                        v-else
                        class="size-4"
                    />
                    Reemplazar archivo
                </Button>
                <input
                    ref="replaceInput"
                    type="file"
                    class="hidden"
                    :accept="props.accept"
                    @change="onReplace"
                />
                <Button variant="outline" size="sm" @click="loadUsages"
                    >¿Dónde se usa?</Button
                >
            </div>

            <div
                v-if="usages"
                class="rounded-lg border border-border p-3 text-sm"
            >
                <p v-if="!usages.length" class="text-muted-foreground">
                    Este archivo no se usa en ningún lugar.
                </p>
                <template v-else>
                    <p class="mb-2 flex items-center gap-2 font-medium">
                        <TriangleAlert class="size-4 text-warning" /> Se usa en
                        {{ usages.length }}
                        {{ usages.length === 1 ? 'lugar' : 'lugares' }}:
                    </p>
                    <ul class="grid gap-1">
                        <li v-for="(u, i) in usages" :key="i">
                            <span class="text-muted-foreground"
                                >{{ u.type }}:</span
                            >
                            <a
                                v-if="u.href"
                                :href="u.href"
                                class="ml-1 text-primary underline-offset-4 hover:underline"
                                >{{ u.label }}</a
                            >
                            <span v-else class="ml-1">{{ u.label }}</span>
                        </li>
                    </ul>
                </template>
            </div>

            <form id="media-form" class="grid gap-4" @submit.prevent="save">
                <div class="grid gap-1.5">
                    <Label for="m-alt">Texto alternativo</Label>
                    <Input
                        id="m-alt"
                        v-model="altText"
                        maxlength="255"
                        placeholder="Describe la imagen para personas con lector de pantalla"
                    />
                    <InputError :message="errors.alt_text" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="m-title">Título</Label>
                    <Input id="m-title" v-model="title" maxlength="255" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="m-origin">Origen</Label>
                    <Select v-model="origin">
                        <SelectTrigger id="m-origin" class="w-full"
                            ><SelectValue
                        /></SelectTrigger>
                        <SelectContent>
                            <SelectItem value="none">Sin indicar</SelectItem>
                            <SelectItem value="render"
                                >Render conceptual</SelectItem
                            >
                            <SelectItem value="real_photo"
                                >Proyecto real</SelectItem
                            >
                        </SelectContent>
                    </Select>
                    <p class="text-xs text-muted-foreground">
                        Se muestra como etiqueta en el sitio cuando la imagen
                        aparece en el catálogo o en inspiración.
                    </p>
                </div>
                <div class="grid gap-1.5">
                    <Label for="m-desc">Descripción interna</Label>
                    <Textarea
                        id="m-desc"
                        v-model="description"
                        rows="3"
                        maxlength="2000"
                    />
                </div>
            </form>

            <KeyValue :items="facts" />
            <p class="text-xs text-muted-foreground">
                Subido <DateText :value="props.media.createdAt" relative />
            </p>
        </div>

        <template #footer>
            <Button
                type="button"
                variant="ghost"
                class="mr-auto text-danger-ink"
                @click="deleteOpen = true"
                ><Trash2 class="size-4" /> Eliminar</Button
            >
            <Button type="submit" form="media-form" :disabled="saving"
                ><Spinner v-if="saving" /> Guardar</Button
            >
        </template>

        <ConfirmDialog
            v-model:open="deleteOpen"
            title="¿Eliminar este archivo?"
            description="Se borra de forma permanente junto con sus versiones optimizadas. Si se está usando, no se podrá eliminar."
            confirm-label="Eliminar"
            loading-label="Eliminando…"
            tone="destructive"
            :confirm="destroy"
        />
    </FormSheet>
</template>
