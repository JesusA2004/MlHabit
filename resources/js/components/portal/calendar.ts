/**
 * "Agregar a mi calendario": genera un .ics en el navegador (sin pedir nada
 * al servidor) y enlaces directos a Google Calendar.
 */
export type CalendarEvent = {
    uid: string;
    title: string;
    start: string;
    end: string | null;
    description?: string | null;
    location?: string | null;
    url?: string | null;
};

function stamp(value: string | Date): string {
    const d = typeof value === 'string' ? new Date(value) : value;

    return d
        .toISOString()
        .replace(/[-:]/g, '')
        .replace(/\.\d{3}/, '');
}

function endOf(event: CalendarEvent): string {
    if (event.end) {
        return event.end;
    }

    return new Date(
        new Date(event.start).getTime() + 60 * 60 * 1000,
    ).toISOString();
}

function escapeText(text: string): string {
    return text
        .replace(/\\/g, '\\\\')
        .replace(/\n/g, '\\n')
        .replace(/,/g, '\\,')
        .replace(/;/g, '\\;');
}

export function icsContent(event: CalendarEvent): string {
    const lines = [
        'BEGIN:VCALENDAR',
        'VERSION:2.0',
        'PRODID:-//ML Habitat//Portal//ES',
        'CALSCALE:GREGORIAN',
        'BEGIN:VEVENT',
        `UID:${event.uid}@mlhabitat`,
        `DTSTAMP:${stamp(new Date())}`,
        `DTSTART:${stamp(event.start)}`,
        `DTEND:${stamp(endOf(event))}`,
        `SUMMARY:${escapeText(event.title)}`,
    ];

    const details = [event.description, event.url].filter(Boolean).join('\n');

    if (details) {
        lines.push(`DESCRIPTION:${escapeText(details)}`);
    }

    if (event.location) {
        lines.push(`LOCATION:${escapeText(event.location)}`);
    }

    if (event.url) {
        lines.push(`URL:${event.url}`);
    }

    lines.push(
        'BEGIN:VALARM',
        'TRIGGER:-PT1H',
        'ACTION:DISPLAY',
        'DESCRIPTION:Recordatorio',
        'END:VALARM',
        'END:VEVENT',
        'END:VCALENDAR',
    );

    return lines.join('\r\n');
}

export function downloadIcs(event: CalendarEvent): void {
    const blob = new Blob([icsContent(event)], {
        type: 'text/calendar;charset=utf-8',
    });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `${event.title.toLowerCase().replace(/[^a-z0-9]+/gi, '-')}.ics`;
    document.body.appendChild(a);
    a.click();
    a.remove();
    window.setTimeout(() => URL.revokeObjectURL(url), 1000);
}

export function googleCalendarUrl(event: CalendarEvent): string {
    const params = new URLSearchParams({
        action: 'TEMPLATE',
        text: event.title,
        dates: `${stamp(event.start)}/${stamp(endOf(event))}`,
    });

    const details = [event.description, event.url].filter(Boolean).join('\n');

    if (details) {
        params.set('details', details);
    }

    if (event.location) {
        params.set('location', event.location);
    }

    return `https://calendar.google.com/calendar/render?${params.toString()}`;
}

/** "en 3 días", "mañana", "en 2 h"… (null si ya pasó). */
export function countdown(iso: string | null, now = Date.now()): string | null {
    if (!iso) {
        return null;
    }

    const diff = new Date(iso).getTime() - now;

    if (diff <= 0) {
        return null;
    }

    const minutes = Math.round(diff / 60000);

    if (minutes < 60) {
        return `en ${minutes} min`;
    }

    const hours = Math.round(minutes / 60);

    if (hours < 24) {
        return `en ${hours} h`;
    }

    const days = Math.round(hours / 24);

    return days === 1 ? 'mañana' : `en ${days} días`;
}
