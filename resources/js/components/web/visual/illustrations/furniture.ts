import type { IllustrationDef } from './types';

/** Isla de cocina con lámparas colgantes y bancos. */
export const kitchen: IllustrationDef = {
    title: 'Isla de cocina a la medida',
    fills: [
        { d: 'M28 58h104v7H28z', tone: 'stone', opacity: 0.9 },
        { d: 'M65 65h30v39H65z', tone: 'eucalyptus', opacity: 0.28 },
        { d: 'M34 65h31v39H34z', tone: 'oak', opacity: 0.2 },
        {
            d: 'M53 30a9 8 0 0 1 18 0zM89 30a9 8 0 0 1 18 0z',
            tone: 'oak',
            opacity: 0.55,
        },
    ],
    lines: [
        { d: 'M8 104h144', tone: 'muted' },
        { d: 'M28 48h104M28 45v6M132 45v6', tone: 'oak' },
        { d: 'M62 4v18M98 4v18', tone: 'muted' },
        { d: 'M53 30a9 8 0 0 1 18 0zM89 30a9 8 0 0 1 18 0z' },
        { d: 'M28 58h104v7H28z' },
        { d: 'M34 65v39h92V65' },
        { d: 'M65 65v39M95 65v39M34 78h31M95 78h31M34 91h31M95 91h31' },
        {
            d: 'M45 71.5h9M106 71.5h9M45 84.5h9M106 84.5h9M45 97.5h9M106 97.5h9M77 72v12M83 72v12',
            tone: 'muted',
        },
        { d: 'M12 72h14M19 72v32M14 104h10M134 72h14M141 72v32M136 104h10' },
    ],
};

/** Clóset de tres cuerpos: abierto, espejo y entrepaños. */
export const closet: IllustrationDef = {
    title: 'Clóset a la medida',
    fills: [
        { d: 'M64 10h32v88H64z', tone: 'eucalyptus', opacity: 0.14 },
        { d: 'M39 30h7v32h-7zM51 30h7v24h-7z', tone: 'oak', opacity: 0.45 },
        {
            d: 'M101 56V46h10v10zM101 78v-9h22v9z',
            tone: 'eucalyptus',
            opacity: 0.3,
        },
    ],
    lines: [
        { d: 'M8 104h144', tone: 'muted' },
        { d: 'M28 10h104M32 104V10M128 10v94M32 98h96' },
        { d: 'M64 10v88M96 10v88' },
        { d: 'M37 24h22M42 24v2l-4 4h8l-4-4M54 24v2l-4 4h8l-4-4' },
        { d: 'M39 30h7v32h-7zM51 30h7v24h-7z' },
        { d: 'M32 74h32M32 86h32M44 80h8', tone: 'ink' },
        { d: 'M91 46v14' },
        { d: 'M70 28l12-12M70 40l20-20M74 54l16-16', tone: 'muted' },
        {
            d: 'M96 34h32M96 56h32M96 78h32M101 34v-5h12v5M102 29v-4h10v4M101 56V46h10v10M114 56v-7h9v7M101 78v-9h22v9',
        },
        { d: 'M142 10v88M139 10h6M139 98h6', tone: 'oak' },
    ],
};

/** Recámara: cabecera tapizada, buros y lámparas. */
export const bedroom: IllustrationDef = {
    title: 'Recámara con cabecera a la medida',
    fills: [
        {
            d: 'M34 28h92a6 6 0 0 1 6 6v46H28V34a6 6 0 0 1 6-6z',
            tone: 'eucalyptus',
            opacity: 0.22,
        },
        { d: 'M4 76h20v28H4zM136 76h20v28h-20z', tone: 'oak', opacity: 0.25 },
        {
            d: 'M7 64h10l-2-10h-6zM143 64h10l-2-10h-6z',
            tone: 'oak',
            opacity: 0.6,
        },
        { d: 'M64 8h32v14H64z', tone: 'oak', opacity: 0.25 },
    ],
    lines: [
        { d: 'M2 104h156', tone: 'muted' },
        { d: 'M64 8h32v14H64z', tone: 'oak' },
        { d: 'M34 28h92a6 6 0 0 1 6 6v46H28V34a6 6 0 0 1 6-6z' },
        { d: 'M51 28v52M70 28v52M90 28v52M109 28v52', tone: 'muted' },
        {
            d: 'M40 80c0-7 4-10 14-10s14 3 14 10M92 80c0-7 4-10 14-10s14 3 14 10',
        },
        { d: 'M26 80h108v10H26zM26 85h108', tone: 'ink' },
        { d: 'M30 90v8M130 90v8' },
        {
            d: 'M4 76h20v28H4zM136 76h20v28h-20zM4 88h20M136 88h20M13 82h2M145 82h2',
        },
        { d: 'M12 76V64M7 64h10l-2-10h-6zM148 76V64M143 64h10l-2-10h-6z' },
    ],
};

/** Centro de entretenimiento flotado con lambrín. */
export const tv: IllustrationDef = {
    title: 'Centro de entretenimiento a la medida',
    fills: [
        { d: 'M58 22h44v26H58z', tone: 'espresso', opacity: 0.85 },
        { d: 'M18 78h124v14H18z', tone: 'oak', opacity: 0.3 },
        { d: 'M126 78l2-8h8l2 8z', tone: 'eucalyptus', opacity: 0.5 },
    ],
    lines: [
        { d: 'M8 104h144', tone: 'muted' },
        {
            d: 'M40 8v62M48 8v62M56 8v62M104 8v62M112 8v62M120 8v62',
            tone: 'muted',
        },
        { d: 'M58 22h44v26H58z' },
        { d: 'M18 78h124v14H18zM49 78v14M80 78v14M111 78v14' },
        { d: 'M30 85h8M61 85h8M92 85h8', tone: 'muted' },
        { d: 'M24 99h112', tone: 'muted' },
        { d: 'M26 78V66M30 78V64M34 78V68', tone: 'oak' },
        {
            d: 'M126 78l2-8h8l2 8M132 70c-4-8-2-14 0-18M132 70c3-6 8-9 12-9M132 70c-4-3-9-4-12-2',
            tone: 'eucalyptus',
        },
    ],
};

/** Zapatera de puertas abatibles con espejo. */
export const shoes: IllustrationDef = {
    title: 'Zapatera a la medida',
    fills: [
        { d: 'M46 40h68v58H46z', tone: 'eucalyptus', opacity: 0.2 },
        {
            d: 'M98 40c-3-6-3-10 0-14h6c3 4 3 8 0 14z',
            tone: 'oak',
            opacity: 0.55,
        },
        {
            d: 'M65 21a15 15 0 1 0 30 0a15 15 0 1 0-30 0',
            tone: 'stone',
            opacity: 0.6,
        },
    ],
    lines: [
        { d: 'M8 104h144', tone: 'muted' },
        { d: 'M65 21a15 15 0 1 0 30 0a15 15 0 1 0-30 0' },
        { d: 'M72 16l8-8M74 24l12-12', tone: 'muted' },
        { d: 'M46 40h68v58H46zM46 59h68M46 78h68' },
        { d: 'M74 47h12M74 66h12M74 85h12', tone: 'muted' },
        { d: 'M50 98v6M110 98v6' },
        { d: 'M54 40v-3h14v3' },
        {
            d: 'M98 40c-3-6-3-10 0-14h6c3 4 3 8 0 14zM101 26c0-6 3-10 6-12M101 26c-2-5-5-8-9-9',
            tone: 'eucalyptus',
        },
        { d: 'M120 104v-6c4 0 6-2 8-4l6 4c4 1 8 2 8 6z' },
        { d: 'M36 40v58M33 40h6M33 98h6', tone: 'oak' },
    ],
};
