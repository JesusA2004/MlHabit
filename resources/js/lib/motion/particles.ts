/**
 * Campo de partículas en <canvas>: "polvo en un rayo de sol" y "virutas de
 * madera" que flotan muy despacio. Sin dependencias.
 *
 * - Un solo `requestAnimationFrame` por lienzo; se detiene cuando el lienzo
 *   sale de pantalla (IntersectionObserver) o la pestaña se oculta.
 * - Consciente del DPR (tope 2) y del tamaño real (ResizeObserver).
 * - Pocas partículas en móvil; densidad proporcional al área.
 * - Con `prefers-reduced-motion: reduce` se pinta un único cuadro quieto
 *   (o nada, con `staticFrame: false`). El cambio de preferencia se aplica
 *   en vivo.
 * - Con puntero fino, las partículas se apartan suavemente del cursor.
 *
 * Es decorativo: el lienzo va `aria-hidden` y sin eventos de puntero.
 */
import {
    hasFinePointer,
    onReducedMotionChange,
    prefersReducedMotion,
} from './env';

export type ParticleVariant = 'dust' | 'shavings' | 'mixed';
export type ParticleTone = 'light' | 'dark';

export type ParticleOptions = {
    variant?: ParticleVariant;
    /** `dark` sobre espresso/eucalipto/fotografía; `light` sobre lino. */
    tone?: ParticleTone;
    /** Multiplicador de densidad (0.2–2). */
    density?: number;
    /** Repulsión del cursor. */
    interactive?: boolean;
    /** Con movimiento reducido: pintar un cuadro quieto (por defecto) o nada. */
    staticFrame?: boolean;
};

export type ParticleField = {
    update: (options: ParticleOptions) => void;
    destroy: () => void;
};

type Particle = {
    kind: 'dust' | 'shaving';
    x: number;
    y: number;
    /** Velocidad base (px/s). */
    vx: number;
    vy: number;
    /** Empuje del cursor (decae). */
    px: number;
    py: number;
    size: number;
    alpha: number;
    phase: number;
    twinkle: number;
    angle: number;
    spin: number;
    sprite: number;
    curl: number;
};

/** Paletas (roble, lino cálido, eucalipto claro, espresso). */
const PALETTE: Record<ParticleTone, { dust: string[]; shaving: string[] }> = {
    dark: {
        dust: [
            '255, 236, 205',
            '232, 196, 150',
            '246, 239, 230',
            '214, 178, 132',
        ],
        shaving: ['214, 168, 118', '196, 150, 104', '232, 200, 160'],
    },
    light: {
        dust: ['176, 125, 79', '201, 160, 116', '138, 106, 74', '47, 90, 76'],
        shaving: ['176, 125, 79', '160, 112, 70', '201, 160, 116'],
    },
};

const REPEL_RADIUS = 130;
const REPEL_FORCE = 220;
const MAX_DPR = 2;

function rand(min: number, max: number): number {
    return min + Math.random() * (max - min);
}

/** Sprites de brillo (gradiente radial) pre-renderizados: dibujar es barato. */
function makeSprites(colors: string[], dark: boolean): HTMLCanvasElement[] {
    return colors.map((rgb) => {
        const size = 64;
        const sprite = document.createElement('canvas');
        sprite.width = sprite.height = size;
        const ctx = sprite.getContext('2d');

        if (ctx) {
            const g = ctx.createRadialGradient(32, 32, 0, 32, 32, 32);
            g.addColorStop(0, `rgba(${rgb}, 1)`);
            g.addColorStop(dark ? 0.18 : 0.28, `rgba(${rgb}, 0.85)`);
            g.addColorStop(dark ? 0.45 : 0.55, `rgba(${rgb}, 0.18)`);
            g.addColorStop(1, `rgba(${rgb}, 0)`);
            ctx.fillStyle = g;
            ctx.fillRect(0, 0, size, size);
        }

        return sprite;
    });
}

export function createParticleField(
    canvas: HTMLCanvasElement,
    initial: ParticleOptions = {},
): ParticleField {
    const noop: ParticleField = { update: () => {}, destroy: () => {} };
    const ctx = canvas.getContext('2d', { alpha: true });

    if (!ctx || typeof window === 'undefined') {
        return noop;
    }

    let options: Required<ParticleOptions> = {
        variant: 'dust',
        tone: 'dark',
        density: 1,
        interactive: true,
        staticFrame: true,
        ...initial,
    };

    let width = 0;
    let height = 0;
    let dpr = 1;
    let particles: Particle[] = [];
    let sprites: HTMLCanvasElement[] = [];
    let frame = 0;
    let last = 0;
    let born = 0;
    let onScreen = false;
    let reduced = prefersReducedMotion();
    let pointer: { x: number; y: number } | null = null;
    let rect: DOMRect | null = null;

    function targetCount(): number {
        const mobile = width < 640;
        const per = mobile ? 24000 : 13000;
        const cap = mobile ? 26 : 90;
        const density = Math.min(2, Math.max(0.2, options.density));

        return Math.round(
            Math.min(cap, Math.max(10, ((width * height) / per) * density)),
        );
    }

    function spawn(anywhere: boolean): Particle {
        const shaving =
            options.variant === 'shavings' ||
            (options.variant === 'mixed' && Math.random() < 0.28);
        const palette = PALETTE[options.tone];

        if (shaving) {
            return {
                kind: 'shaving',
                x: rand(0, width),
                y: anywhere ? rand(0, height) : rand(-40, -10),
                vx: rand(-6, 6),
                vy: rand(7, 16),
                px: 0,
                py: 0,
                size: rand(5, 10),
                alpha: rand(0.28, 0.55),
                phase: rand(0, Math.PI * 2),
                twinkle: rand(0.3, 0.7),
                angle: rand(0, Math.PI * 2),
                spin: rand(-0.6, 0.6),
                sprite: Math.floor(rand(0, palette.shaving.length)),
                curl: rand(0.9, 1.9),
            };
        }

        return {
            kind: 'dust',
            x: rand(0, width),
            y: anywhere ? rand(0, height) : height + rand(4, 30),
            vx: rand(-4, 5),
            vy: rand(-12, -3),
            px: 0,
            py: 0,
            size:
                rand(0.8, options.tone === 'dark' ? 2.6 : 2) *
                (options.tone === 'dark' && Math.random() < 0.12 ? 2.2 : 1),
            alpha: rand(0.25, options.tone === 'dark' ? 0.85 : 0.5),
            phase: rand(0, Math.PI * 2),
            twinkle: rand(0.4, 1.4),
            angle: 0,
            spin: 0,
            sprite: Math.floor(rand(0, palette.dust.length)),
            curl: 0,
        };
    }

    function populate(): void {
        const count = targetCount();
        particles = particles.slice(0, count);

        while (particles.length < count) {
            particles.push(spawn(true));
        }
    }

    function resize(): void {
        const box = canvas.getBoundingClientRect();
        const w = Math.max(1, Math.round(box.width));
        const h = Math.max(1, Math.round(box.height));
        const nextDpr = Math.min(MAX_DPR, window.devicePixelRatio || 1);

        if (w === width && h === height && nextDpr === dpr) {
            return;
        }

        // Reubica proporcionalmente (sin "saltos" al girar el teléfono).
        const sx = width ? w / width : 1;
        const sy = height ? h / height : 1;
        particles.forEach((p) => {
            p.x *= sx;
            p.y *= sy;
        });

        width = w;
        height = h;
        dpr = nextDpr;
        canvas.width = Math.round(w * dpr);
        canvas.height = Math.round(h * dpr);
        ctx!.setTransform(dpr, 0, 0, dpr, 0, 0);
        populate();
        rect = null;

        if (reduced || !frame) {
            draw(0, performance.now());
        }
    }

    function draw(dt: number, now: number): void {
        ctx!.clearRect(0, 0, width, height);

        if (reduced && !options.staticFrame) {
            return;
        }

        const palette = PALETTE[options.tone];
        const fadeIn = reduced ? 1 : Math.min(1, (now - born) / 1400);
        const t = now / 1000;

        for (const p of particles) {
            if (dt > 0) {
                if (pointer && options.interactive) {
                    const dx = p.x - pointer.x;
                    const dy = p.y - pointer.y;
                    const dist2 = dx * dx + dy * dy;

                    if (dist2 < REPEL_RADIUS * REPEL_RADIUS && dist2 > 0.01) {
                        const dist = Math.sqrt(dist2);
                        const push = (1 - dist / REPEL_RADIUS) * REPEL_FORCE;
                        p.px += (dx / dist) * push * dt;
                        p.py += (dy / dist) * push * dt;
                    }
                }

                // El empuje decae: vuelven a su deriva natural.
                const decay = Math.exp(-dt * 2.2);
                p.px *= decay;
                p.py *= decay;

                const sway =
                    p.kind === 'shaving'
                        ? Math.sin(t * 0.9 + p.phase) * 10
                        : Math.sin(t * 0.35 + p.phase) * 3;
                p.x += (p.vx + sway + p.px) * dt;
                p.y += (p.vy + p.py) * dt;
                p.angle += p.spin * dt;

                const margin = 40;

                if (
                    p.y < -margin ||
                    p.y > height + margin ||
                    p.x < -margin ||
                    p.x > width + margin
                ) {
                    Object.assign(p, spawn(false));
                }
            }

            const flicker = reduced
                ? 1
                : 0.65 + 0.35 * Math.sin(t * p.twinkle + p.phase);
            const alpha = p.alpha * flicker * fadeIn;

            if (p.kind === 'dust') {
                const sprite = sprites[p.sprite % sprites.length];
                const s = p.size * 6;
                ctx!.globalAlpha = alpha;
                ctx!.drawImage(sprite, p.x - s / 2, p.y - s / 2, s, s);
            } else {
                // Viruta: un rizo (arco) delgado que gira despacio.
                const rgb = palette.shaving[p.sprite % palette.shaving.length];
                ctx!.globalAlpha = alpha;
                ctx!.strokeStyle = `rgb(${rgb})`;
                ctx!.lineWidth = 1.3;
                ctx!.lineCap = 'round';
                ctx!.beginPath();
                ctx!.ellipse(
                    p.x,
                    p.y,
                    p.size,
                    p.size * 0.45,
                    p.angle,
                    0,
                    Math.PI * p.curl,
                );
                ctx!.stroke();
            }
        }

        ctx!.globalAlpha = 1;
    }

    function loop(now: number): void {
        frame = 0;

        if (!running()) {
            return;
        }

        const dt = last ? Math.min(0.05, (now - last) / 1000) : 0;
        last = now;

        if (pointer && !rect) {
            rect = canvas.getBoundingClientRect();
        }

        draw(dt, now);
        frame = requestAnimationFrame(loop);
    }

    function running(): boolean {
        return onScreen && !reduced && document.visibilityState === 'visible';
    }

    function start(): void {
        if (!frame && running()) {
            last = 0;
            frame = requestAnimationFrame(loop);
        }
    }

    function stop(): void {
        if (frame) {
            cancelAnimationFrame(frame);
            frame = 0;
        }
    }

    function refreshSprites(): void {
        sprites = makeSprites(
            PALETTE[options.tone].dust,
            options.tone === 'dark',
        );
    }

    // --- Puntero --------------------------------------------------------
    function onPointerMove(event: PointerEvent): void {
        if (event.pointerType === 'touch') {
            return;
        }

        if (!rect) {
            rect = canvas.getBoundingClientRect();
        }

        const x = event.clientX - rect.left;
        const y = event.clientY - rect.top;
        pointer =
            x >= -REPEL_RADIUS &&
            y >= -REPEL_RADIUS &&
            x <= rect.width + REPEL_RADIUS &&
            y <= rect.height + REPEL_RADIUS
                ? { x, y }
                : null;
    }

    function onPointerLeave(): void {
        pointer = null;
    }

    function onScroll(): void {
        rect = null;
    }

    const fine = hasFinePointer();

    if (fine) {
        window.addEventListener('pointermove', onPointerMove, {
            passive: true,
        });
        window.addEventListener('scroll', onScroll, { passive: true });
        document.documentElement.addEventListener(
            'pointerleave',
            onPointerLeave,
        );
    }

    // --- Visibilidad ----------------------------------------------------
    const io =
        'IntersectionObserver' in window
            ? new IntersectionObserver(
                  (entries) => {
                      onScreen = entries.some((e) => e.isIntersecting);

                      if (onScreen) {
                          start();
                      } else {
                          stop();
                      }
                  },
                  { rootMargin: '80px 0px' },
              )
            : null;

    if (io) {
        io.observe(canvas);
    } else {
        onScreen = true;
    }

    const ro =
        'ResizeObserver' in window ? new ResizeObserver(() => resize()) : null;
    ro?.observe(canvas);

    function onVisibility(): void {
        if (document.visibilityState === 'visible') {
            start();
        } else {
            stop();
        }
    }

    document.addEventListener('visibilitychange', onVisibility);

    const offReduced = onReducedMotionChange((value) => {
        reduced = value;
        stop();
        draw(0, performance.now());
        start();
    });

    refreshSprites();
    born = performance.now();
    resize();
    start();

    return {
        update(next) {
            const toneChanged = next.tone && next.tone !== options.tone;
            const variantChanged =
                next.variant && next.variant !== options.variant;
            options = { ...options, ...next };

            if (toneChanged) {
                refreshSprites();
            }

            if (toneChanged || variantChanged) {
                particles = [];
            }

            populate();

            if (!frame) {
                draw(0, performance.now());
            }
        },
        destroy() {
            stop();
            io?.disconnect();
            ro?.disconnect();
            offReduced();
            document.removeEventListener('visibilitychange', onVisibility);

            if (fine) {
                window.removeEventListener('pointermove', onPointerMove);
                window.removeEventListener('scroll', onScroll);
                document.documentElement.removeEventListener(
                    'pointerleave',
                    onPointerLeave,
                );
            }
        },
    };
}
