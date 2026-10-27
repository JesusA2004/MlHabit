import { expect } from '@playwright/test';
import type { Page } from '@playwright/test';
import { pickDateAndSlot } from './helpers';

export type VisitCustomer = {
    first: string;
    last: string;
    email: string;
    phone: string;
};

/** Paso 1 del checkout: código postal. */
export async function enterPostalCode(
    page: Page,
    postalCode: string,
): Promise<void> {
    await page
        .getByRole('region', { name: '¿Dónde será la visita?' })
        .getByLabel('Código postal')
        .fill(postalCode);
    await page.getByRole('button', { name: 'Continuar' }).click();
}

/**
 * Recorre el checkout de la visita (desde la página ya abierta) hasta
 * "Continuar al pago" sin pulsarlo. Devuelve la etiqueta del horario.
 */
export async function fillVisitCheckout(
    page: Page,
    opts: { dateIso: string; slotIndex?: number; customer?: VisitCustomer },
): Promise<string> {
    await enterPostalCode(page, '01000');
    await expect(
        page.getByRole('heading', { name: 'Elige día y horario' }),
    ).toBeVisible();
    const slot = await pickDateAndSlot(page, opts.dateIso, opts.slotIndex ?? 0);
    await page.getByRole('button', { name: /^Continuar/ }).click();

    await expect(
        page.getByRole('heading', { name: 'Domicilio y contacto' }),
    ).toBeVisible();
    await page.getByLabel('Calle').fill('Calle TEST');
    await page.getByLabel('Número exterior').fill('123');
    await page.getByLabel('Colonia').fill('Colonia TEST');
    await page.getByLabel('Municipio o alcaldía').fill('Álvaro Obregón');
    await page.getByLabel('Estado', { exact: true }).fill('Ciudad de México');

    if (opts.customer) {
        await page
            .getByLabel('Nombre', { exact: true })
            .fill(opts.customer.first);
        await page.getByLabel(/^Apellidos/).fill(opts.customer.last);
        await page.getByLabel('Correo electrónico').fill(opts.customer.email);
        await page.getByLabel('Teléfono celular').fill(opts.customer.phone);
    }

    await page.getByRole('button', { name: 'Revisar y continuar' }).click();
    await expect(
        page.getByRole('heading', { name: 'Revisa y confirma' }),
    ).toBeVisible();
    await page
        .getByRole('checkbox', { name: /Acepto el alcance de la visita/ })
        .click();

    return slot;
}

/** Pulsa "Continuar al pago" y espera la página de pago con el apartado. */
export async function continueToPayment(page: Page): Promise<string> {
    await page.getByRole('button', { name: 'Continuar al pago' }).click();
    await expect(page).toHaveURL(/\/agendar-visita\/pago\/[0-9a-f-]{36}/);
    await expect(
        page.getByRole('heading', {
            level: 1,
            name: /confirma tu visita con el pago/,
        }),
    ).toBeVisible();
    await expect(
        page
            .getByRole('status')
            .filter({ hasText: 'Tu horario está apartado' }),
    ).toContainText(/\d{2}:\d{2}/);

    return page.url().match(/pago\/([0-9a-f-]{36})/)![1];
}
