import { technicianTabs } from '@/lib/navigation';
import { navTourKey, tabTourKey } from '../keys';
import type { HelpModule, TourDefinition, TourStep } from '../types';
import { detail, exact, note } from './helpers';

/**
 * Ayuda y recorridos de la app de campo (/tecnico). Pantallas pensadas
 * para el celular: textos cortos y directos.
 */
const iconFor = (href: string) =>
    technicianTabs.find((i) => i.href === href)?.icon;

const section = (id: string, title: string, body: string): TourStep => ({
    target: `css:[aria-labelledby="${id}"]`,
    title,
    body,
    optional: true,
});

const pageTitle = (title: string, body: string): TourStep => ({
    target: 'css:#contenido h1',
    title,
    body,
});

type Input = Omit<HelpModule, 'area' | 'tour' | 'group'> & {
    steps: TourStep[];
    match?: RegExp;
};

function mod(input: Input): HelpModule {
    const { steps, match, ...rest } = input;

    return {
        group: 'App de campo',
        ...rest,
        area: 'tecnico',
        icon: rest.icon ?? iconFor(rest.href),
        tour: {
            id: `tecnico.${rest.id}`,
            area: 'tecnico',
            title: `Recorrido: ${rest.title}`,
            description: rest.summary,
            startRoute: rest.href,
            match: match ?? exact(rest.href),
            steps,
        },
    };
}

export const technicianModules: HelpModule[] = [
    mod({
        id: 'hoy',
        title: 'Hoy',
        href: '/tecnico',
        summary: 'Tus visitas e instalaciones del día.',
        purpose: 'Abre la app y ve directo lo que te toca hoy, en orden.',
        keySteps: [
            'Revisa tus visitas e instalaciones de hoy.',
            'Toca una para ver la dirección y los detalles.',
        ],
        steps: [
            pageTitle('Tu día', 'Lo que te toca hoy, en orden de hora.'),
            section(
                'hoy-visitas',
                'Visitas de hoy',
                'Visitas de medición. Toca una para ver la dirección y empezar a medir.',
            ),
            section(
                'hoy-instalaciones',
                'Instalaciones de hoy',
                'Instalaciones asignadas. Desde ahí subes la evidencia de cada módulo.',
            ),
        ],
    }),
    mod({
        id: 'visitas',
        title: 'Visitas',
        href: '/tecnico/visitas',
        summary: 'Tus visitas de medición próximas y pasadas.',
        purpose:
            'Cada visita con la dirección, el cliente y lo que necesita. Al llegar, capturas la medición desde aquí.',
        keySteps: [
            'Abre la visita y revisa la dirección.',
            'Al llegar, captura la medición: espacio, medidas, fotos y croquis.',
            'Marca la visita como realizada (o «Cliente ausente»).',
        ],
        steps: [
            pageTitle('Mis visitas', 'Tus visitas de medición.'),
            section(
                'visitas-proximas',
                'Próximas',
                'Las que vienen. Toca una para ver la dirección y el detalle.',
            ),
            section(
                'visitas-pasadas',
                'Recientes',
                'Las que ya hiciste, por si necesitas revisar una medición.',
            ),
        ],
    }),
    mod({
        id: 'instalaciones',
        title: 'Instalaciones',
        href: '/tecnico/instalaciones',
        summary: 'Tus instalaciones y su avance.',
        purpose:
            'Las órdenes de instalación asignadas, con sus partidas. Sube evidencia de cada módulo para que el cliente dé su conformidad y Calidad revise.',
        keySteps: [
            'Abre la instalación y revisa las partidas.',
            'Sube fotos de cada módulo terminado.',
            'Atiende lo que Calidad marque por corregir.',
        ],
        steps: [
            pageTitle('Instalaciones', 'Tus órdenes de instalación.'),
            section(
                'activas',
                'Activas',
                'Las que estás trabajando, con el avance de partidas.',
            ),
            section('historial', 'Historial', 'Las que ya terminaste.'),
        ],
    }),
];

export const technicianDetailTours: TourDefinition[] = [
    {
        id: 'tecnico.visita',
        area: 'tecnico',
        title: 'Recorrido: la visita',
        match: detail('/tecnico/visitas'),
        steps: [
            pageTitle('La visita', 'Cliente, fecha y hora.'),
            section(
                'visita-lugar',
                'El lugar',
                'Dirección y referencias. Ábrela en tu app de mapas.',
            ),
            section(
                'visita-mediciones',
                'Mediciones',
                'Empieza o continúa la medición de esta visita.',
            ),
        ],
    },
    {
        id: 'tecnico.medicion',
        area: 'tecnico',
        title: 'Recorrido: medición',
        match: detail('/tecnico/mediciones'),
        steps: [
            pageTitle(
                'Medición',
                'Se guarda por versiones: puedes corregir después sin perder lo anterior.',
            ),
            section(
                'med-espacio',
                'El espacio',
                'Tipo de espacio y medidas generales.',
            ),
            section(
                'med-fotos',
                'Fotos',
                'Toma fotos de cada pared y detalle.',
            ),
            section(
                'med-croquis',
                'Croquis',
                'Sube el croquis a mano o una foto de tus notas.',
            ),
        ],
    },
    {
        id: 'tecnico.instalacion',
        area: 'tecnico',
        title: 'Recorrido: la instalación',
        match: detail('/tecnico/instalaciones'),
        steps: [
            pageTitle('La instalación', 'El proyecto y su avance.'),
            section(
                'pendientes',
                'Por corregir',
                'Lo que Calidad pidió corregir. Atiéndelo primero.',
            ),
            section(
                'partidas',
                'Partidas',
                'Cada módulo a instalar. Sube su evidencia al terminarlo.',
            ),
        ],
    },
];

export const technicianGeneralTour: TourDefinition = {
    id: 'tecnico.general',
    area: 'tecnico',
    title: 'Conoce la app de campo',
    description: 'Tus visitas, mediciones e instalaciones.',
    steps: [
        note(
            'Te damos la bienvenida',
            'En esta app ves tus visitas de medición y tus instalaciones, y subes medidas y evidencia desde el celular.',
        ),
        {
            route: '/tecnico',
            target: [navTourKey('/tecnico'), tabTourKey('/tecnico')],
            title: 'Hoy',
            body: 'Lo que te toca hoy, en orden de hora.',
        },
        {
            route: '/tecnico/visitas',
            target: [
                navTourKey('/tecnico/visitas'),
                tabTourKey('/tecnico/visitas'),
            ],
            title: 'Visitas',
            body: 'Tus visitas de medición. Al llegar, captura medidas, fotos y croquis.',
        },
        {
            route: '/tecnico/instalaciones',
            target: [
                navTourKey('/tecnico/instalaciones'),
                tabTourKey('/tecnico/instalaciones'),
            ],
            title: 'Instalaciones',
            body: 'Tus instalaciones con sus partidas. Sube la evidencia de cada módulo.',
        },
        {
            target: [
                navTourKey('/settings/profile'),
                tabTourKey('/settings/profile'),
            ],
            title: 'Perfil',
            body: 'Tus datos y tu contraseña.',
            optional: true,
        },
        {
            target: 'help-button',
            title: 'Ayuda',
            body: 'Con este botón repites el recorrido o ves el de la pantalla en la que estás.',
        },
    ],
};
