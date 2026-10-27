<script lang="ts">
import { Head } from '@inertiajs/vue3';
import type { PropType, VNode } from 'vue';
import { defineComponent, h } from 'vue';
import type { ResponsiveImageData, SeoData } from '@/types/web';

/**
 * Metadatos SEO de una página pública: título, descripción, canónica, Open
 * Graph, Twitter y JSON-LD (ya serializado y escapado en el backend).
 * Opcionalmente precarga la imagen del hero (LCP).
 */
export default defineComponent({
    name: 'SeoHead',
    props: {
        seo: { type: Object as PropType<SeoData>, required: true },
        preloadImage: {
            type: Object as PropType<ResponsiveImageData | null>,
            default: null,
        },
    },
    setup(props) {
        return () => {
            const seo = props.seo;
            const nodes: VNode[] = [
                h('meta', {
                    name: 'description',
                    content: seo.description,
                    'head-key': 'description',
                }),
                h('meta', {
                    name: 'robots',
                    content: seo.robots,
                    'head-key': 'robots',
                }),
                h('link', {
                    rel: 'canonical',
                    href: seo.canonical,
                    'head-key': 'canonical',
                }),
                h('meta', {
                    property: 'og:type',
                    content: seo.type,
                    'head-key': 'og:type',
                }),
                h('meta', {
                    property: 'og:site_name',
                    content: seo.siteName,
                    'head-key': 'og:site_name',
                }),
                h('meta', {
                    property: 'og:locale',
                    content: seo.locale,
                    'head-key': 'og:locale',
                }),
                h('meta', {
                    property: 'og:title',
                    content: seo.fullTitle,
                    'head-key': 'og:title',
                }),
                h('meta', {
                    property: 'og:description',
                    content: seo.description,
                    'head-key': 'og:description',
                }),
                h('meta', {
                    property: 'og:url',
                    content: seo.canonical,
                    'head-key': 'og:url',
                }),
                h('meta', {
                    name: 'twitter:card',
                    content: seo.image ? 'summary_large_image' : 'summary',
                    'head-key': 'twitter:card',
                }),
                h('meta', {
                    name: 'twitter:title',
                    content: seo.fullTitle,
                    'head-key': 'twitter:title',
                }),
                h('meta', {
                    name: 'twitter:description',
                    content: seo.description,
                    'head-key': 'twitter:description',
                }),
            ];

            if (seo.image) {
                nodes.push(
                    h('meta', {
                        property: 'og:image',
                        content: seo.image,
                        'head-key': 'og:image',
                    }),
                );
                nodes.push(
                    h('meta', {
                        name: 'twitter:image',
                        content: seo.image,
                        'head-key': 'twitter:image',
                    }),
                );
            }
            if (seo.twitter) {
                nodes.push(
                    h('meta', {
                        name: 'twitter:site',
                        content: seo.twitter,
                        'head-key': 'twitter:site',
                    }),
                );
            }
            if (props.preloadImage) {
                const image = props.preloadImage;
                const avif = image.sources.find((s) => s.type === 'image/avif');
                nodes.push(
                    h('link', {
                        rel: 'preload',
                        as: 'image',
                        href: image.src,
                        imagesrcset: avif?.srcset ?? image.srcset ?? undefined,
                        imagesizes: image.sizes,
                        type: avif ? 'image/avif' : undefined,
                        fetchpriority: 'high',
                        'head-key': 'preload-hero',
                    }),
                );
            }
            if (seo.jsonLd) {
                nodes.push(
                    h(
                        'script',
                        { type: 'application/ld+json', 'head-key': 'jsonld' },
                        seo.jsonLd,
                    ),
                );
            }

            return h(Head, { title: seo.title }, () => nodes);
        };
    },
});
</script>
