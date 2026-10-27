/**
 * Efectos ligados al scroll, con un único listener pasivo y un solo rAF:
 * - `--scroll-progress` (0–1) en <html> para barras y anillos de progreso.
 * - Parallax en `[data-parallax="0.15"]` (solo mientras está en pantalla).
 *   Escribe `--parallax-y`; el CSS lo traduce en `transform`.
 */

const visible = new Set<HTMLElement>();

export type ScrollEngine = {
    scan: (root: ParentNode) => void;
    update: () => void;
    destroy: () => void;
};

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
                    '[data-parallax]:not([data-parallax-bound])',
                )
                .forEach((el) => {
                    el.setAttribute('data-parallax-bound', '');
                    observer.observe(el);
                });
        },
        update: schedule,
        destroy() {
            window.removeEventListener('scroll', schedule);
            window.removeEventListener('resize', schedule);
            observer?.disconnect();
            visible.clear();

            if (frame) {
                cancelAnimationFrame(frame);
            }
        },
    };
}
