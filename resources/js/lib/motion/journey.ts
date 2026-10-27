/**
 * Recorrido guiado del sitio: la historia que acompaña a quien visita, de
 * la inspiración a la cita. Las secciones se marcan con
 * `data-journey="<paso>"`; si la página no tiene anclas, el paso se deduce
 * de la ruta. Un solo rAF por scroll; sin dependencias.
 */
import { usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { normalizePath } from '@/lib/navigation';

export type JourneyStepKey =
    | 'inspirate'
    | 'espacio'
    | 'proceso'
    | 'visita'
    | 'pagos'
    | 'agenda';

export type JourneyStep = {
    key: JourneyStepKey;
    label: string;
    /** Destino cuando el paso no está en la página actual. */
    href: string;
    /** Mensaje del botón flotante mientras se está en este paso. */
    cta: { label: string; href: string };
};

export const JOURNEY_STEPS: JourneyStep[] = [
    {
        key: 'inspirate',
        label: 'Inspírate',
        href: '/inspiracion',
        cta: { label: 'Ver catálogo', href: '/catalogo' },
    },
    {
        key: 'espacio',
        label: 'Elige tu espacio',
        href: '/catalogo',
        cta: { label: 'Agenda tu asesoría', href: '/solicitar' },
    },
    {
        key: 'proceso',
        label: 'Así trabajamos',
        href: '/#como-funciona',
        cta: { label: 'Agenda tu asesoría', href: '/solicitar' },
    },
    {
        key: 'visita',
        label: 'Visita y medición',
        href: '/#visita',
        cta: { label: 'Revisa tu cobertura', href: '/solicitar' },
    },
    {
        key: 'pagos',
        label: 'Formas de pago',
        href: '/#pagos',
        cta: { label: 'Solicita tu propuesta', href: '/solicitar' },
    },
    {
        key: 'agenda',
        label: 'Agenda',
        href: '/solicitar',
        cta: { label: 'Solicitar asesoría', href: '/solicitar' },
    },
];

const STEP_KEYS = new Set<string>(JOURNEY_STEPS.map((s) => s.key));

/** Páginas transaccionales: calma total, sin riel ni botón flotante. */
const QUIET_PREFIXES = [
    '/solicitar',
    '/visita',
    '/asesoria',
    '/precalificacion',
    '/pagos',
];

export function isQuietPath(path: string): boolean {
    const clean = normalizePath(path);

    return QUIET_PREFIXES.some(
        (prefix) => clean === prefix || clean.startsWith(`${prefix}/`),
    );
}

/** Paso implícito de la página cuando no hay anclas `data-journey`. */
export function stepForPath(path: string): JourneyStepKey | null {
    const clean = normalizePath(path);

    if (clean.startsWith('/inspiracion')) return 'inspirate';
    if (clean.startsWith('/catalogo')) return 'espacio';
    if (clean.startsWith('/preguntas')) return 'proceso';
    if (clean.startsWith('/visita')) return 'visita';
    if (clean.startsWith('/pagos')) return 'pagos';
    if (isQuietPath(clean)) return 'agenda';
    if (clean === '/') return 'inspirate';

    return null;
}

type Anchor = { key: JourneyStepKey; el: HTMLElement };

function collectAnchors(): Anchor[] {
    const main = document.getElementById('contenido');

    if (!main) {
        return [];
    }

    return Array.from(main.querySelectorAll<HTMLElement>('[data-journey]'))
        .map((el) => ({ key: el.dataset.journey as JourneyStepKey, el }))
        .filter((a) => STEP_KEYS.has(a.key));
}

function intersectsViewport(el: Element, margin = 0): boolean {
    const rect = el.getBoundingClientRect();

    return (
        rect.bottom > margin &&
        rect.top < window.innerHeight - margin &&
        rect.width > 0 &&
        rect.height > 0
    );
}

/**
 * Estado reactivo del recorrido: paso actual, pasos presentes en la página,
 * progreso dentro de la página y si el botón flotante debe ocultarse.
 */
export function useJourney() {
    const page = usePage();
    const current = ref<JourneyStepKey | null>(null);
    const present = ref<JourneyStepKey[]>([]);
    const progress = ref(0);
    const scrolledIn = ref(false);
    const blocked = ref(false);
    const footerVisible = ref(false);

    const quiet = computed(() => isQuietPath(page.url));

    let frame = 0;
    let anchors: Anchor[] = [];
    let refreshTimer = 0;

    function refreshAnchors(): void {
        anchors = collectAnchors();
        const keys = anchors.map((a) => a.key);
        present.value = JOURNEY_STEPS.map((s) => s.key).filter((k) =>
            keys.includes(k),
        );
        schedule();
    }

    function measure(): void {
        frame = 0;

        const viewport = window.innerHeight;
        const line = viewport * 0.42;
        let active: JourneyStepKey | null = null;

        for (const anchor of anchors) {
            if (!anchor.el.isConnected) {
                continue;
            }

            if (anchor.el.getBoundingClientRect().top <= line) {
                active = anchor.key;
            }
        }

        current.value =
            active ??
            (anchors.length ? anchors[0].key : null) ??
            stepForPath(page.url);

        const max = Math.max(
            1,
            document.documentElement.scrollHeight - viewport,
        );
        progress.value = Math.min(1, Math.max(0, window.scrollY / max));
        scrolledIn.value = window.scrollY > viewport * 0.35;

        const footer = document.querySelector('[data-site-footer]');
        footerVisible.value = footer ? intersectsViewport(footer, 40) : false;

        const main = document.getElementById('contenido');
        blocked.value = Boolean(
            main &&
            Array.from(main.querySelectorAll('form, [data-journey-hide]')).some(
                (el) => intersectsViewport(el, 60),
            ),
        );
    }

    function schedule(): void {
        if (!frame) {
            frame = requestAnimationFrame(measure);
        }
    }

    function delayedRefresh(): void {
        // Los bloques del editor pueden montarse en diferido.
        window.clearTimeout(refreshTimer);
        refreshAnchors();
        refreshTimer = window.setTimeout(refreshAnchors, 400);
    }

    onMounted(() => {
        window.addEventListener('scroll', schedule, { passive: true });
        window.addEventListener('resize', schedule, { passive: true });
        delayedRefresh();
    });

    watch(
        () => page.url,
        () => window.setTimeout(delayedRefresh, 30),
    );

    onBeforeUnmount(() => {
        window.removeEventListener('scroll', schedule);
        window.removeEventListener('resize', schedule);
        window.clearTimeout(refreshTimer);

        if (frame) {
            cancelAnimationFrame(frame);
        }
    });

    function anchorFor(key: JourneyStepKey): HTMLElement | null {
        return (
            anchors.find((a) => a.key === key && a.el.isConnected)?.el ?? null
        );
    }

    const currentIndex = computed(() =>
        JOURNEY_STEPS.findIndex((s) => s.key === current.value),
    );

    return {
        steps: JOURNEY_STEPS,
        current,
        currentIndex,
        present,
        progress,
        scrolledIn,
        blocked,
        footerVisible,
        quiet,
        anchorFor,
        refresh: delayedRefresh,
    };
}
