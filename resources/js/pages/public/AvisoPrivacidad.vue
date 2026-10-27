<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import PostBody from '@/components/public/PostBody.vue';
import type { TiptapContent } from '@/types';

const props = defineProps<{
    page: {
        title: string;
        intro: string | null;
        body: TiptapContent | null;
        updatedAt?: string | null;
        seoDescription?: string | null;
    };
}>();

const hasBody = computed(() => {
    const content = (props.page.body as { content?: unknown[] } | null)?.content;

    return Array.isArray(content) && content.length > 0;
});

const updated = computed(() =>
    props.page.updatedAt ? new Intl.DateTimeFormat('es-MX', { day: 'numeric', month: 'long', year: 'numeric' }).format(new Date(props.page.updatedAt)) : null,
);
</script>

<template>
    <Head :title="page.title">
        <meta v-if="page.seoDescription" head-key="description" name="description" :content="page.seoDescription" />
    </Head>

    <section class="tone-ink pt-16 pb-24 lg:pt-24">
        <div class="container-editorial container-editorial--narrow">
            <p class="sn-eyebrow type-eyebrow">Legal</p>
            <h1 class="type-display-md mt-5 text-paper">{{ page.title }}</h1>
            <p v-if="updated" class="mt-3 text-sm text-mist">Última actualización: {{ updated }}</p>
            <p v-if="page.intro" class="type-lead mt-8 text-paper/80">{{ page.intro }}</p>
            <div class="mt-10 border-t border-line pt-10">
                <PostBody v-if="hasBody" :content="page.body" />
                <p v-else class="text-mist">El texto completo de este aviso se publicará en breve.</p>
            </div>
        </div>
    </section>
</template>
