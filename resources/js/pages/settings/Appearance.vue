<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowRight } from '@lucide/vue';
import FormSection from '@/components/admin/FormSection.vue';
import AppearanceTabs from '@/components/AppearanceTabs.vue';
import Heading from '@/components/Heading.vue';
import { edit as editSiteAppearance } from '@/routes/admin/apariencia';
import { edit } from '@/routes/appearance';
import { edit as editProfile } from '@/routes/profile';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Configuración',
                href: editProfile(),
            },
            {
                title: 'Apariencia',
                href: edit(),
            },
        ],
    },
});
</script>

<template>
    <Head title="Apariencia" />

    <Heading title="Apariencia" description="Cómo se ve el panel administrador en este dispositivo. El cambio se aplica al instante." />

    <div class="rounded-xl border bg-card px-4 text-card-foreground shadow-xs sm:px-6">
        <FormSection title="Tema del panel" description="Elige claro, oscuro o deja que siga la configuración de tu dispositivo.">
            <template #aside>
                <p class="mt-4 text-xs text-muted-foreground">
                    Esto no cambia los colores del sitio público.
                    <Link :href="editSiteAppearance()" class="inline-flex items-center gap-1 font-medium text-foreground underline underline-offset-4 hover:no-underline">
                        Editar apariencia del sitio
                        <ArrowRight class="size-3" />
                    </Link>
                </p>
            </template>

            <AppearanceTabs />
        </FormSection>
    </div>
</template>
