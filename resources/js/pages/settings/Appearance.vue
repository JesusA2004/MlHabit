<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import FormSection from '@/components/admin/FormSection.vue';
import AppearanceTabs from '@/components/AppearanceTabs.vue';
import Heading from '@/components/Heading.vue';
import { edit } from '@/routes/appearance';
import { edit as editProfile } from '@/routes/profile';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Mi cuenta', href: editProfile() },
            { title: 'Apariencia', href: edit() },
        ],
    },
});
</script>

<template>
    <Head title="Apariencia" />

    <Heading
        title="Apariencia"
        description="Cómo se ve ML Habitat en este dispositivo. El cambio se aplica al instante."
    />

    <div
        class="rounded-xl border bg-card px-4 text-card-foreground shadow-xs sm:px-6"
    >
        <FormSection
            title="Tema"
            description="Elige claro, oscuro o deja que siga la configuración de tu dispositivo."
        >
            <template #aside>
                <p class="mt-4 text-xs text-muted-foreground">
                    Solo afecta al panel y a tu cuenta. El sitio público siempre
                    se muestra en claro.
                </p>
            </template>

            <AppearanceTabs />
        </FormSection>
    </div>
</template>
