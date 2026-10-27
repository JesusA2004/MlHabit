<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ArrowLeft, ExternalLink, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import ConfirmDialog from '@/components/ds/ConfirmDialog.vue';
import DateText from '@/components/ds/DateText.vue';
import PageContainer from '@/components/ds/PageContainer.vue';
import PageHeader from '@/components/ds/PageHeader.vue';
import SectionCard from '@/components/ds/SectionCard.vue';
import StatusBadge from '@/components/ds/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import RichTextEditor from '@/components/web/admin/RichTextEditor.vue';
import paginas from '@/routes/admin/sitio/paginas';

type PageData = {
    id: number;
    title: string;
    slug: string;
    summary: string | null;
    body: string;
    version: number;
    isPublished: boolean;
    showInFooter: boolean;
    publishedAt: string | null;
    updatedAt: string | null;
    publicUrl: string | null;
};

const props = defineProps<{
    page: PageData | null;
    can: { publish: boolean };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Panel', href: '/admin' },
            { title: 'Sitio web', href: '/admin/sitio?pestana=paginas' },
            { title: 'Página', href: '#' },
        ],
    },
});

const form = useForm({
    title: props.page?.title ?? '',
    slug: props.page?.slug ?? '',
    summary: props.page?.summary ?? '',
    body: props.page?.body ?? '',
    show_in_footer: props.page?.showInFooter ?? true,
});

const locked = !!props.page?.isPublished && !props.can.publish;

function submit() {
    if (props.page)
        form.put(paginas.update(props.page.id).url, { preserveScroll: true });
    else form.post(paginas.store().url);
}

const publishOpen = ref(false);
const unpublishOpen = ref(false);
const deleteOpen = ref(false);

function action(url: string, method: 'post' | 'delete'): Promise<void> {
    return new Promise((resolve, reject) => {
        router.visit(url, {
            method,
            preserveScroll: true,
            onSuccess: () => resolve(),
            onError: () => reject(new Error('No se pudo completar la acción.')),
        });
    });
}
</script>

<template>
    <Head :title="props.page ? props.page.title : 'Nueva página'" />

    <PageContainer width="narrow" class="flex flex-col gap-6">
        <PageHeader
            :title="props.page ? props.page.title : 'Nueva página'"
            description="El texto se publica tal como lo ves. Revisa con tu asesor legal antes de publicar documentos legales."
        >
            <template #breadcrumbs>
                <Link
                    href="/admin/sitio?pestana=paginas"
                    class="inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
                >
                    <ArrowLeft class="size-4" /> Páginas
                </Link>
            </template>
            <template v-if="props.page" #meta>
                <StatusBadge
                    :tone="props.page.isPublished ? 'success' : 'warning'"
                    :label="props.page.isPublished ? 'Publicada' : 'Borrador'"
                />
                <span>Versión {{ props.page.version }}</span>
                <span v-if="props.page.publishedAt"
                    >· Publicada
                    <DateText :value="props.page.publishedAt" relative
                /></span>
            </template>
            <template v-if="props.page" #actions>
                <Button
                    v-if="props.page.publicUrl"
                    as="a"
                    :href="props.page.publicUrl"
                    target="_blank"
                    rel="noopener"
                    variant="outline"
                >
                    <ExternalLink class="size-4" /> Ver
                </Button>
                <template v-if="props.can.publish">
                    <Button
                        v-if="props.page.isPublished"
                        variant="outline"
                        @click="unpublishOpen = true"
                        >Despublicar</Button
                    >
                    <Button v-else @click="publishOpen = true">Publicar</Button>
                </template>
            </template>
        </PageHeader>

        <p
            v-if="locked"
            class="rounded-lg bg-warning-soft p-3 text-sm text-warning-ink"
        >
            Esta página está publicada. Para modificarla necesitas el permiso de
            publicar contenido.
        </p>
        <p
            v-else-if="props.page?.isPublished"
            class="rounded-lg bg-info-soft p-3 text-sm text-info-ink"
        >
            Esta página está publicada: al guardar cambios en el texto se
            publica una nueva versión.
        </p>

        <form class="flex flex-col gap-6" @submit.prevent="submit">
            <SectionCard title="Contenido">
                <div class="grid gap-5">
                    <div class="grid gap-1.5">
                        <Label for="page-title">Título</Label>
                        <Input
                            id="page-title"
                            v-model="form.title"
                            maxlength="160"
                            :disabled="locked"
                        />
                        <InputError :message="form.errors.title" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="page-summary">Resumen (opcional)</Label>
                        <Textarea
                            id="page-summary"
                            v-model="form.summary"
                            rows="2"
                            maxlength="320"
                            :disabled="locked"
                        />
                        <InputError :message="form.errors.summary" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="page-body">Texto</Label>
                        <RichTextEditor
                            id="page-body"
                            v-model="form.body"
                            :invalid="!!form.errors.body"
                            min-height="min-h-96"
                            placeholder="Escribe el contenido de la página…"
                        />
                        <InputError :message="form.errors.body" />
                    </div>
                </div>
            </SectionCard>

            <SectionCard title="Publicación">
                <div class="grid gap-5">
                    <div class="grid gap-1.5">
                        <Label for="page-slug">Dirección (URL)</Label>
                        <div class="flex items-center gap-2">
                            <span class="shrink-0 text-sm text-muted-foreground"
                                >/legal/</span
                            >
                            <Input
                                id="page-slug"
                                v-model="form.slug"
                                maxlength="140"
                                placeholder="se-genera-del-titulo"
                                :disabled="locked"
                            />
                        </div>
                        <InputError :message="form.errors.slug" />
                    </div>
                    <label
                        class="flex items-center justify-between gap-3 rounded-lg border border-border p-3 text-sm"
                    >
                        Mostrar en el pie de página
                        <Switch
                            v-model="form.show_in_footer"
                            :disabled="locked"
                        />
                    </label>
                </div>
            </SectionCard>

            <div
                class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-between"
            >
                <Button
                    v-if="props.page && !props.page.isPublished"
                    type="button"
                    variant="ghost"
                    class="text-danger-ink"
                    @click="deleteOpen = true"
                >
                    <Trash2 class="size-4" /> Eliminar
                </Button>
                <span v-else />
                <Button type="submit" :disabled="form.processing || locked"
                    ><Spinner v-if="form.processing" />
                    {{
                        props.page?.isPublished
                            ? 'Guardar y publicar'
                            : 'Guardar borrador'
                    }}</Button
                >
            </div>
        </form>

        <template v-if="props.page">
            <ConfirmDialog
                v-model:open="publishOpen"
                title="¿Publicar esta página?"
                description="Quedará visible en el sitio. Confirma que el texto ya fue revisado."
                confirm-label="Publicar"
                :confirm="
                    () => action(paginas.publicar(props.page!.id).url, 'post')
                "
            />
            <ConfirmDialog
                v-model:open="unpublishOpen"
                title="¿Despublicar esta página?"
                description="Dejará de verse en el sitio y en el pie de página."
                confirm-label="Despublicar"
                :confirm="
                    () =>
                        action(paginas.despublicar(props.page!.id).url, 'post')
                "
            />
            <ConfirmDialog
                v-model:open="deleteOpen"
                title="¿Eliminar esta página?"
                description="Se borra de forma permanente."
                confirm-label="Eliminar"
                tone="destructive"
                :confirm="
                    () => action(paginas.destroy(props.page!.id).url, 'delete')
                "
            />
        </template>
    </PageContainer>
</template>
