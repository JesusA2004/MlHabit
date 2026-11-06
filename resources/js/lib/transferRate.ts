/**
 * Medidor de velocidad de transferencia (bytes/s) con media móvil
 * exponencial, para mostrar velocidad y tiempo restante de una subida sin
 * que los números brinquen con cada evento de progreso.
 */
export function createRateMeter(smoothing = 0.25) {
    let lastBytes: number | null = null;
    let lastAt = 0;
    let rate = 0;

    return {
        /** Registra los bytes enviados hasta ahora; devuelve la velocidad estimada. */
        sample(bytes: number, now = performance.now()): number {
            if (lastBytes === null || bytes < lastBytes) {
                lastBytes = bytes;
                lastAt = now;

                return rate;
            }

            const elapsed = (now - lastAt) / 1000;

            // Muestras demasiado juntas inflan la velocidad: se acumulan.
            if (elapsed < 0.4) {
                return rate;
            }

            const instant = (bytes - lastBytes) / elapsed;
            rate = rate === 0 ? instant : rate + smoothing * (instant - rate);
            lastBytes = bytes;
            lastAt = now;

            return rate;
        },
        reset(): void {
            lastBytes = null;
            rate = 0;
        },
    };
}

/** Segundos restantes estimados, o null si aún no hay una velocidad fiable. */
export function etaSeconds(
    sent: number,
    total: number,
    bytesPerSecond: number,
): number | null {
    if (bytesPerSecond <= 0 || total <= 0) {
        return null;
    }

    return Math.max(0, Math.round((total - sent) / bytesPerSecond));
}

/** 2.4 MB/s */
export function formatRate(bytesPerSecond: number): string {
    if (bytesPerSecond <= 0) {
        return '';
    }

    const units = ['B/s', 'KB/s', 'MB/s', 'GB/s'];
    let value = bytesPerSecond;
    let unit = 0;

    while (value >= 1024 && unit < units.length - 1) {
        value /= 1024;
        unit += 1;
    }

    return `${value.toFixed(value >= 10 || unit === 0 ? 0 : 1)} ${units[unit]}`;
}

/** "quedan 2 min 10 s" / "quedan 8 s" / "menos de 1 s". */
export function formatEta(seconds: number | null): string {
    if (seconds === null || !Number.isFinite(seconds)) {
        return '';
    }

    if (seconds < 1) {
        return 'casi listo';
    }

    if (seconds < 60) {
        return `quedan ${seconds} s`;
    }

    const minutes = Math.floor(seconds / 60);
    const rest = seconds % 60;

    if (minutes < 60) {
        return rest > 0 && minutes < 10
            ? `quedan ${minutes} min ${rest} s`
            : `quedan ${minutes} min`;
    }

    const hours = Math.floor(minutes / 60);

    return `quedan ${hours} h ${minutes % 60} min`;
}
