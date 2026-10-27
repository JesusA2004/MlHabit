/** Tamaño legible en es-MX (1.2 MB). */
export function formatBytes(bytes: number): string {
    if (!bytes) return '0 B';
    const units = ['B', 'KB', 'MB', 'GB', 'TB'];
    const i = Math.min(
        units.length - 1,
        Math.floor(Math.log(bytes) / Math.log(1024)),
    );
    const value = bytes / 1024 ** i;

    return `${new Intl.NumberFormat('es-MX', { maximumFractionDigits: i === 0 ? 0 : 1 }).format(value)} ${units[i]}`;
}
