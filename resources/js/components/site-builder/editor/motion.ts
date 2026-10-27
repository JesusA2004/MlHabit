/**
 * Vista previa local de un movimiento dentro del editor (Web Animations).
 * El sitio público NO usa esto: allí el motor global anima cualquier
 * elemento con `data-motion` al entrar en pantalla.
 */
const KEYFRAMES: Record<string, Keyframe[]> = {
    settle: [{ transform: 'scale(1.06)' }, { transform: 'none' }],
    'fade-up': [
        { opacity: 0, transform: 'translateY(28px)' },
        { opacity: 1, transform: 'none' },
    ],
    'fade-in': [{ opacity: 0 }, { opacity: 1 }],
    'zoom-in': [
        { opacity: 0, transform: 'scale(0.92)' },
        { opacity: 1, transform: 'none' },
    ],
    'slide-left': [
        { opacity: 0, transform: 'translateX(48px)' },
        { opacity: 1, transform: 'none' },
    ],
    'slide-right': [
        { opacity: 0, transform: 'translateX(-48px)' },
        { opacity: 1, transform: 'none' },
    ],
    'blur-in': [
        { opacity: 0, filter: 'blur(14px)' },
        { opacity: 1, filter: 'blur(0)' },
    ],
    'mask-reveal': [
        { clipPath: 'inset(0 0 100% 0)', transform: 'translateY(12px)' },
        { clipPath: 'inset(0 0 0% 0)', transform: 'none' },
    ],
};

export function previewMotion(
    el: Element | null | undefined,
    preset: string,
    delay = 0,
): void {
    if (!(el instanceof HTMLElement) || !KEYFRAMES[preset]) return;
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

    const target = (el.firstElementChild as HTMLElement | null) ?? el;
    target.getAnimations().forEach((a) => a.cancel());
    target.animate(KEYFRAMES[preset], {
        duration: preset === 'mask-reveal' ? 900 : 700,
        delay: Math.min(delay, 2000),
        easing: 'cubic-bezier(0.22, 1, 0.36, 1)',
        fill: 'backwards',
    });
}
