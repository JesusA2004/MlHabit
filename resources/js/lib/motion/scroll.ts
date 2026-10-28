/**
 * Efectos ligados al scroll, con un único listener pasivo y un solo rAF:
 * - `--scroll-progress` (0–1) en <html> para barras y anillos de progreso.
 * - Parallax en `[data-parallax="0.15"]` (solo mientras está en pantalla).
 *   Escribe `--parallax-y`; el CSS lo traduce en `transform`.
 * - Progreso de un elemento en `[data-scroll-progress]` (0–1, `--progress`):
 *     · `cover` (por defecto): 0 cuando su borde superior entra por abajo,
 *       1 cuando su borde inferior sale por arriba. Para derivas y giros
 *       (`data-drift`, `data-spin`, ver motion.css).
 *     · `track`: 0 cuando su borde superior cruza la "línea de lectura"
 *       (60 % de la vista), 1 cuando la cruza su borde inferior. Para
 *       líneas de tiempo que se rellenan al leer.
 *   Dentro de un `track`, cada `[data-scroll-step]` recibe `data-passed`
 *   cuando la línea de lectura lo alcanza (estado "ya leído").
 *
 * Con movimiento reducido no hay observador: `--progress` no se escribe y
 * el CSS muestra el estado final (líneas completas, pasos activos).
 */

const visible = new Set<HTMLElement>();
/** Todo lo que este motor marcó (para desmarcarlo al reiniciar). */
const bound = new Set<HTMLElement>();

/** Línea de lectura: fracción de la altura de la vista. */
const READ_LINE = 0.6;

export type ScrollEngine = {
    scan: (root: ParentNode) => void;
    update: () => void;
    destroy: () => void;
};

function clamp01(value: number): number {
    return Math.min(1, Math.max(0, value));
}

function measureProgress(el: HTMLElement, viewport: number): void {
    const rect = el.getBoundingClientRect();
    const mode = el.dataset.scrollProgress || 'cover';
    let progress: number;

    if (mode === 'track') {
        const line = viewport * READ_LINE;
        progress = clamp01((line - rect.top) / Math.max(1, rect.height));

        el.querySelectorAll<HTMLElement>('[data-scroll-step]').forEach(
            (step) => {
                const top = step.getBoundingClientRect().top;
                step.toggleAttribute('data-passed', top < line);
            },
        );
    } else {
        progress = clamp01(
            (viewport - rect.top) / Math.max(1, viewport + rect.height),
        );
    }

    el.style.setProperty('--progress', progress.toFixed(4));
}

export function createScrollEngine(options: {
    parallax: boolean;
}): ScrollEngine {
    const root = document.documentElement;
    let frame = 0;

    const observer =
        options.parallax && 'IntersectionObserver' in window
            ? new IntersectionObserver(
                  (entries) => {
                      for (const entry of entries) {
                          const el = entry.target as HTMLElement;

                          if (entry.isIntersecting) {
                              visible.add(el);
                          } else {
                              visible.delete(el);
                          }
                      }

                      schedule();
                  },
                  { rootMargin: '20% 0px 20% 0px' },
              )
            : null;

    function measure(): void {
        frame = 0;

        const max = Math.max(1, root.scrollHeight - window.innerHeight);
        const progress = Math.min(1, Math.max(0, window.scrollY / max));
        root.style.setProperty('--scroll-progress', progress.toFixed(4));
        root.dataset.scrolled = window.scrollY > 24 ? 'true' : 'false';

        if (!observer) {
            return;
        }

        const viewport = window.innerHeight;

        visible.forEach((el) => {
            if (!el.isConnected) {
                visible.delete(el);

                return;
            }

            if (el.hasAttribute('data-scroll-progress')) {
                measureProgress(el, viewport);
            }

            if (!el.hasAttribute('data-parallax')) {
                return;
            }

            const speed = Number.parseFloat(el.dataset.parallax ?? '0') || 0;
            const rect = el.getBoundingClientRect();
            // Distancia del centro del elemento al centro de la vista.
            const delta = rect.top + rect.height / 2 - viewport / 2;
            const shift = Math.max(-160, Math.min(160, -delta * speed));
            el.style.setProperty('--parallax-y', `${shift.toFixed(1)}px`);
        });
    }

    function schedule(): void {
        if (!frame) {
            frame = requestAnimationFrame(measure);
        }
    }

    window.addEventListener('scroll', schedule, { passive: true });
    window.addEventListener('resize', schedule, { passive: true });
    schedule();

    return {
        scan(scope: ParentNode) {
            if (!observer) {
                return;
            }

            scope
                .querySelectorAll<HTMLElement>(
                    '[data-parallax]:not([data-parallax-bound]), [data-scroll-progress]:not([data-parallax-bound])',
                )
                .forEach((el) => {
                    el.setAttribute('data-parallax-bound', '');
                    bound.add(el);
                    // Marca que el runtime controla `--progress`: sin esta
                    // marca el CSS muestra el estado final (sin JS, reducido).
                    if (el.hasAttribute('data-scroll-progress')) {
                        el.setAttribute('data-scroll-live', '');
                    }
                    observer.observe(el);
                });
        },
        update: schedule,
        destroy() {
            window.removeEventListener('scroll', schedule);
            window.removeEventListener('resize', schedule);
            observer?.disconnect();
            bound.forEach((el) => {
                el.removeAttribute('data-scroll-live');
                el.removeAttribute('data-parallax-bound');
                el.style.removeProperty('--progress');
                el.style.removeProperty('--parallax-y');
            });
            bound.clear();
            visible.clear();

            if (frame) {
                cancelAnimationFrame(frame);
            }
        },
    };
}
