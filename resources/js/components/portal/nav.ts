import {
    CalendarDays,
    ClipboardList,
    FileText,
    House,
    LifeBuoy,
    PencilRuler,
    ReceiptText,
    ShieldCheck,
    Wallet,
} from '@lucide/vue';
import type { TabNavItem } from '@/types/navigation';

/**
 * Navegación del portal del cliente. Barra lateral agrupada en escritorio;
 * en móvil, cuatro pestañas + "Más" (hoja inferior con el resto).
 */
export type PortalNavItem = TabNavItem & { description?: string };

export type PortalNavGroup = { label?: string; items: PortalNavItem[] };

export const portalNavGroups: PortalNavGroup[] = [
    {
        items: [
            {
                title: 'Inicio',
                href: '/portal',
                icon: House,
                exact: true,
                description: 'Tu resumen',
            },
        ],
    },
    {
        label: 'Seguimiento',
        items: [
            {
                title: 'Solicitudes',
                href: '/portal/solicitudes',
                icon: ClipboardList,
                description: 'Lo que nos pediste',
            },
            {
                title: 'Citas',
                href: '/portal/citas',
                icon: CalendarDays,
                description: 'Asesorías y visitas',
            },
            {
                title: 'Pagos',
                href: '/portal/pagos',
                icon: Wallet,
                description: 'Pagos y comprobantes',
            },
        ],
    },
    {
        label: 'Tu proyecto',
        items: [
            {
                title: 'Cotizaciones',
                href: '/portal/cotizaciones',
                icon: ReceiptText,
                description: 'Revisa y aprueba',
            },
            {
                title: 'Diseños',
                href: '/portal/disenos',
                icon: PencilRuler,
                description: 'Propuestas de diseño',
            },
            {
                title: 'Documentos',
                href: '/portal/documentos',
                icon: FileText,
                description: 'Contratos y archivos',
            },
        ],
    },
    {
        label: 'Soporte',
        items: [
            {
                title: 'Garantías',
                href: '/portal/garantias',
                icon: ShieldCheck,
                match: '/portal/garantias',
                description: 'Garantías e incidencias',
            },
            {
                title: 'Ayuda',
                href: '/portal/ayuda',
                icon: LifeBuoy,
                description: 'Contacto y preguntas',
            },
        ],
    },
];

export const portalNavItems: PortalNavItem[] = portalNavGroups.flatMap(
    (g) => g.items,
);

/** Pestañas fijas de la barra inferior (el resto vive en "Más"). */
export const portalTabHrefs = [
    '/portal',
    '/portal/solicitudes',
    '/portal/citas',
    '/portal/pagos',
];

/** Rutas que pertenecen a una sección aunque su URL no empiece igual. */
export function portalSectionFor(url: string): string | null {
    const path = url.split(/[?#]/)[0];

    if (path.startsWith('/portal/incidencias')) {
        return '/portal/garantias';
    }

    if (path.startsWith('/portal/instalaciones')) {
        return '/portal';
    }

    return null;
}
