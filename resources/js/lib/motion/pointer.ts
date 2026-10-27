/**
 * Efectos de puntero por delegación (un listener para todo el documento,
 * throttled con rAF). Solo con puntero fino y sin movimiento reducido.
 *
 * - `[data-magnetic]`  el elemento sigue ligeramente al cursor (`--mag-x/y`).
 * - `[data-tilt]`      inclinación 3D + brillo (`--tilt-rx/ry`, `--glare-x/y`).
 * - `[data-spotlight]` foco de luz que sigue al cursor (`--spot-x/y`).
 * - `[data-hover-zoom]` lupa: la imagen se amplía desde el punto del cursor.
 *
 * Todos escriben variables CSS; el aspecto vive en `motion.css`.
 */

type Effect = 'magnetic' | 'tilt' | 'spotlight' | 'zoom';

const SELECTORS: Record<Effect, string> = {
    magnetic: '[data-magnetic]',
    tilt: '[data-tilt]',
    spotlight: '[data-spotlight]',
    zoom: '[data-hover-zoom]',
};

const RESET: Record<Effect, string[]> = {
    magnetic: ['--mag-x', '--mag-y'],
    tilt: ['--tilt-rx', '--tilt-ry', '--glare-o'],
    spotlight: ['--spot-o'],
    zoom: ['--zoom-on'],
};

export function createPointerEngine(): { destroy: () => void } {
    const active = new Map<Effect, HTMLElement>();
    let frame = 0;
    let last: PointerEvent | null = null;

    function release(effect: Effect): void {
        const el = active.get(effect);

        if (!el) {
            return;
        }

        RESET[effect].forEach((prop) => el.style.removeProperty(prop));
        el.removeAttribute('data-pointer-active');
        active.delete(effect);
    }

    function apply(): void {
        frame = 0;

        if (!last) {
            return;
        }

        const event = last;
        const target = event.target instanceof Element ? event.target : null;

        (Object.keys(SELECTORS) as Effect[]).forEach((effect) => {
            const el = target?.closest<HTMLElement>(SELECTORS[effect]) ?? null;

            if (active.get(effect) !== el) {
                release(effect);
            }

            if (!el) {
                return;
            }

            active.set(effect, el);
            el.setAttribute('data-pointer-active', '');

            const rect = el.getBoundingClientRect();
            const x = (event.clientX - rect.left) / Math.max(1, rect.width);
            const y = (event.clientY - rect.top) / Math.max(1, rect.height);

            if (effect === 'magnetic') {
                const strength = Number.parseFloat(
                    el.dataset.magnetic || '0.3',
                );
                const pull = Number.isFinite(strength) ? strength : 0.3;
                el.style.setProperty(
                    '--mag-x',
                    `${((x - 0.5) * rect.width * pull).toFixed(1)}px`,
                );
                el.style.setProperty(
                    '--mag-y',
                    `${((y - 0.5) * rect.height * pull).toFixed(1)}px`,
                );
            }

            if (effect === 'tilt') {
                const max = Number.parseFloat(el.dataset.tilt || '6') || 6;
                el.style.setProperty(
                    '--tilt-rx',
                    `${((0.5 - y) * max).toFixed(2)}deg`,
                );
                el.style.setProperty(
                    '--tilt-ry',
                    `${((x - 0.5) * max).toFixed(2)}deg`,
                );
                el.style.setProperty('--glare-x', `${(x * 100).toFixed(1)}%`);
                el.style.setProperty('--glare-y', `${(y * 100).toFixed(1)}%`);
                el.style.setProperty('--glare-o', '1');
            }

            if (effect === 'spotlight') {
                el.style.setProperty(
                    '--spot-x',
                    `${(event.clientX - rect.left).toFixed(0)}px`,
                );
                el.style.setProperty(
                    '--spot-y',
                    `${(event.clientY - rect.top).toFixed(0)}px`,
                );
                el.style.setProperty('--spot-o', '1');
            }

            if (effect === 'zoom') {
                el.style.setProperty('--zoom-x', `${(x * 100).toFixed(1)}%`);
                el.style.setProperty('--zoom-y', `${(y * 100).toFixed(1)}%`);
                el.style.setProperty('--zoom-on', '1');
            }
        });
    }

    function onMove(event: PointerEvent): void {
        if (event.pointerType !== 'mouse' && event.pointerType !== 'pen') {
            return;
        }

        last = event;

        if (!frame) {
            frame = requestAnimationFrame(apply);
        }
    }

    function onLeaveWindow(): void {
        last = null;
        (Object.keys(SELECTORS) as Effect[]).forEach(release);
    }

    document.addEventListener('pointermove', onMove, { passive: true });
    document.documentElement.addEventListener('pointerleave', onLeaveWindow);
    window.addEventListener('blur', onLeaveWindow);

    return {
        destroy() {
            document.removeEventListener('pointermove', onMove);
            document.documentElement.removeEventListener(
                'pointerleave',
                onLeaveWindow,
            );
            window.removeEventListener('blur', onLeaveWindow);
            onLeaveWindow();

            if (frame) {
                cancelAnimationFrame(frame);
            }
        },
    };
}
