<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    Clock,
    Mail,
    MapPin,
    MessageCircle,
    Phone,
    ShieldAlert,
} from '@lucide/vue';
import { computed } from 'vue';
import {
    PageContainer,
    PageHeader,
    SectionCard,
    UserAvatar,
} from '@/components/ds';
import { Button } from '@/components/ui/button';

const props = defineProps<{
    contact: {
        phone: string | null;
        whatsapp: string | null;
        email: string | null;
        address: string | null;
        hours: string | null;
    };
    advisor: {
        name: string;
        email: string | null;
        phone: string | null;
    } | null;
    project: { folio: string; name: string } | null;
}>();

const whatsappUrl = computed(() => {
    const digits = (props.contact.whatsapp ?? '').replace(/\D/g, '');

    if (!digits) {
        return null;
    }

    const number = digits.length === 10 ? `52${digits}` : digits;
    const text = props.project
        ? `Hola, tengo una pregunta sobre mi proyecto ${props.project.folio}.`
        : 'Hola, tengo una pregunta.';

    return `https://wa.me/${number}?text=${encodeURIComponent(text)}`;
});

const hasContact = computed(() =>
    Object.values(props.contact).some((v) => !!v),
);
</script>

<template>
    <Head title="Ayuda" />

    <PageContainer width="narrow" class="flex flex-col gap-6">
        <PageHeader
            title="¿Cómo te ayudamos?"
            description="Estamos para resolver tus dudas sobre tu proyecto."
        />

        <SectionCard v-if="advisor" title="Tu asesor">
            <div class="flex items-center gap-3">
                <UserAvatar :name="advisor.name" size="lg" />
                <div>
                    <p class="font-medium text-foreground">
                        {{ advisor.name }}
                    </p>
                    <p class="text-sm text-muted-foreground">
                        {{
                            project
                                ? `Te acompaña en ${project.name}.`
                                : 'Te acompaña en todo tu proyecto.'
                        }}
                    </p>
                </div>
            </div>
            <div class="mt-4 grid gap-2 sm:grid-cols-2">
                <Button v-if="advisor.phone" as-child variant="outline">
                    <a :href="`tel:${advisor.phone}`"
                        ><Phone class="size-4" aria-hidden="true" /> Llamar</a
                    >
                </Button>
                <Button v-if="advisor.email" as-child variant="outline">
                    <a :href="`mailto:${advisor.email}`"
                        ><Mail class="size-4" aria-hidden="true" /> Escribir
                        correo</a
                    >
                </Button>
            </div>
        </SectionCard>

        <SectionCard title="ML Habitat">
            <ul v-if="hasContact" class="flex flex-col gap-3 text-sm">
                <li v-if="whatsappUrl">
                    <a
                        :href="whatsappUrl"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="flex items-center gap-2.5 text-foreground hover:text-primary"
                    >
                        <MessageCircle
                            class="size-4 text-muted-foreground"
                            aria-hidden="true"
                        />
                        WhatsApp {{ contact.whatsapp }}
                    </a>
                </li>
                <li v-if="contact.phone">
                    <a
                        :href="`tel:${contact.phone}`"
                        class="flex items-center gap-2.5 text-foreground hover:text-primary"
                    >
                        <Phone
                            class="size-4 text-muted-foreground"
                            aria-hidden="true"
                        />
                        {{ contact.phone }}
                    </a>
                </li>
                <li v-if="contact.email">
                    <a
                        :href="`mailto:${contact.email}`"
                        class="flex items-center gap-2.5 text-foreground hover:text-primary"
                    >
                        <Mail
                            class="size-4 text-muted-foreground"
                            aria-hidden="true"
                        />
                        {{ contact.email }}
                    </a>
                </li>
                <li
                    v-if="contact.hours"
                    class="flex items-start gap-2.5 text-foreground"
                >
                    <Clock
                        class="mt-0.5 size-4 shrink-0 text-muted-foreground"
                        aria-hidden="true"
                    />
                    <span class="whitespace-pre-line">{{ contact.hours }}</span>
                </li>
                <li
                    v-if="contact.address"
                    class="flex items-start gap-2.5 text-foreground"
                >
                    <MapPin
                        class="mt-0.5 size-4 shrink-0 text-muted-foreground"
                        aria-hidden="true"
                    />
                    <span class="whitespace-pre-line">{{
                        contact.address
                    }}</span>
                </li>
            </ul>
            <p v-else class="text-sm text-muted-foreground">
                Escríbele a tu asesor; te responderá a la brevedad.
            </p>
        </SectionCard>

        <Link
            href="/portal/garantias"
            class="flex items-center gap-3 rounded-xl border bg-card p-4 text-sm hover:border-primary/40 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
        >
            <ShieldAlert
                class="size-5 shrink-0 text-primary"
                aria-hidden="true"
            />
            <span>
                <span class="block font-medium text-foreground"
                    >¿Algo no está bien con tu mueble?</span
                >
                <span class="text-muted-foreground"
                    >Consulta tu garantía o reporta un problema.</span
                >
            </span>
        </Link>
    </PageContainer>
</template>
