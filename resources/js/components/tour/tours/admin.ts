import { LifeBuoy } from '@lucide/vue';
import type { Component } from 'vue';
import { adminNavigation } from '@/lib/navigation';
import { navTourKey } from '../keys';
import type { HelpModule, TourDefinition, TourStep } from '../types';
import {
    action,
    actions,
    card,
    content,
    detail,
    exact,
    filters,
    header,
    list,
    note,
    tab,
    tabs,
} from './helpers';

/**
 * Contenido de ayuda y recorridos del panel (/admin). Una ficha por módulo
 * (mismo orden y permisos que la barra lateral) con su recorrido corto; el
 * recorrido general se arma con `adminGeneralTour`. Edita los textos aquí.
 */
function iconFor(href: string): Component | undefined {
    for (const group of adminNavigation) {
        const item = group.items.find((i) => i.href === href);

        if (item) {
            return item.icon;
        }
    }

    return undefined;
}

type ModuleInput = Omit<HelpModule, 'area' | 'tour' | 'extraTours'> & {
    steps: TourStep[];
    match?: RegExp;
    extraTours?: Array<
        Omit<TourDefinition, 'area' | 'permission'> & {
            permission?: string | string[];
        }
    >;
};

function mod(input: ModuleInput): HelpModule {
    const { steps, match, extraTours, ...rest } = input;

    return {
        ...rest,
        area: 'admin',
        icon: rest.icon ?? iconFor(rest.href),
        tour: {
            id: `admin.${rest.id}`,
            area: 'admin',
            title: `Recorrido: ${rest.title}`,
            description: rest.summary,
            startRoute: rest.href,
            match: match ?? exact(rest.href),
            permission: rest.permission,
            steps,
        },
        extraTours: (extraTours ?? []).map((t) => ({
            ...t,
            area: 'admin',
            permission: t.permission ?? rest.permission,
        })),
    };
}

export const adminModules: HelpModule[] = [
    // ── Inicio ─────────────────────────────────────────────────────────
    mod({
        id: 'inicio',
        group: 'Inicio',
        title: 'Inicio (tablero)',
        href: '/admin',
        permission: 'dashboard.view',
        summary: 'Tu resumen del día: pendientes, agenda y cifras clave.',
        purpose:
            'Es lo primero que ves al entrar. Junta lo urgente de cada área para que sepas por dónde empezar sin abrir cada módulo.',
        keySteps: [
            'Revisa «Requiere tu atención» y atiende lo más antiguo.',
            'Mira la agenda de hoy: asesorías y visitas con su hora.',
            'Consulta las cifras comerciales, de visitas, finanzas y operación.',
            'Entra a cualquier pendiente con un clic para resolverlo.',
        ],
        steps: [
            header(
                'Tu tablero',
                'Aquí empieza tu día. Cada bloque solo aparece si tu rol puede verlo.',
                { target: ['page-header', 'css:#contenido h1'] },
            ),
            card(
                'Requiere tu atención',
                'Lo que no puede esperar',
                'Prospectos sin contactar, visitas por confirmar, pagos y revisiones pendientes. Haz clic en cualquiera para ir directo.',
            ),
            card(
                'Agenda de hoy',
                'Agenda de hoy',
                'Asesorías y visitas del día con su hora y responsable.',
            ),
            card(
                'Comercial',
                'Cifras comerciales',
                'Prospectos nuevos, conversión y seguimiento del periodo.',
            ),
            card(
                'Finanzas',
                'Finanzas',
                'Lo cobrado en el mes, lo pendiente y los reembolsos.',
            ),
            card(
                'Proyectos activos',
                'Proyectos activos',
                'En qué etapa va cada proyecto: medición, diseño, fabricación, instalación…',
            ),
        ],
    }),

    // ── Comercial ──────────────────────────────────────────────────────
    mod({
        id: 'prospectos',
        group: 'Comercial',
        title: 'Prospectos',
        href: '/admin/prospectos',
        permission: 'leads.view',
        summary: 'Solicitudes de clientes potenciales y su seguimiento.',
        purpose:
            'Cada solicitud del sitio («Cotiza tu proyecto», asesoría, visita) llega aquí con folio y origen. Sirve para no perder a nadie y saber en qué etapa va cada persona.',
        keySteps: [
            'Abre el prospecto nuevo y llama o escribe al cliente.',
            'Registra cada contacto en la bitácora y agenda el próximo seguimiento.',
            'Cambia la etapa conforme avanza (contactado, asesoría, visita…).',
            'Desde el detalle: agenda una visita, inicia la precalificación o crea el proyecto.',
        ],
        steps: [
            header(
                'Prospectos',
                'Todas las solicitudes de clientes potenciales, con su etapa y su próximo seguimiento.',
            ),
            actions(
                'Lista o tablero',
                'Cambia entre la lista y el tablero por etapas. En el tablero ves de un vistazo cuántos prospectos hay en cada fase.',
            ),
            filters(
                'Busca por nombre, folio, teléfono o correo y filtra por etapa, responsable u origen.',
            ),
            list(
                'Tus prospectos',
                'Abre uno para ver la solicitud completa, su bitácora y las acciones: agendar visita, precalificar o crear el proyecto.',
            ),
        ],
        extraTours: [
            {
                id: 'admin.prospectos.detalle',
                title: 'Recorrido: detalle del prospecto',
                description: 'Datos, bitácora y siguientes pasos.',
                match: detail('/admin/prospectos'),
                steps: [
                    header(
                        'Ficha del prospecto',
                        'Nombre, folio y acciones rápidas para llamar o escribir.',
                    ),
                    card(
                        'Datos de la solicitud',
                        'Qué pidió',
                        'El espacio, las medidas aproximadas, fotos y el origen de la solicitud.',
                    ),
                    card(
                        'Bitácora',
                        'Bitácora',
                        'Anota cada llamada, mensaje o acuerdo. Todo el equipo ve el historial.',
                    ),
                    card(
                        'Etapa',
                        'Etapa',
                        'Mueve al prospecto de etapa conforme avanza. Así el tablero y las cifras se mantienen al día.',
                    ),
                    card(
                        'Próximo seguimiento',
                        'Próximo seguimiento',
                        'Programa cuándo volver a contactar. Aparece en «Requiere tu atención» cuando llega la fecha.',
                    ),
                    card(
                        'Acciones',
                        'Siguientes pasos',
                        'Agenda la visita técnica, inicia la precalificación o crea el proyecto cuando el cliente esté listo.',
                    ),
                ],
            },
        ],
    }),
    mod({
        id: 'clientes',
        group: 'Comercial',
        title: 'Clientes',
        href: '/admin/clientes',
        permission: 'customers.view',
        summary: 'Personas con solicitudes o proyectos y su historial.',
        purpose:
            'Un mismo cliente puede tener varias solicitudes, asesorías y proyectos. Aquí ves todo junto y puedes invitarlo a su portal.',
        keySteps: [
            'Busca al cliente por nombre, correo o teléfono.',
            'Revisa sus domicilios, solicitudes, asesorías y proyectos.',
            'Edita sus datos de contacto si cambiaron.',
            'Invítalo al portal para que siga su proyecto en línea.',
        ],
        steps: [
            header(
                'Clientes',
                'Todas las personas que nos han pedido información o ya tienen un proyecto.',
            ),
            filters('Busca por nombre, correo o teléfono.'),
            list(
                'Lista de clientes',
                'Abre un cliente para ver sus domicilios, solicitudes, asesorías y proyectos, y para invitarlo al portal.',
            ),
        ],
        extraTours: [
            {
                id: 'admin.clientes.detalle',
                title: 'Recorrido: ficha del cliente',
                match: detail('/admin/clientes'),
                steps: [
                    header(
                        'Ficha del cliente',
                        'Contacto rápido y la opción «Invitar al portal» para que vea su proyecto en línea.',
                    ),
                    card(
                        'Contacto',
                        'Datos de contacto',
                        'Teléfono, correo y preferencias. Edítalos si cambian.',
                    ),
                    card(
                        'Domicilios',
                        'Domicilios',
                        'Donde se hacen las visitas y las instalaciones.',
                    ),
                    card(
                        'Proyectos',
                        'Proyectos',
                        'Cada proyecto del cliente con su etapa. Haz clic para abrirlo.',
                    ),
                ],
            },
        ],
    }),
    mod({
        id: 'asesorias',
        group: 'Comercial',
        title: 'Asesorías',
        href: '/admin/asesorias',
        permission: 'consultations.view',
        summary:
            'Citas remotas (videollamada, llamada o WhatsApp) con prospectos.',
        purpose:
            'El cliente aparta y paga su asesoría desde el sitio. Aquí confirmas, entras a la videollamada, registras el resultado y defines el siguiente paso.',
        keySteps: [
            'Revisa las asesorías de hoy y las próximas.',
            'Abre la cita para copiar el enlace de la videollamada.',
            'Al terminar, registra el resultado y el siguiente paso.',
            'Reprograma o cancela si el cliente lo pide.',
        ],
        steps: [
            header(
                'Asesorías',
                'Citas remotas con prospectos. Se crean cuando el cliente aparta desde el sitio o cuando tú la agendas.',
            ),
            tabs(
                'Hoy, próximas y pasadas',
                'Cambia de pestaña para ver las de hoy, las próximas, las pasadas o todas.',
            ),
            filters('Filtra por asesor, estado o busca por cliente.'),
            list(
                'Citas',
                'Abre una para confirmar, copiar el enlace de la videollamada, reprogramar o registrar el resultado.',
            ),
        ],
        extraTours: [
            {
                id: 'admin.asesorias.detalle',
                title: 'Recorrido: detalle de la asesoría',
                match: detail('/admin/asesorias', ['disponibilidad']),
                steps: [
                    header('La asesoría', 'Con quién es, cuándo y su estado.'),
                    card(
                        'Cita',
                        'Cita y videollamada',
                        'Copia el enlace de la videollamada o genera uno nuevo. Con Jitsi, el asesor entra primero.',
                    ),
                    action(
                        'Registrar resultado',
                        'Registra el resultado',
                        'Al terminar, anota qué se acordó y el siguiente paso (visita, cotización, seguimiento).',
                    ),
                    card(
                        'Cliente',
                        'Cliente',
                        'Sus datos de contacto y la liga a su prospecto.',
                    ),
                ],
            },
        ],
    }),
    mod({
        id: 'visitas',
        group: 'Comercial',
        title: 'Visitas',
        href: '/admin/visitas',
        permission: 'visits.view',
        summary: 'Visitas técnicas a domicilio: pago, técnico y seguimiento.',
        purpose:
            'La visita técnica se paga con tarjeta (Openpay) y reserva a un técnico de la zona. Aquí sigues cada visita, confirmas, reprogramas y ves el resultado de la medición.',
        keySteps: [
            'Crea una visita con «Nueva visita» o deja que el cliente la agende desde el sitio.',
            'Verifica el pago y confirma la cita.',
            'El técnico mide desde su app; tú ves la medición en el proyecto.',
            'Ajusta precio, crédito y condiciones en «Política».',
        ],
        steps: [
            header(
                'Visitas técnicas',
                'Agenda de visitas a domicilio con su pago y el técnico asignado.',
            ),
            action(
                'Nueva visita',
                'Agenda una visita',
                'Revisa la cobertura del código postal, elige horario, cliente y domicilio, y define cómo se paga.',
            ),
            action(
                'Política',
                'Política de visita',
                'Tarifa, crédito abonable al proyecto, agenda y condiciones. Cada versión se congela para las visitas ya reservadas.',
            ),
            filters('Filtra por estado, técnico, fecha o busca por cliente.'),
            list(
                'Visitas',
                'Abre una para confirmar, reprogramar, marcar «Cliente ausente» o «Realizada» y ver el comprobante de pago.',
            ),
        ],
        extraTours: [
            {
                id: 'admin.visitas.politica',
                title: 'Recorrido: política de visita',
                startRoute: '/admin/visitas/politica',
                match: exact('/admin/visitas/politica'),
                steps: [
                    header(
                        'Política de visita técnica',
                        'Define cuánto cuesta la visita, si se abona al proyecto y con cuánta anticipación se agenda.',
                    ),
                    content(
                        'Versiones inmutables',
                        'Al guardar se crea una versión nueva. Las visitas ya apartadas conservan los términos con los que se reservaron.',
                    ),
                ],
            },
            {
                id: 'admin.visitas.crear',
                title: 'Recorrido: nueva visita',
                startRoute: '/admin/visitas/crear',
                match: exact('/admin/visitas/crear'),
                steps: [
                    header(
                        'Nueva visita técnica',
                        'Cuatro pasos: cobertura y horario, cliente, domicilio y pago.',
                    ),
                    card(
                        '1. Cobertura y horario',
                        '1. Cobertura y horario',
                        'Escribe el código postal: solo verás horarios de técnicos que atienden esa zona.',
                    ),
                    card(
                        '2. Cliente',
                        '2. Cliente',
                        'Elige un cliente existente o captura uno nuevo.',
                    ),
                    card(
                        '4. Pago',
                        '4. Pago',
                        'Envía el enlace de pago con tarjeta o registra un pago manual.',
                    ),
                ],
            },
            {
                id: 'admin.visitas.detalle',
                title: 'Recorrido: detalle de la visita',
                match: detail('/admin/visitas', [
                    'crear',
                    'politica',
                    'disponibilidad',
                ]),
                steps: [
                    header(
                        'La visita',
                        'Cliente, folio y acciones: confirmar, reprogramar, cliente ausente o realizada.',
                    ),
                    card(
                        'Agenda',
                        'Agenda y técnico',
                        'Fecha, hora y técnico asignado. Cambia el técnico si hace falta.',
                    ),
                    card(
                        'Pago',
                        'Pago',
                        'Estado del cobro y comprobante. Sin pago confirmado, la cita queda apartada por tiempo limitado.',
                    ),
                    card(
                        'Historial',
                        'Historial',
                        'Todo lo que ha pasado con la visita, en orden.',
                    ),
                ],
            },
        ],
    }),
    mod({
        id: 'precalificaciones',
        group: 'Comercial',
        title: 'Precalificación',
        href: '/admin/precalificaciones',
        permission: 'prequalification.view',
        summary: 'Revisión manual de solicitudes de pago a plazos.',
        purpose:
            'Algunos clientes piden pagar a plazos. Aquí se revisa cada expediente a mano. Ninguna decisión es automática y ningún resultado es una aprobación de crédito.',
        keySteps: [
            'Toma una solicitud «Por revisar».',
            'Revisa los datos y documentos del cliente.',
            'Registra tu dictamen con una nota clara.',
            'Comunica el resultado al cliente desde su prospecto.',
        ],
        steps: [
            header(
                'Precalificaciones',
                'Revisión manual de solicitudes de pago a plazos. Nunca se aprueba un crédito desde aquí.',
            ),
            tabs(
                'Por revisar, en revisión y todas',
                'Empieza por «Por revisar». Lo que ya tomó alguien pasa a «En revisión».',
            ),
            filters('Busca por cliente o filtra por estado.'),
            list(
                'Expedientes',
                'Abre uno para ver los datos, los documentos y la auditoría de cada cambio.',
            ),
        ],
        extraTours: [
            {
                id: 'admin.precalificaciones.detalle',
                title: 'Recorrido: expediente',
                match: detail('/admin/precalificaciones'),
                steps: [
                    header(
                        'Expediente',
                        'Datos del cliente y lo que solicitó.',
                    ),
                    card(
                        'Contacto',
                        'Contacto',
                        'Para aclarar dudas con el cliente.',
                    ),
                    card(
                        'Auditoría',
                        'Auditoría',
                        'Quién revisó, qué decidió y cuándo. No se puede borrar.',
                    ),
                ],
            },
        ],
    }),
    mod({
        id: 'cotizaciones',
        group: 'Comercial',
        title: 'Cotizaciones',
        href: '/admin/cotizaciones',
        permission: 'quotes.view',
        summary: 'Todas las cotizaciones vigentes de los proyectos.',
        purpose:
            'La cotización se arma desde el proyecto, con partidas y condiciones. El cliente la acepta o la rechaza desde su portal; al aceptarla nace el contrato.',
        keySteps: [
            'Crea la cotización desde el detalle del proyecto.',
            'Agrega partidas, condiciones y revisa los totales.',
            'Envíala: el cliente la ve en su portal.',
            'Si pide cambios, crea una nueva versión.',
        ],
        steps: [
            header(
                'Cotizaciones',
                'Lista de cotizaciones de todos los proyectos. Se crean desde el detalle de cada proyecto.',
            ),
            filters('Filtra por estado o busca por proyecto, cliente o folio.'),
            list(
                'Cotizaciones',
                'Abre una para editarla (si es borrador) o revisar lo que el cliente decidió.',
            ),
        ],
        extraTours: [
            {
                id: 'admin.cotizaciones.editor',
                title: 'Recorrido: editor de cotización',
                match: /^\/admin\/(cotizaciones\/[^/]+\/editar|proyectos\/[^/]+\/cotizaciones\/crear)\/?$/,
                steps: [
                    header(
                        'Editor de cotización',
                        'Arma la propuesta para el cliente.',
                    ),
                    card(
                        'Encabezado',
                        'Encabezado',
                        'Título, vigencia y notas generales.',
                    ),
                    card(
                        'Partidas',
                        'Partidas',
                        'Cada mueble o trabajo con cantidad y precio. Los totales se calculan en el servidor.',
                    ),
                    card(
                        'Condiciones',
                        'Condiciones',
                        'Anticipo, tiempos de entrega y lo que incluye o no incluye.',
                    ),
                    card(
                        'Totales',
                        'Totales',
                        'Subtotal, impuestos y total. Revisa antes de enviar.',
                    ),
                ],
            },
        ],
    }),

    // ── Proyectos ──────────────────────────────────────────────────────
    mod({
        id: 'proyectos',
        group: 'Proyectos',
        title: 'Proyectos',
        href: '/admin/proyectos',
        permission: 'projects.view',
        summary:
            'Medición, diseño, cotización, contrato y operación en un lugar.',
        purpose:
            'El proyecto es el corazón del trabajo: nace de un prospecto, una visita o un cliente y reúne todo lo que pasa hasta la entrega y la garantía.',
        keySteps: [
            'Crea el proyecto desde el prospecto (botón «Crear proyecto»).',
            'Revisa la medición que subió el técnico.',
            'Sube versiones de diseño y obtén la aprobación del cliente y la técnica.',
            'Cotiza, firma el contrato y cobra el anticipo.',
            'Sigue fabricación, instalación y calidad en «Operación».',
        ],
        steps: [
            header(
                'Proyectos',
                'Cada proyecto reúne medición, diseño, cotización, contrato y operación.',
            ),
            filters(
                'Filtra por etapa, responsable o busca por folio o cliente.',
            ),
            list(
                'Tus proyectos',
                'Abre uno para ver su cronología y trabajar en cada etapa desde sus pestañas.',
            ),
        ],
        extraTours: [
            {
                id: 'admin.proyectos.detalle',
                title: 'Recorrido: detalle del proyecto',
                match: detail('/admin/proyectos'),
                steps: [
                    header(
                        'El proyecto',
                        'Nombre, folio, etapa y contacto rápido con el cliente.',
                    ),
                    tabs(
                        'Todo en pestañas',
                        'Resumen, cronología, medición, diseño, cotizaciones, contrato y pagos, operación y archivos.',
                    ),
                    tab(
                        'Cronología',
                        'Cronología',
                        'Cada paso del proyecto en orden. Lo marcado como visible para el cliente aparece en su portal.',
                    ),
                    tab(
                        'Diseño',
                        'Diseño',
                        'Sube versiones. La aprobación del cliente no es lo mismo que la aprobación técnica: fabricación necesita la técnica.',
                    ),
                    tab(
                        'Contrato y pagos',
                        'Contrato y pagos',
                        'El contrato congela monto y anticipo, y aplica el crédito de la visita si corresponde.',
                    ),
                    tab(
                        'Operación',
                        'Operación',
                        'Órdenes de fabricación, instalaciones, calidad e incidencias del proyecto.',
                    ),
                ],
            },
        ],
    }),
    mod({
        id: 'disenos',
        group: 'Proyectos',
        title: 'Diseño',
        href: '/admin/disenos',
        permission: 'designs.view',
        summary: 'Versiones de diseño de todos los proyectos.',
        purpose:
            'Concentra las propuestas de diseño y su estado de aprobación. Fabricación solo arranca cuando un diseño tiene aprobación técnica.',
        keySteps: [
            'Sube la versión de diseño desde el proyecto.',
            'El cliente la aprueba o pide cambios en su portal.',
            'Diseño da la aprobación técnica.',
            'Con la aprobación técnica, Operaciones puede emitir la fabricación.',
        ],
        steps: [
            header(
                'Diseño',
                'Todas las versiones de diseño con su aprobación del cliente y técnica.',
            ),
            filters('Filtra por estado de aprobación o busca por proyecto.'),
            list(
                'Versiones',
                'Abre una para ir al proyecto y revisar renders, planos y comentarios.',
            ),
        ],
    }),
    mod({
        id: 'fabricacion',
        group: 'Proyectos',
        title: 'Fabricación',
        href: '/admin/fabricacion',
        permission: 'fabrication.view',
        summary: 'Órdenes a proveedores fabricantes.',
        purpose:
            'Controla qué se mandó a fabricar, con qué proveedor y para cuándo. Solo se fabrica con diseño técnicamente aprobado y proyecto autorizado.',
        keySteps: [
            'Crea la orden con «Nueva orden» y elige proveedor y diseño.',
            'Registra fechas comprometidas y avances.',
            'Marca la orden como lista para instalar.',
            'Reporta incidencias si algo llega mal.',
        ],
        steps: [
            header(
                'Fabricación',
                'Órdenes a proveedores fabricantes y su avance.',
            ),
            action(
                'Nueva orden',
                'Nueva orden',
                'Elige el proyecto, el diseño aprobado y el proveedor. Si falta la aprobación técnica, no deja continuar.',
            ),
            filters('Filtra por estado, proveedor o busca por folio.'),
            list(
                'Órdenes',
                'Abre una para ver el diseño a fabricar, las fechas, incidencias y el pago al proveedor.',
            ),
        ],
        extraTours: [
            {
                id: 'admin.fabricacion.detalle',
                title: 'Recorrido: orden de fabricación',
                match: detail('/admin/fabricacion'),
                steps: [
                    header('La orden', 'Folio, proyecto y proveedor.'),
                    card(
                        'Diseño a fabricar',
                        'Diseño a fabricar',
                        'La versión aprobada que recibe el proveedor.',
                    ),
                    card(
                        'Fechas',
                        'Fechas',
                        'Compromiso de entrega y fechas reales.',
                    ),
                    card(
                        'Pago al proveedor',
                        'Pago al proveedor',
                        'Solicita el pago; Finanzas lo autoriza en «Pagos a proveedores».',
                    ),
                ],
            },
        ],
    }),
    mod({
        id: 'instalaciones',
        group: 'Proyectos',
        title: 'Instalaciones',
        href: '/admin/instalaciones',
        permission: 'installations.view',
        summary: 'Órdenes de instalación con tarifario congelado.',
        purpose:
            'Programa al equipo instalador, congela los precios del tarifario, recibe la evidencia y la conformidad del cliente antes de pasar a calidad.',
        keySteps: [
            'Crea la orden con «Nueva orden» y asigna equipo y fecha.',
            'El instalador sube evidencia desde su app.',
            'El cliente da su conformidad en el portal.',
            'La orden pasa a revisión de calidad.',
        ],
        steps: [
            header(
                'Instalaciones',
                'Del tarifario congelado a la evidencia, la conformidad del cliente y la revisión de calidad.',
            ),
            action(
                'Nueva orden',
                'Nueva orden de instalación',
                'Elige el proyecto, el tarifario vigente y el equipo. Los precios quedan congelados en la orden.',
            ),
            filters('Filtra por estado, instalador o fecha.'),
            list(
                'Órdenes',
                'Abre una para ver equipo y fecha, trabajos extra, incidencias y el total.',
            ),
        ],
        extraTours: [
            {
                id: 'admin.instalaciones.detalle',
                title: 'Recorrido: orden de instalación',
                match: detail('/admin/instalaciones'),
                steps: [
                    header('La orden', 'Folio, proyecto y cliente.'),
                    card(
                        'Equipo y fecha',
                        'Equipo y fecha',
                        'Quién instala y cuándo.',
                    ),
                    card(
                        'Trabajos extra',
                        'Trabajos extra',
                        'Lo que se agregó en sitio, con su precio.',
                    ),
                    card(
                        'Total de la orden',
                        'Total',
                        'El total a pagar al instalador según el tarifario congelado.',
                    ),
                ],
            },
        ],
    }),
    mod({
        id: 'calidad',
        group: 'Proyectos',
        title: 'Calidad',
        href: '/admin/calidad',
        permission: 'quality.view',
        summary: 'Revisión de evidencias contra el checklist.',
        purpose:
            'Antes de cerrar una instalación y pagar al instalador, Calidad revisa la evidencia módulo por módulo y dictamina.',
        keySteps: [
            'Abre la revisión más antigua.',
            'Compara la evidencia con el checklist.',
            'Dictamina: aprobado o con pendientes (punch list).',
            'Si hay algo mal, levanta una incidencia.',
        ],
        steps: [
            header('Calidad', 'Revisiones pendientes, la más antigua primero.'),
            content(
                'Pendientes',
                'Abre una revisión para ver las fotos de cada módulo y marcar el checklist.',
            ),
            card(
                'Decisiones recientes',
                'Decisiones recientes',
                'Lo que se dictaminó últimamente, por si necesitas consultarlo.',
            ),
        ],
        extraTours: [
            {
                id: 'admin.calidad.detalle',
                title: 'Recorrido: revisión de calidad',
                match: detail('/admin/calidad'),
                steps: [
                    header('La revisión', 'Orden, proyecto y cliente.'),
                    card(
                        'Dictamen',
                        'Dictamen',
                        'Aprueba o deja pendientes con una nota clara.',
                    ),
                    card(
                        'Incidencias',
                        'Incidencias',
                        'Si falta algo o hay daños, regístralo aquí.',
                    ),
                ],
            },
        ],
    }),
    mod({
        id: 'incidencias',
        group: 'Proyectos',
        title: 'Incidencias',
        href: '/admin/incidencias',
        permission: 'incidents.view',
        summary: 'Faltantes, daños y errores por resolver.',
        purpose:
            'Registra cualquier problema (de fabricación, instalación o reportado por el cliente) con su plan de acción hasta resolverlo.',
        keySteps: [
            'Crea la incidencia con «Nueva incidencia».',
            'Define el plan de acción y el responsable.',
            'Da seguimiento en la actividad.',
            'Registra la resolución y ciérrala.',
        ],
        steps: [
            header(
                'Incidencias',
                'Problemas por resolver. Lo crítico va primero.',
            ),
            action(
                'Nueva incidencia',
                'Nueva incidencia',
                'Describe qué pasó, en qué proyecto y qué tan grave es.',
            ),
            filters('Filtra por severidad, estado o proyecto.'),
            list(
                'Incidencias',
                'Abre una para ver qué pasó, el plan de acción y la resolución.',
            ),
        ],
        extraTours: [
            {
                id: 'admin.incidencias.detalle',
                title: 'Recorrido: incidencia',
                match: detail('/admin/incidencias'),
                steps: [
                    header('La incidencia', 'Título, estado y severidad.'),
                    card(
                        'Qué pasó',
                        'Qué pasó',
                        'La descripción y la evidencia.',
                    ),
                    card(
                        'Plan de acción',
                        'Plan de acción',
                        'Qué se va a hacer, quién y para cuándo.',
                    ),
                    card(
                        'Resolución',
                        'Resolución',
                        'Cómo se resolvió. Al cerrarla queda en el historial del proyecto.',
                    ),
                ],
            },
        ],
    }),
    mod({
        id: 'garantias',
        group: 'Proyectos',
        title: 'Garantías',
        href: '/admin/garantias',
        permission: 'warranties.view',
        summary: 'Garantías por proyecto y reclamos en atención.',
        purpose:
            'Al entregar se registran las garantías (qué cubren y qué no). Si el cliente reclama, aquí se atiende el reclamo.',
        keySteps: [
            'Registra las garantías del proyecto al cerrarlo.',
            'Revisa los reclamos que llegan del portal.',
            'Atiende el reclamo y registra la solución.',
        ],
        steps: [
            header(
                'Garantías',
                'Garantías registradas por proyecto y reclamos en atención.',
            ),
            action(
                'Registrar garantías',
                'Registrar garantías',
                'Elige el proyecto y el tipo de garantía; se calculan las fechas de vigencia.',
            ),
            filters('Filtra por estado, tipo o busca por proyecto.'),
            list(
                'Garantías',
                'Abre una para ver qué cubre, las exclusiones y sus reclamos.',
            ),
        ],
        extraTours: [
            {
                id: 'admin.garantias.detalle',
                title: 'Recorrido: garantía',
                match: detail('/admin/garantias'),
                steps: [
                    header('La garantía', 'Tipo y vigencia.'),
                    card(
                        'Qué cubre',
                        'Qué cubre',
                        'Lo que entra en la garantía.',
                    ),
                    card(
                        'Exclusiones',
                        'Exclusiones',
                        'Lo que no cubre (uso indebido, humedad, etc.).',
                    ),
                    card(
                        'Reclamos',
                        'Reclamos',
                        'Cada reclamo del cliente y cómo se atendió.',
                    ),
                ],
            },
        ],
    }),

    // ── Operación ──────────────────────────────────────────────────────
    mod({
        id: 'agenda',
        group: 'Operación',
        title: 'Agenda',
        href: '/admin/agenda',
        permission: 'schedule.view',
        summary: 'Disponibilidad de asesores y técnicos.',
        purpose:
            'Lo que configures aquí es exactamente lo que el cliente ve al agendar su asesoría o su visita. Sin horario no hay citas disponibles.',
        keySteps: [
            'Elige si configuras asesorías (asesores) o visitas (técnicos).',
            'Selecciona a la persona.',
            'Define su horario semanal.',
            'Agrega excepciones: vacaciones, días festivos o bloqueos.',
        ],
        steps: [
            header(
                'Agenda',
                'Disponibilidad de asesores y técnicos: de aquí salen los horarios que ve el cliente.',
            ),
            tabs(
                'Asesorías o visitas',
                'Elige qué agenda configuras: la de asesores (asesorías) o la de técnicos (visitas).',
            ),
            card(
                'Horario semanal',
                'Horario semanal',
                'Marca los días y horas en que la persona atiende. Se guarda por persona.',
            ),
            card(
                'Excepciones',
                'Excepciones',
                'Vacaciones, festivos o un bloqueo puntual. Usa «Agregar».',
            ),
        ],
    }),
    mod({
        id: 'cobertura',
        group: 'Operación',
        title: 'Cobertura',
        href: '/admin/cobertura',
        permission: 'coverage.view',
        summary: 'Zonas y códigos postales donde atendemos.',
        purpose:
            'Define dónde se pueden vender visitas. Un código postal pertenece a una sola zona, y la zona necesita técnicos activos para aceptar visitas.',
        keySteps: [
            'Crea una zona con «Nueva zona».',
            'Agrega sus códigos postales.',
            'Asigna técnicos y proveedores a la zona.',
            'Verifica un código postal para saber si se atiende.',
        ],
        steps: [
            header(
                'Cobertura',
                'Zonas donde sí podemos vender y atender visitas técnicas.',
            ),
            action(
                'Nueva zona',
                'Nueva zona',
                'Ponle nombre, agrega códigos postales y asigna técnicos.',
            ),
            card(
                'Verificar un código postal',
                'Verifica un código postal',
                'Escribe un CP para saber si se atiende y en qué zona.',
            ),
            list(
                'Zonas',
                'Cada zona con sus códigos postales, técnicos y si acepta visitas.',
            ),
        ],
    }),
    mod({
        id: 'tecnicos',
        group: 'Operación',
        title: 'Técnicos',
        href: '/admin/tecnicos',
        permission: 'suppliers.view',
        summary: 'Quién mide, quién instala, dónde y con cuánta carga.',
        purpose:
            'Administra a los técnicos e instaladores: sus zonas, su proveedor y su carga de trabajo. Ellos usan la app de campo (/tecnico).',
        keySteps: [
            'Invita al técnico con «Invitar técnico».',
            'Indica si hace visitas, instalaciones o ambas.',
            'Asígnale zonas de cobertura.',
            'Configura su horario en Agenda.',
        ],
        steps: [
            header(
                'Técnicos e instaladores',
                'Quién mide, quién instala, en qué zonas y con cuánta carga.',
            ),
            action(
                'Invitar técnico',
                'Invita a un técnico',
                'Recibe un correo para crear su contraseña y entrar a la app de campo.',
            ),
            filters('Filtra por tipo de trabajo, zona o estado.'),
            content(
                'Técnicos',
                'Abre a un técnico para ver su ficha, contacto, zonas y trabajo asignado.',
            ),
        ],
    }),
    mod({
        id: 'proveedores',
        group: 'Operación',
        title: 'Proveedores',
        href: '/admin/proveedores',
        permission: 'suppliers.view',
        summary: 'Fabricantes e instaladores con los que trabajamos.',
        purpose:
            'Directorio de proveedores con sus contactos, zonas, documentos y evaluaciones de desempeño.',
        keySteps: [
            'Da de alta al proveedor con «Nuevo proveedor».',
            'Agrega sus contactos y técnicos.',
            'Califica su desempeño después de cada trabajo.',
        ],
        steps: [
            header(
                'Proveedores',
                'Fabricantes e instaladores: contactos, zonas, documentos y desempeño.',
            ),
            action(
                'Nuevo proveedor',
                'Nuevo proveedor',
                'Captura sus datos, tipo (fabricante, instalador o ambos) y tiempo de entrega.',
            ),
            filters('Filtra por tipo o estado y busca por nombre.'),
            list(
                'Proveedores',
                'Abre uno para ver sus datos generales, contactos y técnicos.',
            ),
        ],
    }),
    mod({
        id: 'tarifarios',
        group: 'Operación',
        title: 'Tarifarios',
        href: '/admin/tarifarios',
        permission: 'rate_cards.view',
        summary: 'Precios de instalación por módulo.',
        purpose:
            'Lo que se paga al instalador por cada módulo. Los precios se congelan en cada orden; las versiones vigentes no se editan.',
        keySteps: [
            'Crea un tarifario con «Nuevo tarifario».',
            'Agrega los conceptos y precios.',
            'Publícalo para usarlo en órdenes nuevas.',
            'Para cambiar precios, crea una versión nueva.',
        ],
        steps: [
            header(
                'Tarifarios de instalación',
                'Precios por módulo que se congelan en cada orden.',
            ),
            action(
                'Nuevo tarifario',
                'Nuevo tarifario',
                'Empieza un borrador; al publicarlo queda disponible para nuevas órdenes.',
            ),
            filters('Filtra por estado o proveedor.'),
            content(
                'Versiones',
                'Una versión vigente no se edita: crea una nueva para cambiar precios. Las órdenes anteriores conservan los suyos.',
            ),
        ],
    }),

    // ── Finanzas ───────────────────────────────────────────────────────
    mod({
        id: 'pagos',
        group: 'Finanzas',
        title: 'Pagos',
        href: '/admin/pagos',
        permission: 'payments.view',
        summary: 'Cobros con tarjeta y pagos registrados a mano.',
        purpose:
            'Todo el dinero que entra: visitas, asesorías y anticipos. Revisa el estado, registra pagos manuales (transferencia, efectivo) y haz reembolsos.',
        keySteps: [
            'Revisa los pagos del día y su estado.',
            'Registra un pago manual con su referencia.',
            'Haz un reembolso total o parcial con su motivo.',
            'Concilia contra el estado de cuenta.',
        ],
        steps: [
            header(
                'Pagos',
                'Cobros con tarjeta (Openpay) y pagos registrados manualmente.',
            ),
            filters(
                'Filtra por estado, concepto (visita, asesoría, anticipo), método o fecha.',
            ),
            list(
                'Pagos',
                'Abre uno para ver el resumen, los reembolsos, el cliente y su historial.',
            ),
        ],
        extraTours: [
            {
                id: 'admin.pagos.detalle',
                title: 'Recorrido: detalle del pago',
                match: detail('/admin/pagos'),
                steps: [
                    header('El pago', 'Folio, cliente y estado.'),
                    card('Resumen', 'Resumen', 'Monto, método y fechas.'),
                    action(
                        'Reembolsar',
                        'Reembolsar',
                        'Devuelve todo o una parte. Pide un motivo y queda en la bitácora.',
                    ),
                    card(
                        'Historial',
                        'Historial',
                        'Cada intento y aviso de Openpay, en orden.',
                    ),
                ],
            },
        ],
    }),
    mod({
        id: 'pagos-proveedores',
        group: 'Finanzas',
        title: 'Pagos a proveedores',
        href: '/admin/pagos-proveedores',
        permission: 'payouts.view',
        summary: 'Autorización y registro de pagos a proveedores.',
        purpose:
            'Quien solicita o ejecuta un trabajo no lo autoriza. Finanzas autoriza y después registra el pago con su referencia.',
        keySteps: [
            'Revisa las solicitudes de pago pendientes.',
            'Autoriza (o rechaza con motivo).',
            'Registra el pago con su referencia bancaria.',
        ],
        steps: [
            header(
                'Pagos a proveedores',
                'Quien solicita no autoriza: Finanzas autoriza y después registra el pago.',
            ),
            filters('Filtra por estado o proveedor.'),
            list(
                'Solicitudes',
                'Usa «Autorizar», «Registrar pago» o «Rechazar» en cada fila según tu permiso.',
            ),
        ],
    }),
    mod({
        id: 'openpay',
        group: 'Finanzas',
        title: 'Openpay',
        href: '/admin/openpay',
        permission: 'openpay.diagnostics',
        summary: 'Configura y prueba los cobros con tarjeta.',
        purpose:
            'Conecta tu cuenta de Openpay: llaves, webhook y prueba de conexión. Sigue la lista «Pasos para activar cobros»: cada paso se marca solo cuando queda listo.',
        keySteps: [
            'Pega el ID de comercio y las llaves (empieza en sandbox).',
            'Genera usuario y contraseña del webhook y regístralo en Openpay.',
            'Pulsa «Probar conexión».',
            'Haz un pago de prueba con las tarjetas de sandbox.',
            'Cuando todo esté verde, activa producción.',
        ],
        steps: [
            header(
                'Cobros con tarjeta (Openpay)',
                'Configura tu cuenta, verifica el webhook y prueba la conexión.',
            ),
            card(
                'Pasos para activar cobros',
                'Pasos para activar cobros',
                'Tu lista de pendientes. Cada paso se marca en verde cuando queda listo.',
            ),
            card(
                'Credenciales de Openpay',
                'Credenciales',
                'ID de comercio, llave pública y privada. La privada se guarda cifrada y luego solo ves sus últimos 4 caracteres.',
            ),
            card(
                'Webhook',
                'Webhook',
                'Copia la URL y regístrala en Openpay con el mismo usuario y contraseña. Así nos avisa de pagos y reembolsos.',
            ),
            action(
                'Probar conexión',
                'Probar conexión',
                'Solo consulta: no cobra nada. Debe decir «Conexión correcta».',
            ),
        ],
    }),

    // ── Contenido ──────────────────────────────────────────────────────
    mod({
        id: 'sitio',
        group: 'Contenido',
        title: 'Sitio web',
        href: '/admin/sitio',
        permission: 'content.manage',
        summary: 'Portada, páginas propias, menú, marca, contacto y SEO.',
        purpose:
            'Controla lo que ve el público: edita la portada con el editor visual, crea páginas propias, ordena el menú y actualiza la identidad, el contacto y las páginas legales.',
        keySteps: [
            'Edita la portada con el editor visual y publícala.',
            'Crea páginas propias desde «Páginas».',
            'Ordena el menú del sitio en «Menú».',
            'Actualiza logotipo, contacto, redes y SEO.',
        ],
        steps: [
            header(
                'Sitio web',
                'Todo lo que ve el público, organizado en pestañas.',
            ),
            tabs(
                'Pestañas del sitio',
                'Portada, páginas, menú, identidad, contacto, redes, SEO, pie, testimonios y páginas legales.',
            ),
            tab(
                'Portada',
                'Portada y editor visual',
                'Abre el editor visual para acomodar secciones y bloques. Los cambios se guardan como borrador hasta que publicas.',
            ),
            tab(
                'Páginas',
                'Páginas propias',
                'Crea páginas nuevas (servicio, galería, nosotros…) con el mismo editor. Solo se ven al publicarlas.',
            ),
            tab(
                'Menú',
                'Menú del sitio',
                'Ordena los enlaces de la cabecera, agrega submenús y elige el botón destacado.',
            ),
            actions(
                'Ver sitio',
                'Abre el sitio público en otra pestaña para revisar cómo quedó.',
            ),
        ],
        extraTours: [
            {
                id: 'admin.sitio.editor',
                title: 'Recorrido: editor visual',
                description:
                    'Secciones, bloques, vista por dispositivo y publicar.',
                startRoute: '/admin/sitio/editor',
                match: /^\/admin\/sitio\/(editor|paginas-web\/[^/]+\/editor)\/?$/,
                steps: [
                    note(
                        'Editor visual',
                        'Aquí acomodas la página tal como se verá. Todo se guarda solo como borrador; nada cambia en el sitio hasta que publicas.',
                    ),
                    {
                        target: 'css:[aria-label="Secciones y elementos"]',
                        title: 'Secciones y elementos',
                        body: 'Agrega secciones, cámbialas de orden u ocúltalas. En «Elementos» arrastras textos, fotos, botones y más al lienzo.',
                        optional: true,
                    },
                    {
                        target: 'css:[aria-label="Lienzo de la página"]',
                        title: 'El lienzo',
                        body: 'Arrastra y redimensiona los bloques. Las guías te ayudan a alinear. Tableta y móvil se acomodan solos.',
                        optional: true,
                    },
                    {
                        target: 'css:[aria-label="Vista del dispositivo"]',
                        title: 'Vista por dispositivo',
                        body: 'Revisa cómo se ve en escritorio, tableta y móvil antes de publicar.',
                        optional: true,
                    },
                    {
                        target: 'css:[aria-label="Propiedades"]',
                        title: 'Propiedades',
                        body: 'Al seleccionar un bloque, aquí cambias su texto, colores, enlace y movimiento.',
                        optional: true,
                    },
                    note(
                        'Publicar e historial',
                        'Cuando esté listo, pulsa «Publicar». Cada publicación queda en el historial y puedes restaurar una versión anterior.',
                    ),
                ],
            },
        ],
    }),
    mod({
        id: 'catalogo',
        group: 'Contenido',
        title: 'Catálogo',
        href: '/admin/catalogo',
        permission: 'catalog.manage',
        summary: 'Categorías, productos y acabados del sitio.',
        purpose:
            'Lo que se muestra en el catálogo público. Solo lo publicado es visible; los borradores se pueden preparar con calma.',
        keySteps: [
            'Crea las categorías (cocinas, clósets, baños…).',
            'Agrega productos con «Nuevo producto», fotos y acabados.',
            'Publica el producto cuando esté listo.',
        ],
        steps: [
            header(
                'Catálogo',
                'Categorías, productos y acabados. Solo lo publicado aparece en el sitio.',
            ),
            tabs(
                'Productos, categorías y acabados',
                'Cada pestaña administra una parte del catálogo.',
            ),
            action(
                'Nuevo producto',
                'Nuevo producto',
                'Nombre, descripción, fotos, categoría, acabados y datos para buscadores.',
            ),
            list(
                'Lista',
                'Edita o publica cada elemento. Ordena para decidir qué aparece primero.',
            ),
        ],
    }),
    mod({
        id: 'inspiracion',
        group: 'Contenido',
        title: 'Inspiración',
        href: '/admin/inspiracion',
        permission: 'catalog.manage',
        summary: 'Proyectos entregados y renders conceptuales.',
        purpose:
            'La galería de ideas del sitio. El sitio siempre indica si es un proyecto real entregado o un render conceptual.',
        keySteps: [
            'Crea un proyecto con «Nuevo proyecto».',
            'Sube las imágenes y marca si es real o render.',
            'Publícalo para que aparezca en el sitio.',
        ],
        steps: [
            header(
                'Inspiración',
                'Proyectos entregados y renders conceptuales para el sitio.',
            ),
            action(
                'Nuevo proyecto',
                'Nuevo proyecto de inspiración',
                'Título, historia, imágenes y si es real o render.',
            ),
            filters('Filtra por tipo, estado o busca por título.'),
            list('Proyectos', 'Edita, ordena o publica cada proyecto.'),
        ],
    }),
    mod({
        id: 'multimedia',
        group: 'Contenido',
        title: 'Multimedia',
        href: '/admin/multimedia',
        permission: 'media.manage',
        summary: 'Biblioteca de imágenes, videos y documentos del sitio.',
        purpose:
            'Sube aquí las fotos y videos del sitio. Las imágenes se optimizan solas en varios tamaños y puedes ver dónde se usa cada archivo antes de borrarlo.',
        keySteps: [
            'Pulsa «Subir archivos» y arrastra tus fotos o videos.',
            'Agrega texto alternativo para accesibilidad y buscadores.',
            'Organiza en colecciones.',
            'Antes de borrar, revisa dónde se usa.',
        ],
        steps: [
            header(
                'Multimedia',
                'Biblioteca de imágenes, videos y documentos del sitio.',
            ),
            action(
                'Subir archivos',
                'Sube archivos',
                'Arrastra fotos, videos o PDF. Se optimizan solos en varios tamaños.',
            ),
            filters('Filtra por tipo o colección y busca por nombre.'),
            list(
                'Biblioteca',
                'Selecciona un archivo para editar su texto alternativo, ver dónde se usa o eliminarlo.',
            ),
        ],
    }),
    mod({
        id: 'preguntas',
        group: 'Contenido',
        title: 'Preguntas frecuentes',
        href: '/admin/preguntas',
        permission: 'content.manage',
        summary: 'Preguntas y respuestas del sitio, por tema.',
        purpose:
            'Resuelve dudas comunes antes de que el cliente pregunte. Aparecen en la página de preguntas y en la portada.',
        keySteps: [
            'Crea una con «Nueva pregunta» y elige su tema.',
            'Ordénalas arrastrando o con las flechas.',
            'Publica u oculta cada pregunta.',
        ],
        steps: [
            header(
                'Preguntas frecuentes',
                'Agrupadas por tema. Arrastra o usa las flechas para ordenarlas.',
            ),
            action(
                'Nueva pregunta',
                'Nueva pregunta',
                'Escribe la pregunta y la respuesta, y elige el tema.',
            ),
            content(
                'Por tema',
                'Cada tema con sus preguntas en orden. Edita, oculta o reordena desde aquí.',
            ),
        ],
    }),

    // ── Administración ─────────────────────────────────────────────────
    mod({
        id: 'usuarios',
        group: 'Administración',
        title: 'Usuarios',
        href: '/admin/usuarios',
        permission: 'users.manage',
        summary: 'Cuentas del equipo, clientes y técnicos.',
        purpose:
            'Invita al personal interno, asigna su rol y controla su acceso. También ves las cuentas de clientes y técnicos.',
        keySteps: [
            'Pulsa «Invitar al equipo» y elige el rol.',
            'La persona recibe un correo para crear su contraseña.',
            'Desactiva una cuenta si alguien deja el equipo.',
            'Reenvía la invitación o restablece los dos pasos si hace falta.',
        ],
        steps: [
            header('Usuarios', 'Cuentas del equipo, clientes y técnicos.'),
            action(
                'Invitar al equipo',
                'Invitar al equipo',
                'Escribe nombre, correo y rol. Recibe un enlace válido por 72 horas.',
            ),
            tabs(
                'Equipo, clientes y técnicos',
                'Cada tipo de cuenta en su pestaña.',
            ),
            filters('Busca por nombre o correo y filtra por rol o estado.'),
            list(
                'Cuentas',
                'Cambia el rol, desactiva, reenvía la invitación o restablece la verificación en dos pasos.',
            ),
        ],
    }),
    mod({
        id: 'roles',
        group: 'Administración',
        title: 'Roles y permisos',
        href: '/admin/roles',
        permission: 'roles.manage',
        summary: 'Qué puede ver y hacer cada rol.',
        purpose:
            'Ajusta los permisos de cada rol. Los cambios aplican de inmediato a todas las personas con ese rol y quedan en la bitácora. El superadministrador siempre puede todo.',
        keySteps: [
            'Elige el rol.',
            'Activa o desactiva cada permiso.',
            'Guarda; aplica de inmediato.',
            'Si te equivocas, usa «Restablecer» para volver a los valores de fábrica.',
        ],
        steps: [
            header(
                'Roles y permisos',
                'Decide qué puede ver y hacer cada rol.',
            ),
            content(
                'Matriz de permisos',
                'Cada rol con sus permisos agrupados por módulo. Los menús y botones se ocultan solos si no hay permiso.',
            ),
        ],
    }),
    mod({
        id: 'actividad',
        group: 'Administración',
        title: 'Actividad',
        href: '/admin/actividad',
        permission: 'activity.view',
        summary: 'Bitácora: quién hizo qué y cuándo.',
        purpose:
            'Cada cambio importante queda registrado con antes y después. Las contraseñas, llaves y datos de tarjeta nunca se muestran.',
        keySteps: [
            'Filtra por persona, módulo o fecha.',
            'Abre un registro para ver qué cambió.',
        ],
        steps: [
            header(
                'Actividad',
                'Bitácora de la plataforma: quién hizo qué, cuándo y qué cambió.',
            ),
            filters('Filtra por persona, módulo, acción o fecha.'),
            content(
                'Registros',
                'Cada registro muestra el antes y el después. Los datos sensibles se ocultan siempre.',
            ),
        ],
    }),
    mod({
        id: 'estadisticas',
        group: 'Administración',
        title: 'Estadísticas',
        href: '/admin/estadisticas',
        permission: 'dashboard.view',
        summary: 'Visitas al sitio público, sin cookies.',
        purpose:
            'Cuánta gente visita el sitio y qué páginas ve. Datos anónimos y agregados; no cuenta al personal con sesión iniciada.',
        keySteps: [
            'Elige el periodo (7, 30 o 90 días).',
            'Revisa las secciones y páginas más vistas.',
        ],
        steps: [
            header(
                'Estadísticas del sitio',
                'Visitas al sitio público: anónimas, sin cookies.',
            ),
            actions('Periodo', 'Cambia el número de días que se muestran.'),
            card(
                'Visitas por sección del sitio',
                'Por sección',
                'Qué partes del sitio atraen más visitas.',
            ),
            {
                target: 'css:#contenido [data-slot="empty-state"]',
                title: 'Aún sin visitas',
                body: 'Cuando el sitio reciba visitas, aquí verás las secciones y páginas más vistas.',
                optional: true,
            },
            card(
                'Páginas más vistas',
                'Páginas más vistas',
                'Las páginas con más visitas en el periodo.',
            ),
        ],
    }),
    mod({
        id: 'configuracion',
        group: 'Administración',
        title: 'Configuración',
        href: '/admin/configuracion',
        permission: 'settings.manage',
        summary: 'Parámetros de negocio: comercial, asesorías, seguridad…',
        purpose:
            'Valores que usan cotizaciones, contratos, asesorías y seguimiento: anticipo, precio y duración de la asesoría, enlace de videollamada y más. Cada cambio queda en la bitácora.',
        keySteps: [
            'En «Comercial»: anticipo, vigencias e impuestos.',
            'En «Asesorías»: precio (total, sin sumar IVA), duración y videollamada (Jitsi automático o manual).',
            'En «Prospectos»: tiempos de seguimiento.',
            'Guarda cada sección por separado.',
        ],
        steps: [
            header(
                'Configuración',
                'Parámetros de negocio. Cada cambio queda en la bitácora.',
            ),
            card(
                'Comercial',
                'Comercial',
                'Anticipo, vigencias e impuestos que usan cotizaciones y contratos.',
            ),
            card(
                'Asesorías',
                'Asesorías',
                'Precio total que se cobra, duración y cómo se crea el enlace de la videollamada. El precio se congela en cada cita.',
            ),
            card(
                'Seguridad',
                'Seguridad',
                'Reglas de acceso del equipo, como la verificación en dos pasos.',
            ),
        ],
    }),
    mod({
        id: 'almacenamiento',
        group: 'Administración',
        title: 'Almacenamiento',
        href: '/admin/almacenamiento',
        permission: 'settings.manage',
        summary: 'Dónde se guardan fotos, renders, videos y documentos.',
        purpose:
            'Elige dónde se guardan los archivos nuevos: el servidor o el Synology de la oficina (montado en el servidor). Los archivos que ya existen se quedan donde están hasta que los muevas.',
        keySteps: [
            'Revisa el estado y el espacio del Synology.',
            'Pulsa «Probar conexión» para verificarlo.',
            'Actívalo cuando la prueba salga bien.',
            'Mueve la multimedia existente si lo necesitas.',
        ],
        steps: [
            header(
                'Almacenamiento',
                'Dónde se guardan los archivos de la plataforma.',
            ),
            {
                target: 'storage-synology',
                title: 'Synology',
                body: 'Estado, espacio y resultado de la última prueba del NAS. Prueba la conexión y actívalo cuando salga bien.',
                optional: true,
            },
            {
                target: 'storage-local',
                title: 'Servidor',
                body: 'El respaldo siempre disponible. Los documentos privados de clientes siempre se quedan aquí.',
                optional: true,
            },
            {
                target: 'storage-migration',
                title: 'Mover multimedia',
                body: 'Ve dónde está cada archivo y pásalos entre el servidor y el Synology, con el avance en vivo.',
                optional: true,
            },
            content(
                'Proveedor activo',
                'Solo un proveedor está activo a la vez. Cambiarlo no mueve los archivos que ya existen.',
            ),
        ],
    }),
    mod({
        id: 'ayuda',
        group: 'Ayuda',
        title: 'Centro de ayuda',
        href: '/admin/ayuda',
        icon: LifeBuoy,
        summary: 'Guía de cada módulo y sus recorridos.',
        purpose:
            'Consulta para qué sirve cada módulo y sus pasos clave, e inicia el recorrido de cualquiera.',
        keySteps: [
            'Busca el módulo por nombre.',
            'Lee para qué sirve y sus pasos clave.',
            'Pulsa «Iniciar recorrido» para verlo en pantalla.',
        ],
        steps: [
            header(
                'Centro de ayuda',
                'Cada módulo al que tienes acceso, con su guía y su recorrido.',
            ),
            {
                target: 'help-search',
                title: 'Busca un módulo',
                body: 'Escribe lo que buscas: «pagos», «visita», «menú»…',
                optional: true,
            },
            {
                target: 'help-modules',
                title: 'Fichas de módulo',
                body: 'Cada ficha explica para qué sirve y sus pasos clave. «Iniciar recorrido» te lleva a la pantalla y te la muestra.',
                optional: true,
            },
        ],
    }),
];

/* ── Recorrido general del panel ──────────────────────────────────── */

const generalIntro: TourStep[] = [
    note(
        'Te damos la bienvenida a ML Habitat',
        'Este recorrido te muestra cada parte del panel en pocos minutos. Solo verás los módulos a los que tienes acceso.\n\nPuedes salir cuando quieras y retomarlo desde el botón de ayuda (?).',
    ),
    {
        target: ['sidebar', 'sidebar-trigger'],
        title: 'El menú',
        body: 'Los módulos están agrupados por área: Comercial, Proyectos, Operación, Finanzas, Contenido y Administración. Cada grupo se puede plegar. En el celular, ábrelo con este botón.',
        placement: 'right',
    },
    {
        target: 'help-button',
        title: 'Ayuda siempre a la mano',
        body: 'Desde aquí repites este recorrido, ves el de la pantalla en la que estás o abres el centro de ayuda.',
    },
    {
        target: 'notifications',
        title: 'Notificaciones',
        body: 'Avisos de pagos, citas, aprobaciones y asignaciones. El número indica cuántas no has leído.',
        optional: true,
    },
];

/** Paso del recorrido general para un módulo: lo abre y lo señala. */
function visitStep(
    href: string,
    title: string,
    body: string,
    permission?: string | string[],
): TourStep {
    return {
        route: href,
        target: [navTourKey(href), 'page-header'],
        title,
        body,
        permission,
        placement: 'right',
    };
}

const generalSteps: TourStep[] = [
    ...generalIntro,
    {
        route: '/admin',
        target: ['card:Requiere tu atención', 'page-header'],
        title: 'Inicio: lo urgente primero',
        body: '«Requiere tu atención» junta los pendientes de todas las áreas. A un lado, la agenda de hoy.',
        permission: 'dashboard.view',
    },
    visitStep(
        '/admin/prospectos',
        'Comercial · Prospectos',
        'Cada solicitud del sitio llega aquí con folio. Da seguimiento, cambia la etapa y, cuando esté listo, crea el proyecto.',
        'leads.view',
    ),
    visitStep(
        '/admin/clientes',
        'Comercial · Clientes',
        'El historial completo de cada persona: solicitudes, asesorías y proyectos. Invítalos a su portal.',
        'customers.view',
    ),
    visitStep(
        '/admin/asesorias',
        'Comercial · Asesorías',
        'Citas remotas pagadas desde el sitio. Copia el enlace de la videollamada y registra el resultado.',
        'consultations.view',
    ),
    visitStep(
        '/admin/visitas',
        'Comercial · Visitas técnicas',
        'Visitas a domicilio pagadas con tarjeta. En «Política» defines tarifa, crédito abonable y condiciones.',
        'visits.view',
    ),
    visitStep(
        '/admin/precalificaciones',
        'Comercial · Precalificación',
        'Revisión manual de solicitudes de pago a plazos. Nunca es una aprobación de crédito.',
        'prequalification.view',
    ),
    visitStep(
        '/admin/cotizaciones',
        'Comercial · Cotizaciones',
        'Todas las cotizaciones. Se arman desde el proyecto y el cliente las acepta en su portal.',
        'quotes.view',
    ),
    visitStep(
        '/admin/proyectos',
        'Proyectos',
        'El corazón del trabajo: medición, diseño, cotización, contrato y operación de cada proyecto.',
        'projects.view',
    ),
    visitStep(
        '/admin/disenos',
        'Proyectos · Diseño',
        'Versiones de diseño. Fabricación solo arranca con la aprobación técnica.',
        'designs.view',
    ),
    visitStep(
        '/admin/fabricacion',
        'Proyectos · Fabricación',
        'Órdenes a fabricantes con fechas comprometidas y su avance.',
        'fabrication.view',
    ),
    visitStep(
        '/admin/instalaciones',
        'Proyectos · Instalaciones',
        'Órdenes de instalación con tarifario congelado, evidencia y conformidad del cliente.',
        'installations.view',
    ),
    visitStep(
        '/admin/calidad',
        'Proyectos · Calidad',
        'Revisión de la evidencia contra el checklist antes de cerrar y pagar.',
        'quality.view',
    ),
    visitStep(
        '/admin/incidencias',
        'Proyectos · Incidencias',
        'Faltantes, daños y errores con su plan de acción hasta resolverlos.',
        'incidents.view',
    ),
    visitStep(
        '/admin/garantias',
        'Proyectos · Garantías',
        'Garantías de cada proyecto entregado y los reclamos del cliente.',
        'warranties.view',
    ),
    visitStep(
        '/admin/agenda',
        'Operación · Agenda',
        'Horarios de asesores y técnicos. Es exactamente lo que el cliente ve al agendar.',
        'schedule.view',
    ),
    visitStep(
        '/admin/cobertura',
        'Operación · Cobertura',
        'Zonas y códigos postales donde atendemos. Sin cobertura no se venden visitas.',
        'coverage.view',
    ),
    visitStep(
        '/admin/tecnicos',
        'Operación · Técnicos',
        'Quién mide y quién instala, en qué zonas y con cuánta carga.',
        'suppliers.view',
    ),
    visitStep(
        '/admin/proveedores',
        'Operación · Proveedores',
        'Fabricantes e instaladores: contactos, documentos y desempeño.',
        'suppliers.view',
    ),
    visitStep(
        '/admin/tarifarios',
        'Operación · Tarifarios',
        'Precios de instalación por módulo; se congelan en cada orden.',
        'rate_cards.view',
    ),
    visitStep(
        '/admin/pagos',
        'Finanzas · Pagos',
        'Cobros con tarjeta y pagos manuales: estado, reembolsos y conciliación.',
        'payments.view',
    ),
    visitStep(
        '/admin/pagos-proveedores',
        'Finanzas · Pagos a proveedores',
        'Quien solicita no autoriza: Finanzas autoriza y registra cada pago.',
        'payouts.view',
    ),
    visitStep(
        '/admin/openpay',
        'Finanzas · Openpay',
        'Conecta los cobros con tarjeta. Sigue la lista «Pasos para activar cobros».',
        'openpay.diagnostics',
    ),
    visitStep(
        '/admin/sitio',
        'Contenido · Sitio web',
        'Edita la portada con el editor visual, crea páginas propias, ordena el menú y actualiza la marca y el contacto.',
        'content.manage',
    ),
    visitStep(
        '/admin/catalogo',
        'Contenido · Catálogo',
        'Categorías, productos y acabados del sitio. Solo lo publicado es visible.',
        'catalog.manage',
    ),
    visitStep(
        '/admin/inspiracion',
        'Contenido · Inspiración',
        'Proyectos entregados y renders conceptuales; el sitio indica cuál es cuál.',
        'catalog.manage',
    ),
    visitStep(
        '/admin/multimedia',
        'Contenido · Multimedia',
        'La biblioteca de fotos, videos y documentos. Las imágenes se optimizan solas.',
        'media.manage',
    ),
    visitStep(
        '/admin/preguntas',
        'Contenido · Preguntas frecuentes',
        'Respuestas a las dudas más comunes, ordenadas por tema.',
        'content.manage',
    ),
    visitStep(
        '/admin/usuarios',
        'Administración · Usuarios',
        'Invita al equipo y controla su rol y acceso.',
        'users.manage',
    ),
    visitStep(
        '/admin/roles',
        'Administración · Roles y permisos',
        'Qué puede ver y hacer cada rol. Aplica de inmediato.',
        'roles.manage',
    ),
    visitStep(
        '/admin/actividad',
        'Administración · Actividad',
        'La bitácora: quién hizo qué, cuándo y qué cambió.',
        'activity.view',
    ),
    visitStep(
        '/admin/estadisticas',
        'Administración · Estadísticas',
        'Visitas al sitio público, anónimas y sin cookies.',
        'dashboard.view',
    ),
    visitStep(
        '/admin/configuracion',
        'Administración · Configuración',
        'Parámetros de negocio: anticipo, precio de la asesoría, videollamada y más.',
        'settings.manage',
    ),
    visitStep(
        '/admin/almacenamiento',
        'Administración · Almacenamiento',
        'Dónde se guardan los archivos (por ejemplo, tu Synology).',
        'settings.manage',
    ),
    {
        route: '/admin/ayuda',
        target: ['help-general', 'page-header'],
        title: '¡Listo! Tu centro de ayuda',
        body: 'Aquí tienes la guía de cada módulo y su recorrido corto. Vuelve cuando quieras desde el botón de ayuda (?).',
    },
];

export const adminGeneralTour: TourDefinition = {
    id: 'admin.general',
    area: 'admin',
    title: 'Recorrido general',
    description: 'Un vistazo a todo el panel, módulo por módulo.',
    steps: generalSteps,
};
