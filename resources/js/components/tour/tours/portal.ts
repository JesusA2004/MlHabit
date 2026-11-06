import { portalNavItems } from '@/components/portal/nav';
import { navTourKey, tabTourKey } from '../keys';
import type { HelpModule, TourDefinition, TourStep } from '../types';
import { card, detail, exact, header, note } from './helpers';

/**
 * Ayuda y recorridos del portal del cliente (/portal). Textos en tono
 * cercano: el cliente no conoce los términos internos del equipo.
 */
const iconFor = (href: string) =>
    portalNavItems.find((i) => i.href === href)?.icon;

/** El elemento del menú (escritorio), su pestaña (móvil) o el encabezado. */
function navTargets(href: string): string[] {
    return [navTourKey(href), tabTourKey(href), 'page-header'];
}

type Input = Omit<HelpModule, 'area' | 'tour' | 'group'> & {
    group?: string;
    steps: TourStep[];
    match?: RegExp;
};

function mod(input: Input): HelpModule {
    const { steps, match, ...rest } = input;

    return {
        group: 'Tu portal',
        ...rest,
        area: 'portal',
        icon: rest.icon ?? iconFor(rest.href),
        tour: {
            id: `portal.${rest.id}`,
            area: 'portal',
            title: `Recorrido: ${rest.title}`,
            description: rest.summary,
            startRoute: rest.href,
            match: match ?? exact(rest.href),
            steps,
        },
    };
}

export const portalModules: HelpModule[] = [
    mod({
        id: 'inicio',
        title: 'Inicio',
        href: '/portal',
        summary: 'El resumen de tu proyecto y lo que sigue.',
        purpose:
            'Ves en qué etapa va tu proyecto, lo que necesitamos de ti y la actividad reciente, todo en una pantalla.',
        keySteps: [
            'Revisa en qué etapa va tu proyecto.',
            'Atiende lo pendiente (aprobar un diseño, pagar un anticipo…).',
            'Consulta la actividad reciente y a tu asesor.',
        ],
        steps: [
            {
                target: ['card:Recorrido de tu proyecto', 'css:#contenido h1'],
                title: 'Tu proyecto, paso a paso',
                body: 'Aquí ves en qué etapa va: medición, diseño, cotización, fabricación, instalación y entrega.',
            },
            card(
                'Actividad reciente',
                'Actividad reciente',
                'Cada avance que registramos en tu proyecto, con su fecha.',
            ),
            card(
                'Tu asesor',
                'Tu asesor',
                'La persona que te acompaña. Llámale o escríbele cuando lo necesites.',
            ),
        ],
    }),
    mod({
        id: 'solicitudes',
        title: 'Solicitudes',
        href: '/portal/solicitudes',
        summary: 'Lo que nos pediste y en qué va.',
        purpose:
            'Cada solicitud que nos enviaste (cotización, asesoría, visita) con su estado. Te avisamos de cada avance.',
        keySteps: [
            'Revisa el estado de cada solicitud.',
            'Usa «Nueva solicitud» si tienes otro espacio en mente.',
        ],
        steps: [
            header(
                'Mis solicitudes',
                'Lo que nos pediste y en qué va cada solicitud.',
            ),
            {
                target: ['portal-new-request', 'portal-more'],
                title: '¿Otro espacio en mente?',
                body: 'Con «Nueva solicitud» nos cuentas qué necesitas y te asesoramos.',
                optional: true,
            },
        ],
    }),
    mod({
        id: 'citas',
        title: 'Citas',
        href: '/portal/citas',
        summary: 'Tus asesorías y visitas técnicas.',
        purpose:
            'Fecha, hora y forma de cada cita: videollamada, llamada, WhatsApp o visita a domicilio. Aquí también encuentras el enlace de la videollamada.',
        keySteps: [
            'Revisa la fecha y hora de tu próxima cita.',
            'Entra a la videollamada desde el enlace.',
            'Si necesitas cambiarla, contacta a tu asesor.',
        ],
        steps: [
            header(
                'Mis citas',
                'Tus asesorías y visitas técnicas, con fecha, hora y enlace de videollamada.',
            ),
        ],
    }),
    mod({
        id: 'pagos',
        title: 'Pagos',
        href: '/portal/pagos',
        summary: 'Lo que pagaste, lo pendiente y tus comprobantes.',
        purpose:
            'Tus visitas, asesorías y anticipos: lo que pagaste, lo pendiente y cada comprobante. Los pagos con tarjeta son seguros a través de Openpay.',
        keySteps: [
            'Revisa los pagos pendientes.',
            'Paga tu anticipo con tarjeta desde aquí.',
            'Descarga tus comprobantes.',
        ],
        steps: [header('Pagos', 'Tus pagos, lo pendiente y cada comprobante.')],
    }),
    mod({
        id: 'cotizaciones',
        title: 'Cotizaciones',
        href: '/portal/cotizaciones',
        summary: 'Revisa, acepta o comenta cada propuesta.',
        purpose:
            'Cada cotización con el detalle de muebles, precios y condiciones. Acéptala o cuéntanos qué cambiarías.',
        keySteps: [
            'Abre la cotización y revisa cada partida.',
            'Acéptala si estás de acuerdo.',
            'Si quieres cambios, recházala con tus comentarios: te enviamos una nueva versión.',
        ],
        steps: [
            header(
                'Cotizaciones',
                'Revisa el detalle de cada propuesta, acéptala o cuéntanos qué cambiarías.',
            ),
        ],
    }),
    mod({
        id: 'disenos',
        title: 'Diseños',
        href: '/portal/disenos',
        summary: 'Las propuestas de diseño de tu proyecto.',
        purpose:
            'Renders y planos de tu proyecto. Apruébalos o pide ajustes; fabricamos solo con tu aprobación y la de nuestro equipo técnico.',
        keySteps: [
            'Revisa las imágenes y planos.',
            'Aprueba el diseño o pide cambios.',
        ],
        steps: [
            header(
                'Diseño',
                'Las propuestas de diseño de tu proyecto. Apruébalas o pide cambios.',
            ),
        ],
    }),
    mod({
        id: 'documentos',
        title: 'Documentos',
        href: '/portal/documentos',
        summary: 'Contratos, garantías y archivos.',
        purpose:
            'Todo lo que compartimos contigo en un solo lugar: contrato, garantías y archivos del proyecto.',
        keySteps: ['Abre o descarga cualquier documento.'],
        steps: [
            header(
                'Documentos',
                'Contratos, garantías y archivos que compartimos contigo.',
            ),
        ],
    }),
    mod({
        id: 'garantias',
        title: 'Garantías',
        href: '/portal/garantias',
        match: /^\/portal\/(garantias|incidencias\/[^/]+)\/?$/,
        summary: 'Tus garantías y reportes.',
        purpose:
            'Si algo no está bien con tu mueble, aquí lo reportas y le das seguimiento. También ves qué cubre tu garantía.',
        keySteps: [
            'Revisa qué cubre tu garantía.',
            'Si algo falla, levanta un reporte con fotos.',
            'Sigue el avance del reporte.',
        ],
        steps: [
            header(
                'Incidencias y garantías',
                'Si algo no está bien con tu mueble, aquí lo atendemos.',
            ),
            card(
                'Tus garantías',
                'Tus garantías',
                'Qué cubre cada garantía y hasta cuándo.',
            ),
            card('Reportes', 'Reportes', 'Tus reportes y en qué va cada uno.'),
        ],
    }),
    mod({
        id: 'ayuda',
        title: 'Ayuda',
        href: '/portal/ayuda',
        summary: 'Contacto con tu asesor y con ML Habitat.',
        purpose:
            'Habla con tu asesor por teléfono, correo o WhatsApp, y repite cualquier recorrido del portal.',
        keySteps: [
            'Contacta a tu asesor.',
            'Inicia el recorrido de cualquier sección.',
        ],
        steps: [
            header(
                '¿Cómo te ayudamos?',
                'Contacto con tu asesor y con ML Habitat.',
            ),
            card('Tu asesor', 'Tu asesor', 'Llámale o escríbele directamente.'),
            {
                target: 'help-modules',
                title: 'Recorridos del portal',
                body: 'Elige una sección y te la mostramos paso a paso.',
                optional: true,
            },
        ],
    }),
];

/** Recorridos del detalle (se inician desde «Recorrido de esta sección»). */
export const portalDetailTours: TourDefinition[] = [
    {
        id: 'portal.cotizacion',
        area: 'portal',
        title: 'Recorrido: tu cotización',
        match: detail('/portal/cotizaciones'),
        steps: [
            header(
                'Tu cotización',
                'El título, el proyecto, el folio y la versión.',
            ),
            card(
                'Detalle',
                'Detalle',
                'Cada mueble o trabajo con su precio. Revisa las condiciones y la vigencia.',
            ),
            note(
                'Acepta o pide cambios',
                'Si estás de acuerdo, acéptala. Si quieres cambios, recházala y cuéntanos qué cambiarías: te enviamos una nueva versión.',
            ),
        ],
    },
    {
        id: 'portal.anticipo',
        area: 'portal',
        title: 'Recorrido: pago del anticipo',
        match: /^\/portal\/pagos\/anticipo\/[^/]+\/?$/,
        steps: [
            header('Tu anticipo', 'El contrato y el monto a pagar.'),
            card(
                'Resumen',
                'Resumen',
                'El total del contrato, el anticipo y el crédito de tu visita si aplica.',
            ),
            note(
                'Pago seguro',
                'El pago con tarjeta se procesa con Openpay. Tus datos de tarjeta nunca se guardan en ML Habitat.',
            ),
        ],
    },
];

export const portalGeneralTour: TourDefinition = {
    id: 'portal.general',
    area: 'portal',
    title: 'Conoce tu portal',
    description: 'Un vistazo a todo lo que puedes hacer aquí.',
    steps: [
        note(
            'Te damos la bienvenida a tu portal',
            'Aquí sigues tu proyecto de principio a fin: citas, pagos, diseños, cotizaciones y documentos. Te mostramos cada sección en un minuto.',
        ),
        {
            target: ['portal-nav', 'portal-tabs'],
            title: 'Tu menú',
            body: 'Desde aquí entras a cada sección. En el celular, las secciones principales están abajo y el resto en «Más».',
        },
        {
            route: '/portal',
            target: ['card:Recorrido de tu proyecto', 'css:#contenido h1'],
            title: 'Inicio',
            body: 'El resumen de tu proyecto: en qué etapa va y lo que necesitamos de ti.',
        },
        {
            route: '/portal/solicitudes',
            target: navTargets('/portal/solicitudes'),
            title: 'Solicitudes',
            body: 'Lo que nos pediste y en qué va cada solicitud.',
        },
        {
            route: '/portal/citas',
            target: navTargets('/portal/citas'),
            title: 'Citas',
            body: 'Tus asesorías y visitas, con el enlace de la videollamada.',
        },
        {
            route: '/portal/pagos',
            target: navTargets('/portal/pagos'),
            title: 'Pagos',
            body: 'Lo que pagaste, lo pendiente y tus comprobantes. Aquí pagas tu anticipo con tarjeta.',
        },
        {
            route: '/portal/cotizaciones',
            target: navTargets('/portal/cotizaciones'),
            title: 'Cotizaciones',
            body: 'Revisa cada propuesta y acéptala o pide cambios.',
        },
        {
            route: '/portal/disenos',
            target: navTargets('/portal/disenos'),
            title: 'Diseños',
            body: 'Los renders y planos de tu proyecto. Apruébalos o pide ajustes.',
        },
        {
            route: '/portal/documentos',
            target: navTargets('/portal/documentos'),
            title: 'Documentos',
            body: 'Tu contrato, garantías y archivos del proyecto.',
        },
        {
            route: '/portal/garantias',
            target: navTargets('/portal/garantias'),
            title: 'Garantías',
            body: 'Si algo no está bien con tu mueble, repórtalo aquí con fotos.',
        },
        {
            target: 'notifications',
            title: 'Avisos',
            body: 'Te avisamos de cada avance: citas, pagos y aprobaciones.',
            optional: true,
        },
        {
            route: '/portal/ayuda',
            target: ['help-button', 'page-header'],
            title: '¿Dudas? Aquí estamos',
            body: 'Con este botón repites el recorrido o abres la ayuda, donde está el contacto de tu asesor.',
        },
    ],
};
