import type { SectionContent } from '@/types/web';

/** Lectores tipados del contenido JSON de una sección del CMS. */
export function text(content: SectionContent, key: string): string {
    const value = content[key];

    return typeof value === 'string' ? value.trim() : '';
}

export function flag(content: SectionContent, key: string): boolean {
    return content[key] === true;
}

export function items<T extends Record<string, unknown>>(
    content: SectionContent,
    key: string,
): T[] {
    const value = content[key];

    return Array.isArray(value)
        ? (value.filter((v) => v && typeof v === 'object') as T[])
        : [];
}
