<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { FileText, Plus } from '@lucide/vue';
import DataView from '@/components/ds/DataView.vue';
import DateText from '@/components/ds/DateText.vue';
import SectionCard from '@/components/ds/SectionCard.vue';
import StatusBadge from '@/components/ds/StatusBadge.vue';
import type { DataColumn } from '@/components/ds/types';
import { Button } from '@/components/ui/button';
import paginas from '@/routes/admin/sitio/paginas';

export type PageRow = {
    id: number;
    title: string;
    slug: string;
    version: number;
    isPublished: boolean;
    showInFooter: boolean;
    publishedAt: string | null;
    updatedAt: string | null;
    updatedBy: string | null;
};

const props = defineProps<{ pages: PageRow[] }>();

const columns: DataColumn<PageRow>[] = [
    { key: 'title', label: 'Página', primary: true },
    { key: 'status', label: 'Estado' },
    { key: 'version', label: 'Versión', align: 'right' },
    { key: 'updatedAt', label: 'Última edición' },
];
</script>

<template>
    <SectionCard
        title="Páginas legales e informativas"
        description="Aviso de privacidad, términos, política de cancelación y otras páginas de texto. Cada publicación aumenta la versión."
    >
        <template #actions>
            <Button as-child size="sm">
                <Link :href="paginas.create().url"
                    ><Plus class="size-4" /> Nueva página</Link
                >
            </Button>
        </template>

        <DataView
            :columns="columns"
            :rows="props.pages"
            :row-href="(row: PageRow) => paginas.edit(row.id).url"
            empty-title="Sin páginas"
            empty-description="Crea el aviso de privacidad y los términos antes de abrir el sitio."
        >
            <template #cell-title="{ row }">
                <span class="flex items-center gap-2"
                    ><FileText
                        class="size-4 text-muted-foreground"
                        aria-hidden="true"
                    />{{ row.title }}</span
                >
                <span class="block text-xs text-muted-foreground"
                    >/legal/{{ row.slug }}</span
                >
            </template>
            <template #cell-status="{ row }">
                <StatusBadge
                    :tone="row.isPublished ? 'success' : 'warning'"
                    :label="row.isPublished ? 'Publicada' : 'Borrador'"
                    size="sm"
                />
            </template>
            <template #cell-version="{ row }">v{{ row.version }}</template>
            <template #cell-updatedAt="{ row }">
                <DateText :value="row.updatedAt" relative />
                <span
                    v-if="row.updatedBy"
                    class="block text-xs text-muted-foreground"
                    >{{ row.updatedBy }}</span
                >
            </template>
        </DataView>
    </SectionCard>
</template>
