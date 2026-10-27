import { spawnSync } from 'node:child_process';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { expect } from '@playwright/test';
import type { BrowserContext, Page } from '@playwright/test';

const HERE = path.dirname(fileURLToPath(import.meta.url));
export const ROOT = path.resolve(HERE, '../../..');
export const PASSWORD = 'E2e-Test-2026!';

export const USERS = {
    super_admin: 'admin.e2e@mlhabitat.test',
    comercial: 'comercial.e2e@mlhabitat.test',
    diseno: 'diseno.e2e@mlhabitat.test',
    operaciones: 'operaciones.e2e@mlhabitat.test',
    calidad: 'calidad.e2e@mlhabitat.test',
    finanzas: 'finanzas.e2e@mlhabitat.test',
    tecnico: 'tecnico.e2e@mlhabitat.test',
} as const;

export type Role = keyof typeof USERS;

const MARK = '@@E2E@@';

/**
 * Ejecuta PHP contra la base E2E con `php artisan tinker --env=e2e` y
 * devuelve el JSON que imprime la expresión. No hay endpoints de prueba:
 * la lectura de la base (enlaces firmados, conteos) se hace por consola.
 */
export function php<T = unknown>(expression: string): T {
    const code = `echo '${MARK}'.json_encode(${expression}).'${MARK}';`;
    const result = spawnSync(
        'php',
        ['artisan', 'tinker', '--env=e2e', '--execute', code],
        { cwd: ROOT, encoding: 'utf8', timeout: 60_000 },
    );
    const out = `${result.stdout ?? ''}`;
    const start = out.indexOf(MARK);
    const end = out.lastIndexOf(MARK);

    if (result.status !== 0 || start < 0 || end <= start) {
        throw new Error(
            `php() falló (${result.status}): ${expression}\n${out}\n${result.stderr ?? ''}`,
        );
    }

    return JSON.parse(out.slice(start + MARK.length, end)) as T;
}

/** Sirve el stub de openpay.js (ver support/openpay-stub.js) en lugar del SDK real. */
export async function stubOpenpay(
    target: Page | BrowserContext,
): Promise<void> {
    await target.route('https://js.openpay.mx/openpay.v1.min.js', (route) =>
        route.fulfill({
            path: path.join(HERE, 'openpay-stub.js'),
            contentType: 'application/javascript',
        }),
    );
    await target.route(
        'https://js.openpay.mx/openpay-data.v1.min.js',
        (route) =>
            route.fulfill({
                body: '/* stub E2E */',
                contentType: 'application/javascript',
            }),
    );
}

export async function login(
    page: Page,
    role: Role,
    password = PASSWORD,
): Promise<void> {
    await loginAs(page, USERS[role], password);
}

export async function loginAs(
    page: Page,
    email: string,
    password = PASSWORD,
): Promise<void> {
    await page.goto('/login');
    await page.getByLabel(/correo/i).fill(email);
    await page
        .getByLabel(/contraseña/i)
        .first()
        .fill(password);
    await page
        .getByRole('button', { name: /iniciar sesión|entrar|ingresar/i })
        .click();
    await expect(page).not.toHaveURL(/\/login/);
}

export async function logout(page: Page): Promise<void> {
    await page.context().clearCookies();
}

/** Recolecta errores de consola y de página (excepciones no capturadas). */
export function collectConsoleErrors(page: Page): string[] {
    const errors: string[] = [];
    page.on('console', (msg) => {
        if (msg.type() === 'error') errors.push(msg.text());
    });
    page.on('pageerror', (error) => errors.push(error.message));

    return errors;
}

/** Espera la siguiente respuesta (no GET) de Inertia tras una acción. */
export async function submitAndWait(
    page: Page,
    action: () => Promise<void>,
    url?: RegExp,
): Promise<void> {
    const response = page.waitForResponse(
        (r) => r.request().method() !== 'GET' && (!url || url.test(r.url())),
    );
    await action();
    await response;
}

/** Fecha local (America/Mexico_City) en formato AAAA-MM-DD, con desplazamiento de días. */
export function localDate(offsetDays = 0): string {
    const d = new Date(Date.now() + offsetDays * 86_400_000);

    return new Intl.DateTimeFormat('en-CA', {
        timeZone: 'America/Mexico_City',
    }).format(d);
}

export const TEST_CARDS = {
    approved: '4111111111111111',
    declined: '4000000000000002',
    declinedAlt: '4222222222222220',
};

/** Llena el formulario de OpenpayCardForm. */
export async function fillCard(
    page: Page,
    number: string,
    holder = 'CLIENTE TEST',
): Promise<void> {
    await page.getByLabel('Nombre del titular').fill(holder);
    await page.getByLabel('Número de tarjeta').fill(number);
    await page.getByLabel('Vencimiento').fill('12/30');
    await page.getByLabel('Código de seguridad').fill('123');
}

/**
 * Elige un día en un DateSlotPicker (calendario reka-ui) y el primer horario
 * libre. Avanza de mes si el día no está a la vista.
 */
export async function pickDateAndSlot(
    page: Page,
    iso: string,
    slotIndex = 0,
): Promise<string> {
    const picker = page.locator('[data-slot="date-slot-picker"]').last();
    const day = picker.locator(
        `[data-value="${iso}"]:not([data-outside-view])`,
    );

    const MONTHS = [
        'enero',
        'febrero',
        'marzo',
        'abril',
        'mayo',
        'junio',
        'julio',
        'agosto',
        'septiembre',
        'octubre',
        'noviembre',
        'diciembre',
    ];
    const [year, month] = iso.split('-').map(Number);
    const target = year * 12 + month - 1;

    for (let i = 0; i < 6 && !(await day.isVisible()); i++) {
        const heading = (
            await picker
                .getByText(/^[a-záéíóú]+ de \d{4}$/i)
                .first()
                .innerText()
        ).toLowerCase();
        const [name, , y] = heading.split(' ');
        const shown = Number(y) * 12 + MONTHS.indexOf(name);
        await picker
            .getByRole('button', {
                name: shown > target ? /previous|anterior/i : /next|siguiente/i,
            })
            .click();
    }

    await day.click();
    const slots = picker
        .getByRole('radiogroup', { name: 'Horarios disponibles' })
        .getByRole('radio');
    await expect(slots.first()).toBeVisible();
    const slot = slots.nth(slotIndex);
    const label = (await slot.innerText()).trim();
    await slot.click();
    await expect(slot).toHaveAttribute('aria-checked', 'true');

    return label;
}
