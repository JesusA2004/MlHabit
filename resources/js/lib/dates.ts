import {
    CalendarDate,
    getLocalTimeZone,
    parseDate,
    today,
} from '@internationalized/date';
import type { DateValue } from '@internationalized/date';

/**
 * Configuración de calendario compartida: español de México, semana que
 * inicia en lunes. Todos los calendarios del sistema usan estas constantes.
 */
export const CALENDAR_LOCALE = 'es-MX';
export const WEEK_STARTS_ON = 1 as const;

/** "YYYY-MM-DD" → CalendarDate (o undefined si no es válida). */
export function isoToCalendarDate(
    value: string | null | undefined,
): CalendarDate | undefined {
    if (!value) {
        return undefined;
    }

    try {
        return parseDate(value.slice(0, 10));
    } catch {
        return undefined;
    }
}

/** CalendarDate/DateValue → "YYYY-MM-DD". */
export function calendarDateToIso(
    value: DateValue | null | undefined,
): string | null {
    if (!value) {
        return null;
    }

    return new CalendarDate(value.year, value.month, value.day).toString();
}

export function todayDate(): CalendarDate {
    return today(getLocalTimeZone());
}

/** Fecha legible desde ISO: "lunes, 3 de noviembre de 2026". */
export function formatIsoDate(
    value: string | null | undefined,
    options: Intl.DateTimeFormatOptions = {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    },
): string | null {
    const date = isoToCalendarDate(value);

    return date
        ? new Intl.DateTimeFormat(CALENDAR_LOCALE, options).format(
              date.toDate(getLocalTimeZone()),
          )
        : null;
}

/** Construye un validador `isDateUnavailable` a partir de fechas ISO bloqueadas. */
export function unavailableFrom(
    disabledDates: string[] = [],
    predicate?: (iso: string) => boolean,
): (date: DateValue) => boolean {
    const blocked = new Set(disabledDates.map((d) => d.slice(0, 10)));

    return (date: DateValue) => {
        const iso = calendarDateToIso(date) ?? '';

        return blocked.has(iso) || (predicate ? predicate(iso) : false);
    };
}
