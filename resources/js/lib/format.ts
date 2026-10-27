/**
 * Formato de presentación para es-MX. Solo para mostrar: los montos llegan
 * del backend como cadenas decimales ("113500.00") y nunca se usan aquí para
 * cálculos.
 */
export const LOCALE = 'es-MX';
export const TIME_ZONE = 'America/Mexico_City';

const moneyFormatters = new Map<string, Intl.NumberFormat>();

function moneyFormatter(
    currency: string,
    fractionDigits: number,
): Intl.NumberFormat {
    const key = `${currency}:${fractionDigits}`;
    let formatter = moneyFormatters.get(key);

    if (!formatter) {
        formatter = new Intl.NumberFormat(LOCALE, {
            style: 'currency',
            currency,
            currencyDisplay: 'symbol',
            minimumFractionDigits: fractionDigits,
            maximumFractionDigits: fractionDigits,
        });
        moneyFormatters.set(key, formatter);
    }

    return formatter;
}

/** "113500.00" → "$113,500.00". Devuelve null si el valor no es numérico. */
export function formatMoney(
    value: string | number | null | undefined,
    options: { currency?: string; decimals?: boolean } = {},
): string | null {
    if (value === null || value === undefined || value === '') {
        return null;
    }

    const amount = typeof value === 'number' ? value : Number(value);

    if (!Number.isFinite(amount)) {
        return null;
    }

    return moneyFormatter(
        options.currency ?? 'MXN',
        options.decimals === false ? 0 : 2,
    ).format(amount);
}

export function formatNumber(
    value: number,
    options: Intl.NumberFormatOptions = {},
): string {
    return new Intl.NumberFormat(LOCALE, options).format(value);
}

export function formatPercent(value: number, fractionDigits = 0): string {
    return new Intl.NumberFormat(LOCALE, {
        style: 'percent',
        maximumFractionDigits: fractionDigits,
    }).format(value);
}

export function formatBytes(bytes: number): string {
    if (!Number.isFinite(bytes) || bytes <= 0) {
        return '0 B';
    }

    const units = ['B', 'KB', 'MB', 'GB', 'TB'];
    const exponent = Math.min(
        Math.floor(Math.log(bytes) / Math.log(1024)),
        units.length - 1,
    );
    const size = bytes / 1024 ** exponent;

    return `${formatNumber(size, { maximumFractionDigits: exponent === 0 ? 0 : 1 })} ${units[exponent]}`;
}

/** Convierte "YYYY-MM-DD" o ISO completo en Date (las fechas puras a mediodía local). */
export function toDate(
    value: string | number | Date | null | undefined,
): Date | null {
    if (value === null || value === undefined || value === '') {
        return null;
    }

    if (value instanceof Date) {
        return Number.isNaN(value.getTime()) ? null : value;
    }

    if (typeof value === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(value)) {
        const [y, m, d] = value.split('-').map(Number);

        return new Date(y, m - 1, d, 12);
    }

    const date = new Date(value);

    return Number.isNaN(date.getTime()) ? null : date;
}

export type DateStyle =
    | 'short'
    | 'medium'
    | 'long'
    | 'full'
    | 'datetime'
    | 'time'
    | 'month';

const DATE_OPTIONS: Record<DateStyle, Intl.DateTimeFormatOptions> = {
    short: { day: '2-digit', month: '2-digit', year: 'numeric' },
    medium: { day: 'numeric', month: 'short', year: 'numeric' },
    long: { day: 'numeric', month: 'long', year: 'numeric' },
    full: { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' },
    datetime: {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    },
    time: { hour: 'numeric', minute: '2-digit' },
    month: { month: 'long', year: 'numeric' },
};

export function formatDate(
    value: string | number | Date | null | undefined,
    style: DateStyle = 'medium',
): string | null {
    const date = toDate(value);

    return date
        ? new Intl.DateTimeFormat(LOCALE, DATE_OPTIONS[style]).format(date)
        : null;
}

const RELATIVE_UNITS: [Intl.RelativeTimeFormatUnit, number][] = [
    ['year', 60 * 60 * 24 * 365],
    ['month', 60 * 60 * 24 * 30],
    ['week', 60 * 60 * 24 * 7],
    ['day', 60 * 60 * 24],
    ['hour', 60 * 60],
    ['minute', 60],
];

/** "hace 3 horas", "en 2 días", "ahora". */
export function formatRelative(
    value: string | number | Date | null | undefined,
    now: Date = new Date(),
): string | null {
    const date = toDate(value);

    if (!date) {
        return null;
    }

    const seconds = Math.round((date.getTime() - now.getTime()) / 1000);
    const formatter = new Intl.RelativeTimeFormat(LOCALE, { numeric: 'auto' });

    for (const [unit, size] of RELATIVE_UNITS) {
        if (Math.abs(seconds) >= size) {
            return formatter.format(Math.round(seconds / size), unit);
        }
    }

    return 'ahora';
}
