<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Download, FileText, FolderOpen, Printer } from '@lucide/vue';
import { computed } from 'vue';
import {
    DateText,
    EmptyState,
    PageContainer,
    PageHeader,
    SectionCard,
} from '@/components/ds';
import { formatBytes } from '@/lib/format';
import type { FileItem } from '@/types/projects';

type Group = {
    project: { uuid: string; folio: string; name: string };
    files: FileItem[];
    quotes: {
        uuid: string;
        folio: string;
        title: string;
        status: string;
        print_url: string;
    }[];
};

const props = defineProps<{ groups: Group[] }>();

const withContent = computed(() =>
    props.groups.filter((g) => g.files.length || g.quotes.length),
);
</script>

<template>
    <Head title="Mis documentos" />

    <PageContainer class="flex flex-col gap-6">
        <PageHeader
            title="Documentos"
            description="Contratos, garantías y archivos que compartimos contigo."
        />

        <div
            v-if="withContent.length"
            class="portal-stagger grid items-start gap-4 xl:grid-cols-2"
            style="--stagger-base: 60ms"
        >
            <SectionCard
                v-for="g in withContent"
                :key="g.project.uuid"
                :title="g.project.name"
                :description="g.project.folio"
                flush
            >
                <ul class="divide-y">
                    <li v-for="file in g.files" :key="file.id">
                        <a
                            :href="file.url ?? undefined"
                            target="_blank"
                            rel="noopener"
                            class="group flex items-center gap-3 px-4 py-3.5 transition-colors hover:bg-muted/40 focus-visible:bg-muted/40 focus-visible:outline-none sm:px-5"
                        >
                            <FileText
                                class="size-5 shrink-0 text-muted-foreground"
                                aria-hidden="true"
                            />
                            <span class="min-w-0 flex-1">
                                <span
                                    class="block truncate text-sm font-medium text-foreground"
                                    >{{ file.caption ?? file.name }}</span
                                >
                                <span
                                    class="block text-xs text-muted-foreground"
                                >
                                    <template v-if="file.size"
                                        >{{ formatBytes(file.size) }} ·
                                    </template>
                                    <DateText
                                        :value="file.created_at"
                                        format="medium"
                                    />
                                </span>
                            </span>
                            <Download
                                class="size-4 shrink-0 text-muted-foreground"
                                aria-hidden="true"
                            />
                            <span class="sr-only">Descargar</span>
                        </a>
                    </li>
                    <li v-for="q in g.quotes" :key="q.uuid">
                        <a
                            :href="q.print_url"
                            target="_blank"
                            rel="noopener"
                            class="group flex items-center gap-3 px-4 py-3.5 transition-colors hover:bg-muted/40 focus-visible:bg-muted/40 focus-visible:outline-none sm:px-5"
                        >
                            <Printer
                                class="size-5 shrink-0 text-muted-foreground"
                                aria-hidden="true"
                            />
                            <span class="min-w-0 flex-1">
                                <span
                                    class="block truncate text-sm font-medium text-foreground"
                                    >Cotización {{ q.folio }} ·
                                    {{ q.title }}</span
                                >
                                <span
                                    class="block text-xs text-muted-foreground"
                                    >{{ q.status }} · versión para
                                    imprimir</span
                                >
                            </span>
                        </a>
                    </li>
                </ul>
            </SectionCard>
        </div>

        <EmptyState
            v-if="!withContent.length"
            :icon="FolderOpen"
            title="Aún no hay documentos"
            description="Cuando compartamos contigo tu contrato, garantía u otros archivos, los encontrarás aquí."
        />
    </PageContainer>
</template>
