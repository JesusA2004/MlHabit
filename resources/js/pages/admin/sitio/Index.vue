<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ExternalLink } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import PageContainer from '@/components/ds/PageContainer.vue';
import PageHeader from '@/components/ds/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import PagesPanel from '@/components/web/admin/site/PagesPanel.vue';
import type { PageRow } from '@/components/web/admin/site/PagesPanel.vue';
import EditorLauncher from '@/components/web/admin/site/EditorLauncher.vue';
import type { EditorSummary } from '@/components/web/admin/site/EditorLauncher.vue';
import SettingsPanel from '@/components/web/admin/site/SettingsPanel.vue';
import SocialPanel from '@/components/web/admin/site/SocialPanel.vue';
import type { SocialLinkRow } from '@/components/web/admin/site/SocialPanel.vue';
import TestimonialsPanel from '@/components/web/admin/site/TestimonialsPanel.vue';
import type { TestimonialRow } from '@/components/web/admin/site/TestimonialsPanel.vue';
import sitio from '@/routes/admin/sitio';
import type { MediaRef, Option } from '@/types/web';

type Tab =
    | 'portada'
    | 'identidad'
    | 'contacto'
    | 'redes'
    | 'seo'
    | 'pie'
    | 'testimonios'
    | 'paginas';

const props = defineProps<{
    tab: Tab;
    editor: EditorSummary;
    media: Record<string, MediaRef | null>;
    settings: {
        brand: Record<string, unknown>;
        contact: Record<string, unknown>;
        seo: Record<string, unknown>;
        footer: Record<string, unknown>;
    };
    socialLinks: SocialLinkRow[];
    testimonials: TestimonialRow[];
    pages: PageRow[];
    showcaseOptions: Option<number>[];
    can: { publish: boolean };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Panel', href: '/admin' },
            { title: 'Sitio web', href: '/admin/sitio' },
        ],
    },
});

const tabs: { value: Tab; label: string }[] = [
    { value: 'portada', label: 'Portada' },
    { value: 'identidad', label: 'Identidad' },
    { value: 'contacto', label: 'Contacto' },
    { value: 'redes', label: 'Redes sociales' },
    { value: 'seo', label: 'SEO' },
    { value: 'pie', label: 'Pie de página' },
    { value: 'testimonios', label: 'Testimonios' },
    { value: 'paginas', label: 'Páginas legales' },
];

const current = ref<Tab>(props.tab);
watch(
    () => props.tab,
    (value) => (current.value = value),
);
watch(current, (value) => {
    const url = new URL(window.location.href);
    url.searchParams.set('pestana', value);
    router.replace({
        url: url.pathname + url.search,
        preserveState: true,
        preserveScroll: true,
    });
});

const mediaMap = computed(() => props.media);
</script>

<template>
    <Head title="Sitio web" />

    <PageContainer class="flex flex-col gap-6">
        <PageHeader
            title="Sitio web"
            description="Edita la portada, la identidad de la marca, los datos de contacto y las páginas legales del sitio público."
        >
            <template #actions>
                <Button
                    as="a"
                    href="/"
                    target="_blank"
                    rel="noopener"
                    variant="outline"
                >
                    <ExternalLink class="size-4" /> Ver sitio
                </Button>
            </template>
        </PageHeader>

        <Tabs v-model="current" class="gap-6">
            <div
                class="-mx-4 scrollbar-none overflow-x-auto px-4 md:mx-0 md:px-0"
            >
                <TabsList class="w-max">
                    <TabsTrigger
                        v-for="t in tabs"
                        :key="t.value"
                        :value="t.value"
                        >{{ t.label }}</TabsTrigger
                    >
                </TabsList>
            </div>

            <TabsContent value="portada">
                <EditorLauncher :editor="props.editor" />
            </TabsContent>

            <TabsContent value="identidad">
                <SettingsPanel
                    group="brand"
                    title="Identidad"
                    description="Logotipos, favicon e imagen para compartir en redes. Usa PNG o WebP con fondo transparente para los logotipos."
                    :values="props.settings.brand"
                    :media="mediaMap"
                    :fields="[
                        {
                            key: 'tagline',
                            label: 'Frase de la marca',
                            kind: 'text',
                            max: 160,
                            help: 'Aparece en el pie de página y en los datos para buscadores.',
                        },
                        {
                            key: 'logo_media_id',
                            label: 'Logotipo',
                            kind: 'media',
                            aspect: 'aspect-[3/1]',
                        },
                        {
                            key: 'logo_dark_media_id',
                            label: 'Logotipo para fondo oscuro',
                            kind: 'media',
                            aspect: 'aspect-[3/1]',
                        },
                        {
                            key: 'favicon_media_id',
                            label: 'Favicon (cuadrado)',
                            kind: 'media',
                            aspect: 'aspect-square max-w-32',
                        },
                        {
                            key: 'og_media_id',
                            label: 'Imagen al compartir en redes (1200 × 630)',
                            kind: 'media',
                            aspect: 'aspect-[1200/630]',
                        },
                    ]"
                />
            </TabsContent>

            <TabsContent value="contacto">
                <SettingsPanel
                    group="contact"
                    title="Contacto"
                    description="Solo se muestran en el sitio los datos que captures."
                    :values="props.settings.contact"
                    :fields="[
                        {
                            key: 'phone',
                            label: 'Teléfono',
                            kind: 'tel',
                            max: 30,
                            placeholder: '55 1234 5678',
                        },
                        {
                            key: 'whatsapp',
                            label: 'WhatsApp',
                            kind: 'tel',
                            max: 30,
                            help: 'Número a 10 dígitos; se agrega la clave de México automáticamente.',
                        },
                        {
                            key: 'email',
                            label: 'Correo',
                            kind: 'email',
                            max: 160,
                        },
                        {
                            key: 'hours',
                            label: 'Horario de atención',
                            kind: 'text',
                            max: 200,
                            placeholder: 'Lunes a viernes de 9:00 a 18:00',
                        },
                        {
                            key: 'address',
                            label: 'Dirección',
                            kind: 'textarea',
                            max: 300,
                        },
                    ]"
                />
            </TabsContent>

            <TabsContent value="redes">
                <SocialPanel :links="props.socialLinks" />
            </TabsContent>

            <TabsContent value="seo">
                <SettingsPanel
                    group="seo"
                    title="SEO"
                    description="Título y descripción predeterminados para buscadores y redes sociales. Cada producto o proyecto puede tener los suyos."
                    :values="props.settings.seo"
                    :fields="[
                        {
                            key: 'title',
                            label: 'Título del sitio',
                            kind: 'text',
                            max: 120,
                            help: 'Idealmente menos de 60 caracteres.',
                        },
                        {
                            key: 'description',
                            label: 'Descripción',
                            kind: 'textarea',
                            max: 320,
                            help: 'Idealmente entre 120 y 160 caracteres.',
                        },
                        {
                            key: 'twitter_handle',
                            label: 'Usuario de X (opcional)',
                            kind: 'text',
                            max: 40,
                            placeholder: '@usuario',
                        },
                    ]"
                />
            </TabsContent>

            <TabsContent value="pie">
                <SettingsPanel
                    group="footer"
                    title="Pie de página"
                    :values="props.settings.footer"
                    :fields="[
                        {
                            key: 'text',
                            label: 'Texto del pie',
                            kind: 'textarea',
                            max: 400,
                        },
                        {
                            key: 'legal_name',
                            label: 'Razón social (opcional)',
                            kind: 'text',
                            max: 160,
                        },
                    ]"
                />
            </TabsContent>

            <TabsContent value="testimonios">
                <TestimonialsPanel
                    :testimonials="props.testimonials"
                    :showcase-options="props.showcaseOptions"
                    :can-publish="props.can.publish"
                />
            </TabsContent>

            <TabsContent value="paginas">
                <PagesPanel :pages="props.pages" />
            </TabsContent>
        </Tabs>
    </PageContainer>
</template>
