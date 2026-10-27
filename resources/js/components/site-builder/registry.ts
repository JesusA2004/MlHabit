/**
 * Registro de bloques: componente que lo dibuja, nombre visible, ícono del
 * panel "Elementos" y tamaño inicial al soltarlo en el lienzo.
 */
import {
    BadgeCheck,
    ClipboardList,
    CreditCard,
    Hash,
    Heading,
    Image,
    LayoutDashboard,
    ListChecks,
    ListOrdered,
    MapPin,
    Megaphone,
    MessageCircleQuestion,
    MousePointerClick,
    Quote,
    Ruler,
    Shapes,
    Sparkles,
    SquareDashed,
    Tag,
    Type,
    Video,
    Images,
} from '@lucide/vue';
import type { Component } from 'vue';
import BadgeBlock from './blocks/BadgeBlock.vue';
import BenefitsListBlock from './blocks/BenefitsListBlock.vue';
import ButtonBlock from './blocks/ButtonBlock.vue';
import CategoriesBlock from './blocks/CategoriesBlock.vue';
import CoverageCheckerBlock from './blocks/CoverageCheckerBlock.vue';
import FaqBlock from './blocks/FaqBlock.vue';
import FeaturedProjectsBlock from './blocks/FeaturedProjectsBlock.vue';
import FinalCtaBlock from './blocks/FinalCtaBlock.vue';
import HeadingBlock from './blocks/HeadingBlock.vue';
import HomeVisitBlock from './blocks/HomeVisitBlock.vue';
import IconBlock from './blocks/IconBlock.vue';
import ImageBlock from './blocks/ImageBlock.vue';
import LeadFormCtaBlock from './blocks/LeadFormCtaBlock.vue';
import ListBlock from './blocks/ListBlock.vue';
import PaymentOptionsBlock from './blocks/PaymentOptionsBlock.vue';
import ProcessStepsBlock from './blocks/ProcessStepsBlock.vue';
import ShapeBlock from './blocks/ShapeBlock.vue';
import SpacerBlock from './blocks/SpacerBlock.vue';
import StatBlock from './blocks/StatBlock.vue';
import TestimonialsBlock from './blocks/TestimonialsBlock.vue';
import TextBlock from './blocks/TextBlock.vue';
import VideoBlock from './blocks/VideoBlock.vue';
import type { BlockType } from './types';

export type BlockDefinition = {
    label: string;
    icon: Component;
    component: Component;
    group: 'basic' | 'media' | 'decor' | 'dynamic';
    /** Tamaño inicial (columnas × filas). */
    size: [number, number];
    props: Record<string, unknown>;
    /** Marco visual: conserva la proporción en móvil. */
    frame?: boolean;
    /** Admite edición de texto con doble clic. */
    textProp?: string;
};

export const BLOCKS: Record<BlockType, BlockDefinition> = {
    heading: {
        label: 'Título',
        icon: Heading,
        component: HeadingBlock,
        group: 'basic',
        size: [6, 4],
        props: {
            text: 'Escribe un título',
            level: 'h2',
            size: 'lg',
            color: 'auto',
        },
        textProp: 'text',
    },
    text: {
        label: 'Texto',
        icon: Type,
        component: TextBlock,
        group: 'basic',
        size: [5, 4],
        props: {
            html: '<p>Escribe aquí un texto breve y claro.</p>',
            size: 'base',
            color: 'auto',
        },
        textProp: 'html',
    },
    button: {
        label: 'Botón',
        icon: MousePointerClick,
        component: ButtonBlock,
        group: 'basic',
        size: [3, 2],
        props: {
            label: 'Solicita una asesoría',
            href: '/solicitar',
            variant: 'primary',
            size: 'lg',
            icon: true,
        },
    },
    badge: {
        label: 'Etiqueta',
        icon: Tag,
        component: BadgeBlock,
        group: 'basic',
        size: [4, 1],
        props: { text: 'Hecho a la medida', variant: 'eyebrow', color: 'auto' },
        textProp: 'text',
    },
    list: {
        label: 'Lista',
        icon: ListChecks,
        component: ListBlock,
        group: 'basic',
        size: [5, 6],
        props: {
            items: [
                { text: 'Primer punto' },
                { text: 'Segundo punto' },
                { text: 'Tercer punto' },
            ],
            marker: 'check',
            color: 'auto',
        },
    },
    stat: {
        label: 'Cifra',
        icon: Hash,
        component: StatBlock,
        group: 'basic',
        size: [3, 4],
        props: { value: '', label: '', color: 'auto' },
    },
    image: {
        label: 'Imagen',
        icon: Image,
        component: ImageBlock,
        group: 'media',
        size: [5, 14],
        props: {
            mediaId: null,
            alt: '',
            fit: 'cover',
            focalX: 50,
            focalY: 50,
            zoom: 1,
            showOrigin: true,
        },
        frame: true,
    },
    video: {
        label: 'Video',
        icon: Video,
        component: VideoBlock,
        group: 'media',
        size: [6, 14],
        props: { mediaId: null, autoplay: true, loop: true, controls: false },
        frame: true,
    },
    icon: {
        label: 'Ícono',
        icon: Sparkles,
        component: IconBlock,
        group: 'decor',
        size: [1, 2],
        props: { name: 'sparkles', size: 'md', color: 'primary', framed: true },
    },
    shape: {
        label: 'Forma',
        icon: Shapes,
        component: ShapeBlock,
        group: 'decor',
        size: [4, 8],
        props: { kind: 'rect', color: 'primary-soft' },
        frame: true,
    },
    spacer: {
        label: 'Espacio',
        icon: SquareDashed,
        component: SpacerBlock,
        group: 'decor',
        size: [12, 2],
        props: {},
    },
    categories: {
        label: 'Categorías',
        icon: LayoutDashboard,
        component: CategoriesBlock,
        group: 'dynamic',
        size: [12, 30],
        props: {},
    },
    featured_projects: {
        label: 'Proyectos destacados',
        icon: Images,
        component: FeaturedProjectsBlock,
        group: 'dynamic',
        size: [12, 28],
        props: {},
    },
    process_steps: {
        label: 'Proceso',
        icon: ListOrdered,
        component: ProcessStepsBlock,
        group: 'dynamic',
        size: [6, 40],
        props: {},
    },
    benefits_list: {
        label: 'Beneficios',
        icon: BadgeCheck,
        component: BenefitsListBlock,
        group: 'dynamic',
        size: [6, 14],
        props: {},
    },
    payment_options: {
        label: 'Formas de pago',
        icon: CreditCard,
        component: PaymentOptionsBlock,
        group: 'dynamic',
        size: [6, 20],
        props: {},
    },
    home_visit: {
        label: 'Visita a domicilio',
        icon: Ruler,
        component: HomeVisitBlock,
        group: 'dynamic',
        size: [6, 20],
        props: {},
    },
    coverage_checker: {
        label: 'Cobertura',
        icon: MapPin,
        component: CoverageCheckerBlock,
        group: 'dynamic',
        size: [5, 8],
        props: {},
    },
    testimonials: {
        label: 'Testimonios',
        icon: Quote,
        component: TestimonialsBlock,
        group: 'dynamic',
        size: [12, 14],
        props: {},
    },
    faq: {
        label: 'Preguntas',
        icon: MessageCircleQuestion,
        component: FaqBlock,
        group: 'dynamic',
        size: [7, 24],
        props: {},
    },
    final_cta: {
        label: 'Llamada final',
        icon: Megaphone,
        component: FinalCtaBlock,
        group: 'dynamic',
        size: [12, 18],
        props: {},
    },
    lead_form_cta: {
        label: 'Invitación a solicitar',
        icon: ClipboardList,
        component: LeadFormCtaBlock,
        group: 'dynamic',
        size: [12, 7],
        props: {},
    },
};

export function blockLabel(type: string): string {
    return BLOCKS[type as BlockType]?.label ?? 'Elemento';
}
