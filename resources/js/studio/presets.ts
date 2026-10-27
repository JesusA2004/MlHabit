import { createElement, createSection } from './tree';
import type { MotionConfig, StudioNode } from './types';

/**
 * Biblioteca de composiciones premium. Todas usan los mismos tokens
 * (tonos, escala tipográfica, espaciados) y se pueden modificar después.
 *
 * `thumb`: rectángulos [x, y, w, h, tipo] en un lienzo 100×64 para la
 * miniatura esquemática (PresetThumb.vue).
 */

export type ThumbRect = [number, number, number, number, 'title' | 'text' | 'media' | 'accent' | 'card'];

export interface Preset {
    id: string;
    group: string;
    label: string;
    description: string;
    thumb: ThumbRect[];
    build: () => StudioNode;
}

export const PRESET_GROUPS: Record<string, string> = {
    hero: 'Hero',
    articles: 'Artículos',
    gallery: 'Galerías',
    story: 'Historia',
    layout: 'Estructura',
    editorial: 'Bloques editoriales',
    closing: 'Cierre y redes',
};

const motion = (preset: string, delay = 0): MotionConfig => ({
    preset,
    duration: 800,
    delay,
    distance: 24,
    trigger: 'scroll',
    mobile: 'reduce',
    once: true,
});

const el = (type: string, props: Record<string, unknown> = {}, extra: Partial<StudioNode> = {}) => ({ ...createElement(type, props), ...extra });

const heroContent = (opts: { eyebrow?: string; title?: string; text?: string; center?: boolean } = {}) => [
    el('eyebrow', { text: opts.eyebrow ?? 'SIN TAPUJOS.' }),
    el('heading', { text: opts.title ?? 'NEGOCIOS.\nHÁBITOS.\nVIDA.', level: 'h1', size: 'xl' }, { motion: motion('slide-up') }),
    el('text', { text: opts.text ?? 'Ideas prácticas para construir una vida con intención, sin rodeos.', size: 'lead' }, { motion: motion('fade', 150) }),
    el('buttons', {
        items: [
            { label: 'Explora los tips', link: { type: 'page', value: '/tips', newTab: false }, variant: 'primary' },
            { label: 'Mi historia', link: { type: 'page', value: '/mi-historia', newTab: false }, variant: 'secondary' },
        ],
    }, { motion: motion('fade', 250) }),
];

const image = (ratio = '4/5', extra: Record<string, unknown> = {}) =>
    el('image', { alt: '' }, { style: { base: { aspectRatio: ratio, ...extra } }, motion: motion('reveal', 100) });

export const PRESETS: Preset[] = [
    // ------------------------------- HERO -------------------------------
    {
        id: 'hero-editorial', group: 'hero', label: 'Editorial clásico', description: 'Titular grande a la izquierda, retrato a la derecha.',
        thumb: [[6, 18, 40, 8, 'title'], [6, 30, 34, 8, 'title'], [6, 44, 20, 5, 'accent'], [58, 8, 36, 48, 'media']],
        build: () => createSection('hero', { layout: 'editorial', spacing: 'editorial', height: 'tall' }, { content: heroContent(), media: [image()] }),
    },
    {
        id: 'hero-cinematic', group: 'hero', label: 'Cinematográfico', description: 'Fotografía o video a pantalla completa con texto superpuesto.',
        thumb: [[0, 0, 100, 64, 'media'], [8, 36, 50, 8, 'title'], [8, 48, 22, 5, 'accent']],
        build: () => createSection('hero', { layout: 'cinematic', height: 'screen', overlay: 0.45 }, { content: heroContent(), media: [el('image', { alt: '', priority: true })] }),
    },
    {
        id: 'hero-minimal', group: 'hero', label: 'Minimal premium', description: 'Titular centrado, mucho aire y CTA único.',
        thumb: [[22, 16, 56, 9, 'title'], [30, 29, 40, 9, 'title'], [40, 44, 20, 5, 'accent']],
        build: () => createSection('hero', { layout: 'centered', spacing: 'cinematic' }, {
            content: [
                el('eyebrow', { text: 'MR. INSIGHT' }),
                el('heading', { text: 'Pensar claro.\nVivir mejor.', level: 'h1', size: 'xl' }, { motion: motion('slide-up') }),
                el('buttons', { items: [{ label: 'Empieza aquí', link: { type: 'page', value: '/tips', newTab: false }, variant: 'primary' }] }),
            ],
            media: [],
        }),
    },
    {
        id: 'hero-framed', group: 'hero', label: 'Fotografía protagonista', description: 'Retrato en marco de arco con texto al lado.',
        thumb: [[8, 6, 30, 52, 'media'], [48, 20, 44, 8, 'title'], [48, 32, 36, 4, 'text'], [48, 42, 18, 5, 'accent']],
        build: () => createSection('hero', { layout: 'framed', spacing: 'editorial' }, { content: heroContent(), media: [image('3/4')] }),
    },
    {
        id: 'hero-video', group: 'hero', label: 'Video principal', description: 'Titular centrado y video destacado con portada.',
        thumb: [[25, 4, 50, 7, 'title'], [8, 18, 84, 42, 'media']],
        build: () => createSection('hero', { layout: 'video', spacing: 'balanced' }, {
            content: [el('eyebrow', { text: 'EN VIDEO' }), el('heading', { text: 'Lo que aprendí en 365 días.', level: 'h1', size: 'lg' })],
            media: [el('video', { controls: true })],
        }),
    },
    {
        id: 'hero-split', group: 'hero', label: 'Split moderno', description: 'Pantalla dividida 50/50 de borde a borde.',
        thumb: [[0, 0, 50, 64, 'card'], [6, 22, 36, 8, 'title'], [6, 36, 20, 5, 'accent'], [50, 0, 50, 64, 'media']],
        build: () => createSection('hero', { layout: 'split', height: 'tall', tone: 'ink-soft' }, { content: heroContent(), media: [el('image', { alt: '', priority: true })] }),
    },
    {
        id: 'hero-asymmetric', group: 'hero', label: 'Asimétrico', description: 'Imagen sangrada a la izquierda, titular anclado abajo.',
        thumb: [[0, 4, 54, 56, 'media'], [60, 30, 34, 8, 'title'], [60, 44, 20, 5, 'accent']],
        build: () => createSection('hero', { layout: 'asymmetric', spacing: 'editorial' }, { content: heroContent(), media: [image('4/5')] }),
    },

    // ----------------------------- ARTÍCULOS ----------------------------
    {
        id: 'posts-cards', group: 'articles', label: 'Tarjetas editoriales', description: 'Últimos tips en tarjetas con imagen.',
        thumb: [[4, 4, 40, 6, 'title'], [4, 16, 28, 26, 'media'], [36, 16, 28, 26, 'media'], [68, 16, 28, 26, 'media'], [4, 46, 24, 4, 'text'], [36, 46, 24, 4, 'text'], [68, 46, 24, 4, 'text']],
        build: () => createSection('posts', { layout: 'cards', limit: 6 }, {}),
    },
    {
        id: 'posts-grid3', group: 'articles', label: 'Grid tres columnas', description: 'Tres tips destacados sin extracto.',
        thumb: [[4, 10, 28, 36, 'media'], [36, 10, 28, 36, 'media'], [68, 10, 28, 36, 'media']],
        build: () => createSection('posts', { layout: 'cards', limit: 3, source: 'featured', showExcerpt: false, eyebrow: 'DESTACADOS', title: 'LO ESENCIAL.' }, {}),
    },
    {
        id: 'posts-magazine', group: 'articles', label: 'Revista asimétrica', description: 'Uno grande y dos secundarios, como portada de revista.',
        thumb: [[4, 4, 54, 56, 'media'], [62, 4, 34, 26, 'media'], [62, 34, 34, 26, 'media']],
        build: () => createSection('posts', { layout: 'magazine', limit: 5 }, {}),
    },
    {
        id: 'posts-spotlight', group: 'articles', label: 'Destacado + secundarios', description: 'Portada a lo ancho con tres tips debajo.',
        thumb: [[4, 4, 92, 30, 'media'], [4, 38, 28, 22, 'media'], [36, 38, 28, 22, 'media'], [68, 38, 28, 22, 'media']],
        build: () => createSection('posts', { layout: 'spotlight', limit: 4 }, {}),
    },
    {
        id: 'posts-carousel', group: 'articles', label: 'Carrusel', description: 'Deslizable en móvil, con botones accesibles.',
        thumb: [[4, 8, 32, 46, 'media'], [40, 8, 32, 46, 'media'], [76, 8, 24, 46, 'media']],
        build: () => createSection('posts', { layout: 'carousel', limit: 8 }, {}),
    },
    {
        id: 'posts-list', group: 'articles', label: 'Índice numerado', description: 'Lista editorial con números grandes.',
        thumb: [[4, 8, 8, 6, 'accent'], [16, 8, 60, 6, 'title'], [4, 26, 8, 6, 'accent'], [16, 26, 60, 6, 'title'], [4, 44, 8, 6, 'accent'], [16, 44, 60, 6, 'title']],
        build: () => createSection('posts', { layout: 'list', limit: 5 }, {}),
    },

    // ----------------------------- GALERÍAS -----------------------------
    ...(['grid', 'bento', 'masonry', 'carousel', 'spotlight'] as const).map((layout): Preset => ({
        id: `gallery-${layout}`, group: 'gallery',
        label: { grid: 'Grid', bento: 'Bento', masonry: 'Masonry', carousel: 'Carrusel', spotlight: 'Fotografía protagonista' }[layout],
        description: 'Fotografías de la biblioteca multimedia.',
        thumb: {
            grid: [[4, 4, 28, 26, 'media'], [36, 4, 28, 26, 'media'], [68, 4, 28, 26, 'media'], [4, 34, 28, 26, 'media'], [36, 34, 28, 26, 'media'], [68, 34, 28, 26, 'media']],
            bento: [[4, 4, 44, 56, 'media'], [52, 4, 20, 26, 'media'], [76, 4, 20, 26, 'media'], [52, 34, 44, 26, 'media']],
            masonry: [[4, 4, 28, 34, 'media'], [36, 4, 28, 22, 'media'], [68, 4, 28, 40, 'media'], [4, 42, 28, 18, 'media'], [36, 30, 28, 30, 'media']],
            carousel: [[4, 8, 36, 48, 'media'], [44, 8, 36, 48, 'media'], [84, 8, 16, 48, 'media']],
            spotlight: [[4, 4, 92, 40, 'media'], [4, 48, 14, 12, 'media'], [22, 48, 14, 12, 'media'], [40, 48, 14, 12, 'media']],
        }[layout] as ThumbRect[],
        build: () => createSection('gallery', { layout, eyebrow: 'GALERÍA', title: '' }, {}),
    })),

    // ------------------------------ HISTORIA ----------------------------
    {
        id: 'story-image-text', group: 'story', label: 'Imagen y texto', description: 'Fotografía con marco y relato al lado.',
        thumb: [[4, 6, 40, 52, 'media'], [52, 16, 40, 7, 'title'], [52, 28, 40, 4, 'text'], [52, 35, 36, 4, 'text']],
        build: () => createSection('columns', { layout: 'two-wide-right', spacing: 'editorial' }, {
            col1: [image('4/5', { frame: 'offset', radius: 16 })],
            col2: [el('eyebrow', { text: 'MI HISTORIA' }), el('heading', { text: 'De dónde vengo.' }), el('text', { text: 'Cuenta aquí el capítulo que quieres que se recuerde.' })],
        }, ),
    },
    {
        id: 'story-timeline', group: 'story', label: 'Timeline', description: 'Entradas del CMS (Mi proceso) en línea de tiempo.',
        thumb: [[49, 2, 2, 60, 'accent'], [8, 8, 34, 10, 'card'], [58, 26, 34, 10, 'card'], [8, 44, 34, 10, 'card']],
        build: () => createSection('timeline', { layout: 'alternating', source: 'entries', page: 'mi-proceso', eyebrow: 'MI PROCESO', title: 'Día a día.' }, {}),
    },
    {
        id: 'story-fullscreen', group: 'story', label: 'Pantalla completa', description: 'Una frase sobre fotografía a sangre.',
        thumb: [[0, 0, 100, 64, 'media'], [10, 28, 60, 9, 'title']],
        build: () => createSection('hero', { layout: 'cinematic', height: 'screen' }, {
            content: [el('heading', { text: 'Todo empezó con una decisión.', level: 'h2', size: 'xl' }, { motion: motion('slide-up') })],
            media: [el('image', { alt: '' })],
        }),
    },
    {
        id: 'story-chapters', group: 'story', label: 'Capítulos visuales', description: 'Capítulos con imagen alternada.',
        thumb: [[4, 4, 50, 26, 'media'], [60, 10, 34, 6, 'title'], [46, 34, 50, 26, 'media'], [6, 40, 34, 6, 'title']],
        build: () => createSection('timeline', {
            layout: 'chapters', source: 'manual', eyebrow: 'CAPÍTULOS', title: 'Mi camino.',
            items: [
                { label: 'El inicio', title: 'Primer capítulo', text: 'Describe este momento.', media: null },
                { label: 'El cambio', title: 'Segundo capítulo', text: 'Lo que aprendiste.', media: null },
            ],
        }, {}),
    },

    // ----------------------------- ESTRUCTURA ---------------------------
    {
        id: 'layout-one', group: 'layout', label: 'Una columna de texto', description: 'Lectura cómoda, ancho estrecho.',
        thumb: [[25, 8, 50, 7, 'title'], [25, 22, 50, 4, 'text'], [25, 30, 50, 4, 'text'], [25, 38, 40, 4, 'text']],
        build: () => createSection('columns', { layout: 'one', width: 'narrow' }, { col1: [el('heading', { text: 'Un titular con intención.', size: 'md' }), el('text')] }),
    },
    {
        id: 'layout-two', group: 'layout', label: 'Dos columnas', description: 'Dos ideas en paralelo.',
        thumb: [[4, 10, 42, 6, 'title'], [4, 22, 42, 4, 'text'], [54, 10, 42, 6, 'title'], [54, 22, 42, 4, 'text']],
        build: () => createSection('columns', { layout: 'two' }, {
            col1: [el('heading', { text: 'Primera idea.', size: 'title', level: 'h3' }), el('text')],
            col2: [el('heading', { text: 'Segunda idea.', size: 'title', level: 'h3' }), el('text')],
        }),
    },
    {
        id: 'layout-three', group: 'layout', label: 'Tres columnas', description: 'Tres pilares como tarjetas.',
        thumb: [[4, 10, 28, 44, 'card'], [36, 10, 28, 44, 'card'], [68, 10, 28, 44, 'card']],
        build: () => createSection('columns', { layout: 'three', cards: true }, {
            col1: [el('eyebrow', { text: '01' }), el('heading', { text: 'Negocios', size: 'title', level: 'h3' }), el('text', { size: 'small' })],
            col2: [el('eyebrow', { text: '02' }), el('heading', { text: 'Hábitos', size: 'title', level: 'h3' }), el('text', { size: 'small' })],
            col3: [el('eyebrow', { text: '03' }), el('heading', { text: 'Vida', size: 'title', level: 'h3' }), el('text', { size: 'small' })],
        }),
    },
    {
        id: 'layout-bento', group: 'layout', label: 'Bento', description: 'Una pieza grande y dos de apoyo.',
        thumb: [[4, 4, 54, 56, 'media'], [62, 4, 34, 26, 'card'], [62, 34, 34, 26, 'card']],
        build: () => createSection('columns', { layout: 'bento', cards: true }, {
            col1: [image('4/3')],
            col2: [el('heading', { text: 'Idea clave.', size: 'title', level: 'h3' }), el('text', { size: 'small' })],
            col3: [el('quote', { variant: 'classic' })],
        }),
    },
    {
        id: 'layout-media-text', group: 'layout', label: 'Video y texto', description: 'Video de la biblioteca junto a una explicación.',
        thumb: [[4, 8, 54, 34, 'media'], [64, 14, 32, 6, 'title'], [64, 26, 32, 4, 'text']],
        build: () => createSection('columns', { layout: 'two-wide-left' }, {
            col1: [el('video', { controls: true }, { style: { base: { aspectRatio: '16/9' } } })],
            col2: [el('eyebrow', { text: 'EN VIDEO' }), el('heading', { text: 'Míralo en un minuto.', size: 'md' }), el('text')],
        }, ),
    },

    // ------------------------- BLOQUES EDITORIALES ----------------------
    {
        id: 'editorial-quote', group: 'editorial', label: 'Cita destacada', description: 'Una frase a gran formato.',
        thumb: [[10, 10, 6, 10, 'accent'], [10, 24, 80, 8, 'title'], [10, 36, 60, 8, 'title']],
        build: () => createSection('columns', { layout: 'one', width: 'narrow', spacing: 'editorial' }, { col1: [el('quote', { variant: 'large', author: 'Zuriel Ávila' }, { motion: motion('fade') })] }),
    },
    {
        id: 'editorial-question', group: 'editorial', label: 'Pregunta al lector', description: 'Invita a reflexionar.',
        thumb: [[16, 12, 68, 40, 'card'], [24, 22, 20, 4, 'accent'], [24, 32, 50, 6, 'title']],
        build: () => createSection('columns', { layout: 'one', width: 'narrow' }, { col1: [el('question')] }),
    },
    {
        id: 'editorial-categories', group: 'editorial', label: 'Empieza aquí', description: 'Una puerta por categoría con su tip destacado.',
        thumb: [[4, 4, 40, 6, 'title'], [4, 16, 28, 40, 'card'], [36, 16, 28, 40, 'card'], [68, 16, 28, 40, 'card']],
        build: () => createSection('categories', { layout: 'cards' }, {}),
    },

    // ---------------------------- CIERRE --------------------------------
    {
        id: 'cta-band', group: 'closing', label: 'Banda dorada', description: 'Mensaje de cierre a todo lo ancho.',
        thumb: [[0, 8, 100, 48, 'accent'], [25, 22, 50, 7, 'title'], [40, 36, 20, 5, 'card']],
        build: () => createSection('cta', { layout: 'band', tone: 'gold' }, {
            content: [el('heading', { text: '¿Listo para empezar?', size: 'lg' }), el('buttons', { items: [{ label: 'Sígueme en Instagram', link: { type: 'social', value: 'instagram', newTab: true }, variant: 'primary' }] })],
            media: [],
        }),
    },
    {
        id: 'cta-card', group: 'closing', label: 'Tarjeta', description: 'Invitación enmarcada.',
        thumb: [[10, 6, 80, 52, 'card'], [25, 20, 50, 7, 'title'], [40, 34, 20, 5, 'accent']],
        build: () => createSection('cta', { layout: 'card' }, {
            content: [el('eyebrow', { text: 'COMUNIDAD' }), el('heading', { text: 'Sin tapujos, cada semana.', size: 'md' }), el('buttons')],
            media: [],
        }),
    },
    {
        id: 'cta-split', group: 'closing', label: 'Split con imagen', description: 'Mensaje junto a fotografía.',
        thumb: [[4, 16, 40, 7, 'title'], [4, 30, 18, 5, 'accent'], [52, 6, 44, 52, 'media']],
        build: () => createSection('cta', { layout: 'split' }, {
            content: [el('heading', { text: 'Hablemos.', size: 'lg' }), el('text'), el('buttons')],
            media: [image('4/3')],
        }),
    },
    {
        id: 'social-cards', group: 'closing', label: 'Redes sociales', description: 'Tarjetas con tus redes del CMS.',
        thumb: [[4, 10, 28, 20, 'card'], [36, 10, 28, 20, 'card'], [68, 10, 28, 20, 'card']],
        build: () => createSection('social', { layout: 'cards', title: 'Sígueme donde prefieras.' }, {}),
    },
];
