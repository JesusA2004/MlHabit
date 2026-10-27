import type { Directive } from 'vue';
import type { MotionPreset } from './reveal';

/**
 * `v-reveal` — azúcar sobre los atributos `data-motion*` para las páginas
 * propias. Escribe los atributos en `created`, antes del primer pintado,
 * así el runtime los descubre al insertarse el nodo.
 *
 *   <section v-reveal>                         → fade-up
 *   <h2 v-reveal="'mask-reveal'">              → preset
 *   <ul v-reveal="{ stagger: 70 }">            → hijos escalonados (fade-up)
 *   <div v-reveal="{ preset: 'zoom-in', delay: 120 }">
 */
export type RevealValue =
    | MotionPreset
    | {
          preset?: MotionPreset;
          delay?: number;
          stagger?: number;
      }
    | undefined;

function apply(el: HTMLElement, value: RevealValue): void {
    const options =
        typeof value === 'string' ? { preset: value } : (value ?? {});
    const preset = options.preset ?? 'fade-up';

    if (options.stagger !== undefined) {
        el.dataset.motionStagger = String(options.stagger);
        el.dataset.motionChildren = preset;
    } else if (!el.dataset.motion) {
        el.dataset.motion = preset;
    }

    if (options.delay !== undefined) {
        el.dataset.motionDelay = String(options.delay);
    }
}

export const reveal: Directive<HTMLElement, RevealValue> = {
    created: (el, binding) => apply(el, binding.value),
    // SSR: emite los mismos atributos en el HTML del servidor.
    getSSRProps(binding) {
        const value = binding.value;
        const options =
            typeof value === 'string' ? { preset: value } : (value ?? {});

        return options.stagger !== undefined
            ? {
                  'data-motion-stagger': String(options.stagger),
                  'data-motion-children': options.preset ?? 'fade-up',
              }
            : { 'data-motion': options.preset ?? 'fade-up' };
    },
};
