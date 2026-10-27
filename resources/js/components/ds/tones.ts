import type { Tone } from '@/types/ui';

export type { Tone };

/** Clases por tono semántico. Fuente única para chips, timeline y alertas. */
export const toneClasses: Record<
    Tone,
    { soft: string; solid: string; dot: string; ring: string }
> = {
    success: {
        soft: 'bg-success-soft text-success-ink',
        solid: 'bg-success text-success-foreground',
        dot: 'bg-success',
        ring: 'ring-success/25',
    },
    warning: {
        soft: 'bg-warning-soft text-warning-ink',
        solid: 'bg-warning text-warning-foreground',
        dot: 'bg-warning',
        ring: 'ring-warning/25',
    },
    danger: {
        soft: 'bg-danger-soft text-danger-ink',
        solid: 'bg-danger text-danger-foreground',
        dot: 'bg-danger',
        ring: 'ring-danger/25',
    },
    info: {
        soft: 'bg-info-soft text-info-ink',
        solid: 'bg-info text-info-foreground',
        dot: 'bg-info',
        ring: 'ring-info/25',
    },
    neutral: {
        soft: 'bg-neutral-soft text-neutral-ink',
        solid: 'bg-neutral text-neutral-foreground',
        dot: 'bg-neutral',
        ring: 'ring-neutral/25',
    },
    brand: {
        soft: 'bg-primary-soft text-primary',
        solid: 'bg-primary text-primary-foreground',
        dot: 'bg-primary',
        ring: 'ring-primary/25',
    },
};
