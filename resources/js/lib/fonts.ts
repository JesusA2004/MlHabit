import { schema } from '@/studio/schema';

/**
 * Carga bajo demanda (Bunny Fonts, compatible con GDPR) las familias de la
 * lista blanca de Studio/Apariencia que no van en el bundle. Fraunces e
 * Instrument Sans ya se sirven desde Vite (ver vite.config.ts).
 */
const BUNDLED = new Set(['Fraunces', 'Instrument Sans']);
const loaded = new Set<string>();

function slug(name: string): string {
    return name.toLowerCase().replace(/\s+/g, '-');
}

export function loadFonts(families: Array<string | null | undefined>, doc: Document = document): void {
    const pending = families.filter(
        (f): f is string => !!f && f in schema.fonts && !schema.fonts[f].theme && !BUNDLED.has(f) && !loaded.has(f),
    );
    if (pending.length === 0) return;

    const query = pending.map((f) => `${slug(f)}:400,400i,500,600,700,800,900`).join('|');
    const link = doc.createElement('link');
    link.rel = 'stylesheet';
    link.href = `https://fonts.bunny.net/css?family=${query}&display=swap`;
    doc.head.appendChild(link);
    pending.forEach((f) => loaded.add(f));
}
