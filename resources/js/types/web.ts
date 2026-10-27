/**
 * Tipos del módulo web (sitio público, CMS, catálogo y multimedia). Reflejan
 * los arreglos explícitos que envían los controladores de Laravel.
 */
import type { SiteData } from '@/types/public';

export type ResponsiveImageData = {
    src: string;
    srcset: string | null;
    sources: { type: string; srcset: string }[];
    sizes: string;
    width: number | null;
    height: number | null;
    alt: string;
    origin: 'render' | 'real_photo' | null;
    originLabel: string | null;
    placeholder: string | null;
};

export type GalleryImage = ResponsiveImageData & {
    id: number;
    caption: string | null;
};

export type PublicSiteData = SiteData & {
    logoDarkUrl?: string | null;
    faviconUrl?: string | null;
    ogImageUrl?: string | null;
    contact?: SiteData['contact'] & {
        phoneHref?: string;
        whatsappHref?: string;
        emailHref?: string;
    };
    categories?: { label: string; href: string }[];
    footer?: { text: string | null; legalName: string | null };
    cta?: { label: string; href: string };
};

export type SeoData = {
    title: string;
    fullTitle: string;
    description: string;
    canonical: string;
    image: string | null;
    type: string;
    siteName: string;
    locale: string;
    twitter: string | null;
    robots: string;
    jsonLd: string | null;
};

export type PriceRange = { from: string; to: string | null; currency: string };

export type CategoryCardData = {
    id: number;
    name: string;
    slug: string;
    tagline: string | null;
    href: string;
    isFeatured: boolean;
    image: ResponsiveImageData | null;
    productsCount: number | null;
};

export type ProductCardData = {
    id: number;
    name: string;
    slug: string;
    summary: string | null;
    href: string;
    category: { name: string; slug: string };
    image: ResponsiveImageData | null;
    price: PriceRange | null;
};

export type EditorialItem = { title: string; body: string | null };

export type FinishData = {
    id: number;
    name: string;
    material: string | null;
    colorHex: string | null;
    description: string | null;
    image: ResponsiveImageData | null;
};

export type ProductDetailData = ProductCardData & {
    description: string | null;
    benefits: EditorialItem[];
    features: EditorialItem[];
    gallery: GalleryImage[];
    finishes: FinishData[];
    tags: { name: string; slug: string }[];
};

export type ShowcaseCardData = {
    id: number;
    title: string;
    slug: string;
    summary: string | null;
    href: string;
    origin: 'render' | 'real_photo';
    originLabel: string;
    locationLabel: string | null;
    category: { name: string; slug: string; href?: string } | null;
    image: ResponsiveImageData | null;
};

export type ShowcaseDetailData = ShowcaseCardData & {
    story: string | null;
    gallery: GalleryImage[];
};

export type FaqData = {
    id: number;
    question: string;
    answer: string;
    topic: string;
    topicLabel: string;
};

export type TestimonialData = {
    id: number;
    authorName: string;
    authorContext: string | null;
    quote: string;
    image: ResponsiveImageData | null;
    project: { title: string; href: string } | null;
};

export type VisitPolicyData = {
    fee: string;
    feeFormatted: string;
    currency: string;
    isCreditable: boolean;
    creditConditions: string | null;
};

/** Contenido de sección: claves definidas por el esquema del backend. */
export type SectionContent = Record<string, unknown>;

export type HomeSection = {
    id: number;
    type:
        | 'hero'
        | 'categories'
        | 'featured_projects'
        | 'process'
        | 'benefits'
        | 'payment_options'
        | 'home_visit'
        | 'coverage'
        | 'testimonials'
        | 'faq'
        | 'final_cta';
    content: SectionContent;
    image?: ResponsiveImageData | null;
    items?: unknown[];
    visit?: VisitPolicyData | null;
};

export type SimplePaginator<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    next_page_url: string | null;
    prev_page_url: string | null;
};

/* --------------------------------------------------------------------- */
/* Panel                                                                  */
/* --------------------------------------------------------------------- */

export type MediaRef = {
    id: number;
    url: string | null;
    thumbnailUrl: string | null;
    filename: string;
    type: string;
    altText: string | null;
    origin: 'render' | 'real_photo' | null;
    originLabel: string | null;
    width: number | null;
    height: number | null;
};

export type MediaItem = MediaRef & {
    uuid: string;
    visibility: 'public' | 'private';
    status: 'uploading' | 'processing' | 'ready' | 'failed';
    mimeType: string;
    size: number;
    durationSeconds: number | null;
    posterUrl: string | null;
    title: string | null;
    description: string | null;
    collection: { id: number; name: string; path: string } | null;
    variants: {
        type: string;
        width: number | null;
        height: number | null;
        size: number | null;
    }[];
    createdAt: string | null;
    updatedAt: string | null;
};

export type GalleryEditorItem = MediaRef & { caption: string | null };

export type Option<V = string | number> = { value: V; label: string };

export type ToneOption = { value: string; label: string; tone: string };

export type SchemaField = {
    key: string;
    kind:
        | 'text'
        | 'textarea'
        | 'link'
        | 'media'
        | 'number'
        | 'toggle'
        | 'list'
        | 'topics';
    label: string;
    help?: string;
    required?: boolean;
    max?: number;
    min?: number;
    itemFields?: SchemaField[];
    options?: { value: string; label: string }[];
};

export type SectionSchema = {
    label: string;
    description: string;
    fields: SchemaField[];
};

export type AdminSection = {
    id: number;
    type: HomeSection['type'];
    sort: number;
    isVisible: boolean;
    content: SectionContent;
    schema: SectionSchema;
    updatedAt: string | null;
    updatedBy: string | null;
};

export type UploadLimits = {
    maxImageMb: number;
    maxDocumentMb: number;
    maxUploadMb: number;
    chunkSizeMb: number;
    extensions: string[];
    accept: string;
};
