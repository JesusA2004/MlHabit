<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Eye, FileText, Lock, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import type { UploadedMediaResult } from '@/composables/useChunkedUpload';
import ConfirmDialog from '@/components/ds/ConfirmDialog.vue';
import DateText from '@/components/ds/DateText.vue';
import EmptyState from '@/components/ds/EmptyState.vue';
import FileDropzone from '@/components/ds/FileDropzone.vue';
import SearchSelect from '@/components/ds/SearchSelect.vue';
import SectionCard from '@/components/ds/SectionCard.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { formatBytes } from '@/lib/format';
import archivos from '@/routes/admin/proyectos/archivos';
import type { FileItem } from '@/types/projects';
import type { HubCan, HubProject } from './types';

const props = defineProps<{
    project: HubProject;
    files: FileItem[];
    can: HubCan;
}>();

const ROLES = [
    { value: 'document', label: 'Documento interno' },
    { value: 'contract', label: 'Contrato (interno)' },
    { value: 'render', label: 'Render (interno)' },
    { value: 'photo', label: 'Foto (interna)' },
    { value: 'client_document', label: 'Documento para el cliente' },
];
const roleLabel = (role: string) =>
    ROLES.find((r) => r.value === role)?.label ?? role;

const uploadRole = ref<string | null>('document');

function attach(media: UploadedMediaResult) {
    router.post(
        archivos.store(props.project.uuid).url,
        { media_id: media.id, role: uploadRole.value ?? 'document' },
        { preserveScroll: true },
    );
}

function toggleClient(file: FileItem) {
    const role =
        file.role === 'client_document' ? 'document' : 'client_document';
    router.patch(
        archivos.update({ project: props.project.uuid, attachment: file.id })
            .url,
        { role, caption: file.caption },
        { preserveScroll: true },
    );
}

const toRemove = ref<FileItem | null>(null);
const removeOpen = computed({
    get: () => toRemove.value !== null,
    set: (v) => !v && (toRemove.value = null),
});
function remove() {
    if (!toRemove.value) return;
    const id = toRemove.value.id;

    return new Promise<void>((resolve, reject) =>
        router.delete(
            archivos.destroy({ project: props.project.uuid, attachment: id })
                .url,
            {
                preserveScroll: true,
                onSuccess: () => resolve(),
                onError: () =>
                    reject(new Error('No se pudo quitar el archivo.')),
            },
        ),
    );
}

const clientCount = computed(
    () => props.files.filter((f) => f.role === 'client_document').length,
);
</script>

<template>
    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_340px]">
        <SectionCard
            title="Archivos del proyecto"
            :description="`Todos son privados. ${clientCount} visible(s) para el cliente en «Documentos».`"
            flush
        >
            <EmptyState
                v-if="!files.length"
                compact
                :bordered="false"
                :icon="FileText"
                title="Sin archivos todavía"
                description="Sube contratos firmados, planos o fotos. Decide cuáles ve el cliente."
            />
            <ul v-else class="flex flex-col divide-y">
                <li
                    v-for="f in files"
                    :key="f.id"
                    class="flex flex-col gap-3 px-4 py-3 sm:flex-row sm:items-center"
                >
                    <a
                        :href="f.url ?? undefined"
                        target="_blank"
                        rel="noopener"
                        class="flex min-w-0 flex-1 items-center gap-3"
                    >
                        <img
                            v-if="f.is_image && f.url"
                            :src="f.url"
                            alt=""
                            class="size-12 shrink-0 rounded-md border object-cover"
                            loading="lazy"
                        />
                        <span
                            v-else
                            class="grid size-12 shrink-0 place-items-center rounded-md border bg-muted"
                            ><FileText
                                class="size-5 text-muted-foreground"
                                aria-hidden="true"
                        /></span>
                        <span class="min-w-0">
                            <span
                                class="block truncate font-medium hover:underline"
                                >{{ f.name }}</span
                            >
                            <span class="text-xs text-muted-foreground">
                                {{ roleLabel(f.role)
                                }}<template v-if="f.size">
                                    · {{ formatBytes(f.size) }}</template
                                >
                                · <DateText :value="f.created_at" />
                            </span>
                        </span>
                    </a>
                    <div v-if="can.manage" class="flex items-center gap-2">
                        <Button
                            size="sm"
                            :variant="
                                f.role === 'client_document'
                                    ? 'secondary'
                                    : 'outline'
                            "
                            :aria-pressed="f.role === 'client_document'"
                            @click="toggleClient(f)"
                        >
                            <component
                                :is="f.role === 'client_document' ? Eye : Lock"
                                class="size-4"
                                aria-hidden="true"
                            />
                            {{
                                f.role === 'client_document'
                                    ? 'Visible al cliente'
                                    : 'Solo equipo'
                            }}
                        </Button>
                        <Button
                            size="icon"
                            variant="ghost"
                            :aria-label="`Quitar ${f.name}`"
                            @click="toRemove = f"
                            ><Trash2 class="size-4" aria-hidden="true"
                        /></Button>
                    </div>
                </li>
            </ul>
        </SectionCard>

        <SectionCard v-if="can.manage" title="Subir archivos">
            <div class="flex flex-col gap-4">
                <div class="grid gap-1.5">
                    <Label for="upload-role">Tipo de archivo</Label>
                    <SearchSelect
                        id="upload-role"
                        v-model="uploadRole"
                        :options="ROLES"
                    />
                    <p class="text-xs text-muted-foreground">
                        {{
                            uploadRole === 'client_document'
                                ? 'El cliente lo verá en su portal y se le avisará en su cronología.'
                                : 'Solo el equipo podrá verlo.'
                        }}
                    </p>
                </div>
                <FileDropzone
                    visibility="private"
                    :max-size="100 * 1024 * 1024"
                    hint="PDF, imágenes u hojas de cálculo. Hasta 100 MB."
                    @uploaded="attach"
                />
            </div>
        </SectionCard>

        <ConfirmDialog
            v-model:open="removeOpen"
            title="¿Quitar este archivo del proyecto?"
            :description="
                toRemove?.role === 'client_document'
                    ? 'El cliente dejará de verlo en su portal.'
                    : 'Dejará de aparecer en el proyecto.'
            "
            confirm-label="Quitar"
            tone="destructive"
            :confirm="remove"
        />
    </div>
</template>
