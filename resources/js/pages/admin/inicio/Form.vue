<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ArrowRight, Check, Loader2, PenTool, Repeat, Star } from '@lucide/vue';
import { ref } from 'vue';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';
import FormSection from '@/components/admin/FormSection.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import inicio from '@/routes/admin/inicio';
import studio from '@/routes/admin/studio';
import type { BreadcrumbItem, PostSummary } from '@/types';

interface SectionRow {
    section_key: string;
    is_visible: boolean;
    order: number;
}

interface FeaturedCandidate extends PostSummary {
    isFeatured: boolean;
}

interface CategoryPreview {
    category_id: number;
    category: string;
    post: PostSummary | null;
    candidates: FeaturedCandidate[];
}

const props = defineProps<{
    hero: {
        eyebrow: string;
        title_lines: string[];
        primary_cta_label: string;
        secondary_cta_label: string;
        tertiary_link_label: string;
        photo: { id: number; url: string | null } | null;
    };
    empiezaAqui: { title: string };
    tipsSection: { title: string; posts_per_page: number };
    historiaPreview: { title: string; photo: { id: number; url: string | null } | null };
    procesoPreview: { title: string };
    footer: { tagline: string };
    sections: SectionRow[];
    empiezaAquiPreview: CategoryPreview[];
    studioPageId: number;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Panel', href: '/admin' },
            { title: 'Inicio', href: inicio.edit() },
        ] as BreadcrumbItem[],
    },
});

/*
 * Los ajustes históricos (hero, títulos de secciones) se conservan tal cual
 * para no perder información, pero la composición visible del inicio ya se
 * define en Studio. Aquí solo se edita lo que sigue vigente: el pie y los
 * tips destacados por categoría (que alimentan el bloque «Empieza aquí»).
 */
const form = useForm({
    hero: { ...props.hero, photo_media_id: props.hero.photo?.id ?? null },
    empieza_aqui: { ...props.empiezaAqui },
    tips_section: { ...props.tipsSection },
    historia_preview: { ...props.historiaPreview, photo_media_id: props.historiaPreview.photo?.id ?? null },
    proceso_preview: { ...props.procesoPreview },
    footer: { ...props.footer },
    sections: props.sections.map((s) => ({ ...s })),
});

function save() {
    form.put(inicio.update().url, { preserveScroll: true });
}

const featuredPickerCategory = ref<CategoryPreview | null>(null);

function chooseFeatured(post: FeaturedCandidate) {
    if (!featuredPickerCategory.value) return;
    router.post(
        inicio.destacados().url,
        { category_id: featuredPickerCategory.value.category_id, post_id: post.id },
        { preserveScroll: true, onSuccess: () => (featuredPickerCategory.value = null) },
    );
}
</script>

<template>
    <Head title="Inicio" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6 lg:p-8">
        <AdminPageHeader
            eyebrow="Sitio web"
            title="Inicio"
            description="La portada del sitio se diseña en Studio. Aquí eliges qué tip representa a cada categoría y el texto del pie de página."
        >
            <template #actions>
                <Button as-child>
                    <Link :href="studio.edit(studioPageId)"><PenTool class="size-4" /> Diseñar el inicio en Studio</Link>
                </Button>
            </template>
        </AdminPageHeader>

        <Link
            :href="studio.edit(studioPageId)"
            class="group relative overflow-hidden rounded-xl border bg-[#0d0e10] p-6 text-[#f4f1eb] transition-shadow hover:shadow-xl md:p-8"
        >
            <div class="absolute inset-0 bg-[radial-gradient(circle_at_85%_20%,rgb(199_170_125/0.35),transparent_55%)]" aria-hidden="true" />
            <div class="relative flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <div class="max-w-xl">
                    <p class="text-[11px] font-semibold tracking-[0.2em] text-[#c7aa7d] uppercase">MR. INSIGHT Studio</p>
                    <p class="mt-2 font-display text-2xl leading-tight md:text-3xl">Portada, fotografías, títulos, videos y orden de secciones</p>
                    <p class="mt-2 text-sm text-[#aba9a3]">Edita sobre la página real, revisa en móvil y publica cuando esté lista. Cada publicación queda en el historial.</p>
                </div>
                <span class="inline-flex shrink-0 items-center gap-2 self-start rounded-full bg-[#c7aa7d] px-4 py-2 text-sm font-semibold text-[#0d0e10] md:self-auto">
                    Abrir Studio <ArrowRight class="size-4 transition-transform group-hover:translate-x-1" />
                </span>
            </div>
        </Link>

        <div class="rounded-xl border bg-card px-5 md:px-8">
            <FormSection
                title="Tip destacado por categoría"
                description="Alimenta la sección «Empieza aquí»: una puerta de entrada por tema. Solo se pueden elegir tips publicados."
            >
                <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    <article v-for="item in empiezaAquiPreview" :key="item.category_id" class="flex flex-col overflow-hidden rounded-lg border">
                        <div class="aspect-[16/9] bg-muted">
                            <img v-if="item.post?.coverUrl" :src="item.post.coverUrl" alt="" class="size-full object-cover" loading="lazy" />
                        </div>
                        <div class="flex flex-1 flex-col gap-2 p-4">
                            <p class="text-[11px] font-semibold tracking-[0.14em] text-brand uppercase">{{ item.category }}</p>
                            <p class="flex-1 text-sm font-medium">{{ item.post?.title ?? 'Sin tip publicado en esta categoría' }}</p>
                            <Button
                                variant="outline"
                                size="sm"
                                class="self-start"
                                :disabled="item.candidates.length === 0"
                                @click="featuredPickerCategory = item"
                            >
                                <Repeat class="size-4" /> Cambiar destacado
                            </Button>
                        </div>
                    </article>
                </div>
            </FormSection>

            <FormSection title="Pie de página" description="Frase breve bajo el nombre del sitio, en todas las páginas.">
                <form class="flex flex-col gap-3" @submit.prevent="save">
                    <div class="flex flex-col gap-1.5">
                        <Label for="footer-tagline">Frase del pie</Label>
                        <Textarea id="footer-tagline" v-model="form.footer.tagline" maxlength="200" rows="2" placeholder="Ej.: NEGOCIOS. HÁBITOS. VIDA. — SIN TAPUJOS." />
                        <div class="flex justify-between text-xs text-muted-foreground">
                            <InputError :message="form.errors['footer.tagline']" />
                            <span>{{ form.footer.tagline?.length ?? 0 }}/200</span>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <Button type="submit" :disabled="form.processing || !form.isDirty">
                            <Loader2 v-if="form.processing" class="size-4 animate-spin" />
                            Guardar
                        </Button>
                        <span v-if="form.recentlySuccessful" class="inline-flex items-center gap-1 text-sm text-success"><Check class="size-4" /> Guardado</span>
                    </div>
                </form>
            </FormSection>
        </div>
    </div>

    <Dialog :open="featuredPickerCategory !== null" @update:open="(v: boolean) => !v && (featuredPickerCategory = null)">
        <DialogContent class="max-h-[80vh] overflow-y-auto sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>Destacado de {{ featuredPickerCategory?.category }}</DialogTitle>
                <DialogDescription>Elige el tip que representará a esta categoría en el inicio.</DialogDescription>
            </DialogHeader>
            <ul class="flex flex-col gap-2">
                <li v-for="candidate in featuredPickerCategory?.candidates ?? []" :key="candidate.id">
                    <button
                        type="button"
                        class="flex w-full items-center gap-3 rounded-lg border p-2 text-left transition-colors hover:border-brand hover:bg-brand/5"
                        :class="{ 'border-brand bg-brand/10': candidate.isFeatured }"
                        @click="chooseFeatured(candidate)"
                    >
                        <span class="size-14 shrink-0 overflow-hidden rounded-md bg-muted">
                            <img v-if="candidate.coverUrl" :src="candidate.coverUrl" alt="" class="size-full object-cover" />
                        </span>
                        <span class="min-w-0 flex-1 text-sm font-medium">{{ candidate.title }}</span>
                        <Star v-if="candidate.isFeatured" class="size-4 fill-brand text-brand" aria-label="Destacado actual" />
                    </button>
                </li>
            </ul>
        </DialogContent>
    </Dialog>
</template>
