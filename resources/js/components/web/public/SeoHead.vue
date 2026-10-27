<script lang="ts">
import { Head } from '@inertiajs/vue3';
import type { PropType, VNode } from 'vue';
import { defineComponent, h } from 'vue';
import type { ResponsiveImageData, SeoData } from '@/types/web';

/**
 * Título de una página pública y precarga opcional de la imagen del hero
 * (LCP).
 *
 * La descripción, canónica, robots, Open Graph, Twitter y JSON-LD NO se
 * declaran aquí: el servidor los escribe en el HTML inicial desde
 * `seo.head` (los rastreadores de WhatsApp/Facebook/Google no ejecutan JS)
 * y `createInertiaApp({ serverHead })` los actualiza al navegar con las
 * mismas claves `data-inertia`, sin duplicados.
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
            const nodes: VNode[] = [];

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

            return h(
                Head,
                { title: props.seo.fullTitle || props.seo.title },
                () => nodes,
            );
        };
    },
});
</script>
