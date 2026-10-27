<script setup lang="ts">
import { Form, Head, Link, router } from '@inertiajs/vue3';
import { ExternalLink, FilePlus2, History, LayoutTemplate, Lock, PenTool, Trash2 } from '@lucide/vue';
import { ref, watch } from 'vue';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';
import StatusBadge from '@/components/admin/StatusBadge.vue';
import InputError from '@/components/InputError.vue';
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
import studio from '@/routes/admin/studio';
import type { BreadcrumbItem } from '@/types';

interface PageRow {
    id: number;
    key: string;
    kind: 'system' | 'custom';
    title: string;
    path: string;
    isPublished: boolean;
    hasChanges: boolean;
    publishedAt: string | null;
    publishedNumber: number | null;
    draftUpdatedAt: string | null;
    draftUpdatedBy: string | null;
    versionsCount: number;
    sectionsCount: number;
}

defineProps<{ pages: PageRow[] }>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Panel', href: '/admin' },
            { title: 'Studio', href: '/admin/studio' },
        ] as BreadcrumbItem[],
    },
});

const createOpen = ref(false);
const title = ref('');
const slug = ref('');
const slugTouched = ref(false);
watch(title, (t) => {
    if (slugTouched.value) return;
    slug.value = t
        .toLowerCase()
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '')
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '')
        .slice(0, 80);
});

const toDelete = ref<PageRow | null>(null);

function fmt(iso: string | null): string {
    if (!iso) return '—';

    return new Intl.DateTimeFormat('es-MX', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(iso));
}

function destroy() {
    if (!toDelete.value) return;
    router.delete(studio.destroy(toDelete.value.id).url, { preserveScroll: true });
    toDelete.value = null;
}
</script>

<template>
    <Head title="Studio" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6 lg:p-8">
        <AdminPageHeader
            eyebrow="MR. INSIGHT Studio"
            title="Diseña tus páginas"
            description="Elige una página para cambiar su composición, fotografías, videos, títulos y animaciones. Todo se guarda como borrador hasta que publiques."
        >
            <template #actions>
                <Button @click="createOpen = true">
                    <FilePlus2 class="size-4" /> Nueva página
                </Button>
            </template>
        </AdminPageHeader>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            <article
                v-for="page in pages"
                :key="page.id"
                class="group flex flex-col overflow-hidden rounded-xl border bg-card transition-shadow hover:shadow-lg"
            >
                <Link :href="studio.edit(page.id).url" class="relative block aspect-[16/9] overflow-hidden bg-[#0d0e10]">
                    <div class="absolute inset-0 flex flex-col justify-end gap-2 p-5 text-[#f4f1eb]">
                        <span class="text-[10px] font-semibold tracking-[0.2em] text-[#c7aa7d] uppercase">{{ page.path }}</span>
                        <span class="font-display text-2xl leading-tight">{{ page.title }}</span>
                        <span class="text-xs text-[#aba9a3]">{{ page.sectionsCount }} secciones</span>
                    </div>
                    <div class="absolute inset-0 bg-[radial-gradient(circle_at_80%_10%,rgb(199_170_125/0.35),transparent_55%)]" />
                    <span class="absolute top-3 right-3 inline-flex items-center gap-1.5 rounded-full bg-[#c7aa7d] px-3 py-1 text-xs font-semibold text-[#0d0e10] opacity-0 transition-opacity group-hover:opacity-100 group-focus-visible:opacity-100">
                        <PenTool class="size-3.5" /> Abrir en Studio
                    </span>
                </Link>
                <div class="flex flex-1 flex-col gap-3 p-4">
                    <div class="flex flex-wrap items-center gap-2">
                        <StatusBadge :status="!page.isPublished ? 'unpublished' : page.hasChanges ? 'changes' : 'published'" />
                        <span v-if="page.kind === 'system'" class="inline-flex items-center gap-1 text-[11px] text-muted-foreground"><Lock class="size-3" /> Página del sistema</span>
                    </div>
                    <dl class="grid grid-cols-2 gap-2 text-xs">
                        <div>
                            <dt class="text-muted-foreground">Publicada</dt>
                            <dd class="font-medium">{{ page.publishedNumber ? `v${page.publishedNumber} · ` : '' }}{{ fmt(page.publishedAt) }}</dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground">Último borrador</dt>
                            <dd class="font-medium">{{ fmt(page.draftUpdatedAt) }}</dd>
                        </div>
                    </dl>
                    <div class="mt-auto flex items-center gap-2 pt-1">
                        <Button as-child size="sm" class="flex-1">
                            <Link :href="studio.edit(page.id).url"><PenTool class="size-4" /> Editar</Link>
                        </Button>
                        <Button v-if="page.isPublished" as-child size="sm" variant="outline">
                            <a :href="page.path" target="_blank" rel="noopener"><ExternalLink class="size-4" /> Ver</a>
                        </Button>
                        <span class="inline-flex items-center gap-1 text-[11px] text-muted-foreground" :title="`${page.versionsCount} versiones`">
                            <History class="size-3.5" /> {{ page.versionsCount }}
                        </span>
                        <Button v-if="page.kind === 'custom'" size="icon-sm" variant="ghost" :aria-label="`Eliminar ${page.title}`" @click="toDelete = page">
                            <Trash2 class="size-4" />
                        </Button>
                    </div>
                </div>
            </article>

            <button
                type="button"
                class="flex min-h-[280px] flex-col items-center justify-center gap-3 rounded-xl border border-dashed text-muted-foreground transition-colors hover:border-brand hover:text-foreground"
                @click="createOpen = true"
            >
                <span class="grid size-12 place-items-center rounded-full bg-brand/12 text-brand"><LayoutTemplate class="size-5" /></span>
                <span class="text-sm font-medium">Crear una página nueva</span>
                <span class="max-w-[240px] text-center text-xs">Landing de un taller, una colaboración o un proyecto especial.</span>
            </button>
        </div>
    </div>

    <Dialog v-model:open="createOpen">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Nueva página</DialogTitle>
                <DialogDescription>Empieza con una portada editorial y personalízala en Studio.</DialogDescription>
            </DialogHeader>
            <Form v-slot="{ errors, processing }" v-bind="studio.store.form()" class="flex flex-col gap-4" @success="createOpen = false">
                <div class="flex flex-col gap-1.5">
                    <Label for="np-title">Nombre</Label>
                    <Input id="np-title" v-model="title" name="title" required maxlength="120" placeholder="Ej.: Taller de hábitos" />
                    <InputError :message="errors.title" />
                </div>
                <div class="flex flex-col gap-1.5">
                    <Label for="np-slug">Dirección</Label>
                    <div class="flex items-center rounded-md border border-input bg-background pl-3 text-sm">
                        <span class="text-muted-foreground">mrinsight.mx/</span>
                        <input id="np-slug" v-model="slug" name="slug" required maxlength="80" class="h-9 flex-1 bg-transparent px-1 outline-none" @input="slugTouched = true" />
                    </div>
                    <InputError :message="errors.slug" />
                </div>
                <DialogFooter>
                    <Button type="button" variant="ghost" @click="createOpen = false">Cancelar</Button>
                    <Button type="submit" :disabled="processing">Crear y diseñar</Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>

    <AlertDialog :open="!!toDelete" @update:open="(v: boolean) => !v && (toDelete = null)">
        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle>¿Eliminar «{{ toDelete?.title }}»?</AlertDialogTitle>
                <AlertDialogDescription>
                    Se eliminarán la página y su historial de versiones. Su dirección dejará de funcionar. Esta acción no se puede deshacer.
                </AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
                <AlertDialogCancel>Cancelar</AlertDialogCancel>
                <AlertDialogAction class="bg-destructive text-white hover:bg-destructive/90" @click="destroy">Eliminar</AlertDialogAction>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>
</template>
