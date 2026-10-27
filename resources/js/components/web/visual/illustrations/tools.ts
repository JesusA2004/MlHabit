import type { IllustrationDef } from './types';

/** Flexómetro: la medición en casa. */
export const tape: IllustrationDef = {
    title: 'Flexómetro',
    fills: [
        {
            d: 'M22 46h44a8 8 0 0 1 8 8v36a8 8 0 0 1-8 8H22a8 8 0 0 1-8-8V54a8 8 0 0 1 8-8z',
            tone: 'eucalyptus',
            opacity: 0.25,
        },
        { d: 'M74 86h70v8H74z', tone: 'oak', opacity: 0.45 },
    ],
    lines: [
        { d: 'M8 104h144', tone: 'muted' },
        {
            d: 'M22 46h44a8 8 0 0 1 8 8v36a8 8 0 0 1-8 8H22a8 8 0 0 1-8-8V54a8 8 0 0 1 8-8z',
        },
        { d: 'M30 72a14 14 0 1 0 28 0a14 14 0 1 0-28 0' },
        { d: 'M42 72a2 2 0 1 0 4 0a2 2 0 1 0-4 0M66 58h6v8h-6' },
        { d: 'M74 86h70v8H74zM144 83v14' },
        {
            d: 'M80 86v4M86 86v3M92 86v4M98 86v3M104 86v5M110 86v3M116 86v4M122 86v3M128 86v5M134 86v3M140 86v4',
            tone: 'oak',
        },
        { d: 'M74 74h70M74 71v6M144 71v6', tone: 'oak' },
    ],
};

/** Plano de planta con cotas y lápiz. */
export const blueprint: IllustrationDef = {
    title: 'Plano con medidas',
    fills: [
        { d: 'M26 14h96l16 16v76H26z', tone: 'linen', opacity: 1 },
        { d: 'M38 34h60v56H38z', tone: 'eucalyptus', opacity: 0.08 },
        { d: 'M40 36h28v6H40z', tone: 'oak', opacity: 0.45 },
    ],
    lines: [
        { d: 'M26 14h96l16 16v76H26zM122 14v16h16' },
        { d: 'M38 34h60v56H88M76 90H38V34' },
        { d: 'M70 34v24M38 62h22' },
        { d: 'M76 90V78M76 78a12 12 0 0 1 12 12', tone: 'muted' },
        { d: 'M40 36h28v6H40z', tone: 'oak' },
        {
            d: 'M38 98h60M38 95v6M98 95v6M108 34v56M105 34h6M105 90h6',
            tone: 'oak',
        },
        { d: 'M112 114l30-30 5 5-30 30-7 2zM137 89l5 5' },
    ],
};

/** Caja de herramientas: instalación profesional. */
export const toolbox: IllustrationDef = {
    title: 'Caja de herramientas',
    fills: [
        { d: 'M24 58h112v42H24z', tone: 'eucalyptus', opacity: 0.28 },
        { d: 'M110 30h16v-7h-16z', tone: 'espresso', opacity: 0.6 },
        { d: 'M37 36h6l-1-14h-4z', tone: 'oak', opacity: 0.6 },
    ],
    lines: [
        { d: 'M8 104h144', tone: 'muted' },
        { d: 'M24 58h112v42H24zM24 70h112' },
        { d: 'M76 66h8v8h-8z' },
        { d: 'M58 58V46a4 4 0 0 1 4-4h36a4 4 0 0 1 4 4v12' },
        { d: 'M40 58V36M37 36h6l-1-14h-4z', tone: 'oak' },
        { d: 'M118 58V30M110 30h16v-7h-16z' },
        { d: 'M86 58l8-22 6 2-8 22', tone: 'oak' },
        { d: 'M30 100v4M130 100v4' },
    ],
};
