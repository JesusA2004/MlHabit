<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { BookOpen, ExternalLink, FileText, Milestone, PenTool, Pencil, ShieldCheck, Type } from '@lucide/vue';
import type { Component } from 'vue';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';
import EmptyState from '@/components/admin/EmptyState.vue';
import { Button } from '@/components/ui/button';
import paginas from '@/routes/admin/paginas';
import type { BreadcrumbItem } from '@/types';

defineProps<{
    pages: { key: string; label: string; title: string; updatedAt: string | null }[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Panel', href: '/admin' },
            { title: 'Páginas', href: paginas.index() },
        ] as BreadcrumbItem[],
    },
});

const STUDIO_HREF = '/admin/studio';

/** Datos de presentación por página (dirección pública, ícono y qué contiene). */
const META: Record<string, { path: string; icon: Component; summary: string }> = {
    'mi-historia': { path: '/mi-historia', icon: BookOpen, summary: 'Título, introducción, fotografía y relato completo.' },
    'mi-proceso': { path: '/mi-proceso', icon: Milestone, summary: 'Introducción y entradas de la línea de tiempo.' },
    'aviso-privacidad': { path: '/aviso-de-privacidad', icon: ShieldCheck, summary: 'Texto legal del aviso de privacidad.' },
};

function meta(key: string) {
    return META[key] ?? { path: `/${key}`, icon: FileText, summary: 'Textos de la página.' };
}

function fmt(iso: string | null): string {
    if (!iso) return 'Sin cambios todavía';

    return `Actualizada el ${new Intl.DateTimeFormat('es-MX', { dateStyle: 'long' }).format(new Date(iso))}`;
}
</script>

<template>
    <Head title="Páginas" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6 lg:p-8">
        <AdminPageHeader
            eyebrow="Contenido"
            title="Páginas"
            description="Aquí se escriben los textos de tus páginas fijas: títulos, introducciones, fotografías y relatos."
        >
            <template #actions>
                <Button as-child variant="outline">
                    <Link :href="STUDIO_HREF"><PenTool class="size-4" /> Diseñar en Studio</Link>
                </Button>
            </template>
        </AdminPageHeader>

        <!-- Cómo se reparten Páginas y Studio -->
        <section class="grid overflow-hidden rounded-xl border bg-card md:grid-cols-2" aria-label="Dónde se edita cada cosa">
            <div class="flex gap-4 p-5">
                <span class="grid size-10 shrink-0 place-items-center rounded-lg bg-muted text-foreground"><Type class="size-5" /></span>
                <div>
                    <p class="text-sm font-semibold">Aquí: el texto</p>
                    <p class="mt-1 text-sm text-muted-foreground">Título, introducción, fotografía principal, contenido completo, SEO y las entradas de «Mi proceso».</p>
                </div>
            </div>
            <div class="flex gap-4 border-t bg-brand/5 p-5 md:border-t-0 md:border-l">
                <span class="grid size-10 shrink-0 place-items-center rounded-lg bg-brand/15 text-brand"><PenTool class="size-5" /></span>
                <div class="flex flex-col items-start gap-2">
                    <div>
                        <p class="text-sm font-semibold">En Studio: la composición</p>
                        <p class="mt-1 text-sm text-muted-foreground">Orden de secciones, diseño, animaciones y estilos. Studio muestra estos textos por referencia, así que se actualizan solos.</p>
                    </div>
                    <Link :href="STUDIO_HREF" class="inline-flex min-h-11 items-center gap-1.5 text-sm font-medium text-brand hover:underline">
                        Abrir Studio <span aria-hidden="true">→</span>
                    </Link>
                </div>
            </div>
        </section>

        <EmptyState v-if="pages.length === 0" :icon="FileText" title="No hay páginas administrables" description="Las páginas fijas aparecerán aquí en cuanto existan en el sitio.">
            <Button as-child variant="outline">
                <Link :href="STUDIO_HREF"><PenTool class="size-4" /> Ir a Studio</Link>
            </Button>
        </EmptyState>

        <div v-else class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <article
                v-for="page in pages"
                :key="page.key"
                class="group flex flex-col rounded-xl border bg-card transition-colors hover:border-brand/60"
            >
                <Link :href="paginas.edit(page.key)" class="flex flex-1 flex-col gap-4 p-5">
                    <div class="flex items-start justify-between gap-3">
                        <span class="grid size-11 place-items-center rounded-lg bg-brand/12 text-brand">
                            <component :is="meta(page.key).icon" class="size-5" />
                        </span>
                        <span class="rounded-full bg-muted px-2.5 py-1 font-mono text-[11px] text-muted-foreground">{{ meta(page.key).path }}</span>
                    </div>
                    <div class="min-w-0">
                        <p class="text-xs font-medium tracking-wide text-muted-foreground uppercase">{{ page.label }}</p>
                        <h2 class="mt-1 font-display text-xl leading-snug font-semibold group-hover:underline">{{ page.title }}</h2>
                        <p class="mt-2 text-sm text-muted-foreground">{{ meta(page.key).summary }}</p>
                    </div>
                    <p class="mt-auto text-xs text-muted-foreground">{{ fmt(page.updatedAt) }}</p>
                </Link>
                <div class="flex items-center gap-2 border-t p-3">
                    <Button as-child class="flex-1 pointer-coarse:min-h-11">
                        <Link :href="paginas.edit(page.key)"><Pencil class="size-4" /> Editar textos</Link>
                    </Button>
                    <Button as-child variant="outline" class="pointer-coarse:min-h-11">
                        <a :href="meta(page.key).path" target="_blank" rel="noopener" :aria-label="`Ver ${page.label} en el sitio (nueva pestaña)`">
                            <ExternalLink class="size-4" /> Ver
                        </a>
                    </Button>
                </div>
            </article>
        </div>
    </div>
</template>
