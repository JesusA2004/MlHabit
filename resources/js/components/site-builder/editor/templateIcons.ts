import {
    BadgeCheck,
    CircleHelp,
    CreditCard,
    Images,
    LayoutDashboard,
    LayoutGrid,
    LayoutPanelLeft,
    ListOrdered,
    MapPin,
    Megaphone,
    PanelTop,
    Quote,
    Ruler,
    SquareDashed,
} from '@lucide/vue';
import type { Component } from 'vue';

/** Íconos de plantillas y tipos de sección (nombres de App\Cms\Layout\Templates). */
const ICONS: Record<string, Component> = {
    hero: PanelTop,
    'panel-top': PanelTop,
    text_image: LayoutPanelLeft,
    'layout-panel-left': LayoutPanelLeft,
    gallery: LayoutGrid,
    'layout-grid': LayoutGrid,
    categories: LayoutDashboard,
    'layout-dashboard': LayoutDashboard,
    featured_projects: Images,
    images: Images,
    process: ListOrdered,
    'list-ordered': ListOrdered,
    benefits: BadgeCheck,
    'badge-check': BadgeCheck,
    payment_options: CreditCard,
    'credit-card': CreditCard,
    home_visit: Ruler,
    ruler: Ruler,
    coverage: MapPin,
    'map-pin': MapPin,
    testimonials: Quote,
    quote: Quote,
    faq: CircleHelp,
    'circle-help': CircleHelp,
    final_cta: Megaphone,
    megaphone: Megaphone,
    canvas: SquareDashed,
    blank: SquareDashed,
    'square-dashed': SquareDashed,
};

export function templateIcon(name: string): Component {
    return ICONS[name] ?? SquareDashed;
}
