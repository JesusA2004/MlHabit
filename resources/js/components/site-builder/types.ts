/**
 * Tipos del editor visual (lienzos de sección con bloques en una retícula de
 * 12 columnas). Reflejan App\Cms\Layout\* del backend.
 */
import type {
    MediaRef,
    ResponsiveImageData,
    SchemaField,
    VisitPolicyData,
} from '@/types/web';

export type Align = 'start' | 'center' | 'end';
export type Device = 'desktop' | 'tablet' | 'mobile';
export type MotionPreset =
    | 'none'
    | 'fade-up'
    | 'fade-in'
    | 'zoom-in'
    | 'slide-left'
    | 'slide-right'
    | 'blur-in'
    | 'mask-reveal'
    | 'settle';

export type StaticBlockType =
    | 'heading'
    | 'text'
    | 'button'
    | 'image'
    | 'icon'
    | 'badge'
    | 'shape'
    | 'spacer'
    | 'video'
    | 'stat'
    | 'list';

export type DynamicBlockType =
    | 'categories'
    | 'featured_projects'
    | 'process_steps'
    | 'benefits_list'
    | 'payment_options'
    | 'home_visit'
    | 'coverage_checker'
    | 'testimonials'
    | 'faq'
    | 'final_cta'
    | 'lead_form_cta';

export type BlockType = StaticBlockType | DynamicBlockType;

export type BlockStyle = {
    align: Align;
    radius: 'none' | 'sm' | 'md' | 'lg' | 'xl' | '2xl' | 'full';
    shadow: 'none' | 'sm' | 'md' | 'lg';
    opacity: number;
    rotate: number;
};

export type BlockMotion = { preset: MotionPreset; delay: number };

export type Block = {
    id: string;
    type: BlockType;
    x: number;
    y: number;
    w: number;
    h: number;
    z: number;
    props: Record<string, unknown>;
    style: BlockStyle;
    motion: BlockMotion;
    hideOn: ('tablet' | 'mobile')[];
    locked: boolean;
};

export type SectionBackground = {
    kind: 'none' | 'color' | 'image' | 'gradient';
    color: string;
    mediaId: number | null;
    overlay: number;
    overlayStyle: 'flat' | 'left' | 'bottom';
    gradient: string;
};

export type SectionSettings = {
    name: string;
    background: SectionBackground;
    padding: 'none' | 'sm' | 'md' | 'lg' | 'xl' | 'hero';
    minHeight: 'auto' | 'sm' | 'md' | 'lg' | 'screen';
    verticalAlign: Align;
    fullBleed: boolean;
    surface: 'none' | 'panel';
    anchor: string;
    tone: 'auto' | 'light' | 'dark';
    overlayHeader: boolean;
    /** Paso del recorrido guiado (data-journey). */
    journey:
        | ''
        | 'inspirate'
        | 'espacio'
        | 'proceso'
        | 'visita'
        | 'pagos'
        | 'agenda';
};

export type SectionLayout = {
    version: number;
    settings: SectionSettings;
    blocks: Block[];
};

export type VideoData = {
    src: string | null;
    mime: string | null;
    poster: string | null;
    width: number | null;
    height: number | null;
};

/** Datos vivos resueltos por el servidor para un bloque dinámico. */
export type BlockData = {
    items?: unknown[];
    visit?: VisitPolicyData | null;
    image?: ResponsiveImageData | null;
    empty?: boolean;
};

/** Sección lista para renderizar (publicada, borrador o vista previa). */
export type RenderSection = {
    id: number | null;
    key: string;
    type: string;
    isVisible: boolean;
    content: Record<string, unknown>;
    layout: SectionLayout;
    images: Record<string, ResponsiveImageData | null>;
    videos: Record<string, VideoData>;
    data: Record<string, BlockData>;
};

export type DynamicDescriptor = {
    label: string;
    liveList: boolean;
    source: string | null;
    fields: SchemaField[];
    defaults: Record<string, unknown>;
};

export type SectionTemplate = {
    id: string;
    label: string;
    description: string;
    icon: string;
    type: string;
    content: Record<string, unknown>;
    layout: SectionLayout;
};

export type VersionRow = {
    id: number;
    number: number;
    note: string | null;
    publishedAt: string;
    publishedBy: string | null;
    sections: number;
};

export type DraftMeta = {
    revision: number;
    savedAt: string | null;
    savedBy: string | null;
    hasChanges: boolean;
};

export type LibraryMap = Record<string, MediaRef | null>;
