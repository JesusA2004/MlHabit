import { expect, test } from '@playwright/test';
import type { Browser, Page } from '@playwright/test';
import {
    fillCard,
    localDate,
    login,
    loginAs,
    php,
    pickDateAndSlot,
    stubOpenpay,
    TEST_CARDS,
} from './support/helpers';
import { continueToPayment, fillVisitCheckout } from './support/visit';

/**
 * Recorrido completo, en serie: solicitud → asesoría → visita pagada →
 * medición → proyecto → diseño → cotización → contrato con crédito →
 * anticipo → fabricación → instalación → conformidad → calidad → completado.
 *
 * El pago usa la pasarela simulada E2E (ver README): NO es Openpay real.
 */
test.describe.configure({ mode: 'serial' });

const RUN = Date.now().toString().slice(-7);
const CUSTOMER = {
    first: `Ana${RUN}`,
    last: 'Cliente TEST',
    email: `ana.${RUN}@cliente-e2e.test`,
    phone: `55${RUN.padStart(8, '1').slice(-8)}`,
};

const CUSTOMER_PASSWORD = 'Cliente-E2e-2026!';

const state: {
    leadUuid?: string;
    folio?: string;
    visitUuid?: string;
    paymentUuid?: string;
    projectId?: number;
    projectUrl?: string;
} = {};

async function staffPage(
    browser: Browser,
    role: Parameters<typeof login>[1],
    mobile = false,
): Promise<Page> {
    const context = await browser.newContext(
        mobile
            ? {
                  viewport: { width: 390, height: 844 },
                  isMobile: true,
                  hasTouch: true,
              }
            : {},
    );
    const page = await context.newPage();
    await login(page, role);

    return page;
}

test('1. visitante explora el catálogo y envía /solicitar', async ({
    page,
}) => {
    await page.goto('/');
    await page
        .getByRole('banner')
        .getByRole('link', { name: 'Catálogo' })
        .click();
    await page
        .getByRole('main')
        .getByRole('link', { name: /Cocinas TEST/ })
        .first()
        .click();
    await expect(
        page.getByRole('heading', { level: 1, name: 'Cocinas TEST' }),
    ).toBeVisible();

    await page.goto('/solicitar');
    // 1. Espacio
    await page
        .getByRole('group', { name: 'Espacios que quieres renovar' })
        .getByRole('button', { name: /Cocinas TEST/ })
        .click();
    await page.getByRole('button', { name: 'Continuar' }).click();
    // 2. Ubicación (CP con cobertura)
    await page.getByLabel('Código postal').fill('01000');
    await expect(page.locator('#postal_code-help')).toContainText(
        /llegamos|cobertura|zona/i,
    );
    await page.getByRole('button', { name: 'Continuar' }).click();
    // 3. Presupuesto
    for (const group of [
        'Presupuesto aproximado',
        'Forma de pago preferida',
        'Plazo del proyecto',
    ]) {
        await page
            .getByRole('radiogroup', { name: group })
            .getByRole('radio')
            .first()
            .click();
    }
    await page.getByRole('button', { name: 'Continuar' }).click();
    // 4. Contacto
    await page.getByLabel('Nombre', { exact: true }).fill(CUSTOMER.first);
    await page.getByLabel(/Apellidos/).fill(CUSTOMER.last);
    await page.getByLabel('Teléfono celular').fill(CUSTOMER.phone);
    await page.getByLabel(/Correo/).fill(CUSTOMER.email);
    await page
        .getByRole('radiogroup', { name: 'Medio de contacto preferido' })
        .getByRole('radio')
        .first()
        .click();
    await page.getByRole('button', { name: 'Continuar' }).click();
    // 5. Detalles
    await page.getByLabel(/Medidas aproximadas/).fill('Pared TEST de 3.20 m');
    await page.getByRole('checkbox', { name: /aviso de privacidad/ }).click();
    await page.getByRole('button', { name: 'Enviar solicitud' }).click();

    await expect(page).toHaveURL(/\/solicitar\/gracias\//);
    const folio = page.getByText(/^MLH-L-\d{4}-\d{5}$/);
    await expect(folio).toBeVisible();
    state.folio = (await folio.innerText()).trim();
    state.leadUuid = page.url().match(/gracias\/([0-9a-f-]{36})/)?.[1];
    expect(state.leadUuid).toBeTruthy();
});

test('2. comercial ve el prospecto y programa la asesoría', async ({
    browser,
}) => {
    const page = await staffPage(browser, 'comercial');
    await page.goto('/admin/prospectos');
    await page
        .getByRole('link', { name: new RegExp(CUSTOMER.first) })
        .first()
        .click();
    await expect(page).toHaveURL(
        new RegExp(`/admin/prospectos/${state.leadUuid}`),
    );
    await expect(page.getByText(state.folio!).first()).toBeVisible();

    await page.getByRole('button', { name: 'Programar asesoría' }).click();
    const sheet = page.getByRole('dialog', { name: 'Programar asesoría' });
    await expect(sheet).toBeVisible();
    await pickDateAndSlot(page, localDate(1));
    await sheet.getByRole('button', { name: 'Agendar asesoría' }).click();
    await expect(sheet).toBeHidden();
    await expect(page.getByText(/asesoría/i).first()).toBeVisible();
    const consultations = php<number>(
        `App\\Models\\Consultation::whereHas('lead', fn($q) => $q->where('uuid', '${state.leadUuid}'))->count()`,
    );
    expect(consultations).toBe(1);
    await page.context().close();
});

test('3. el cliente abre su enlace firmado, aparta horario y paga con tarjeta aprobada', async ({
    page,
}) => {
    await stubOpenpay(page);
    const signed = php<string>(
        `URL::signedRoute('public.visita.inicio', ['lead' => '${state.leadUuid}'])`,
    );
    await page.goto(signed);
    await expect(
        page.getByRole('heading', {
            level: 1,
            name: new RegExp(`${CUSTOMER.first}, agenda tu visita`),
        }),
    ).toBeVisible();

    await fillVisitCheckout(page, { dateIso: localDate(1) });
    state.paymentUuid = await continueToPayment(page);

    // La llave privada nunca llega al navegador.
    expect(await page.content()).not.toMatch(
        /sk_[A-Za-z0-9]|private_key|privateKey/,
    );

    const pay = page.getByRole('button', { name: /^Pagar \$1,500\.00/ });
    await expect(pay).toBeEnabled();
    await fillCard(page, TEST_CARDS.approved);
    await pay.click();

    // Solo se muestra éxito tras la confirmación del backend (página de estado firmada).
    await expect(page).toHaveURL(
        /\/agendar-visita\/pago\/[0-9a-f-]{36}\/estado/,
    );
    await expect(
        page.getByRole('heading', {
            level: 1,
            name: new RegExp(
                `¡Listo, ${CUSTOMER.first}! Tu visita está agendada`,
            ),
        }),
    ).toBeVisible();

    const db = php<{
        status: string;
        attempts: number;
        visit: string;
        uuid: string;
    }>(
        `(function () { $p = App\\Models\\Payment::where('uuid', '${state.paymentUuid}')->first(); return ['status' => $p->status->value, 'attempts' => $p->attempts()->count(), 'visit' => App\\Models\\SiteVisit::find($p->site_visit_id)->status->value, 'uuid' => App\\Models\\SiteVisit::find($p->site_visit_id)->uuid]; })()`,
    );
    expect(db.status).toBe('paid');
    expect(db.attempts).toBe(1);
    expect(db.visit).toBe('scheduled');
    state.visitUuid = db.uuid;
});

test('4. webhook charge.succeeded duplicado se procesa una sola vez', async ({
    request,
}) => {
    const tx = php<string>(
        `App\\Models\\Payment::where('uuid', '${state.paymentUuid}')->value('provider_transaction_id')`,
    );
    expect(tx).toMatch(/^tre2e/);
    const body = {
        type: 'charge.succeeded',
        event_date: '2026-10-27T12:00:00-06:00',
        transaction: {
            id: tx,
            status: 'completed',
            method: 'card',
            amount: 1500.0,
        },
    };
    const auth = `Basic ${Buffer.from('e2e-webhook:e2e-webhook-password').toString('base64')}`;

    const bad = await request.post('/webhooks/openpay', {
        data: body,
        headers: { Authorization: 'Basic eDp5' },
    });
    expect(bad.status()).toBe(401);

    const first = await request.post('/webhooks/openpay', {
        data: body,
        headers: { Authorization: auth },
    });
    expect(first.status()).toBe(200);
    expect(await first.json()).toEqual({ received: true });

    const second = await request.post('/webhooks/openpay', {
        data: body,
        headers: { Authorization: auth },
    });
    expect(second.status()).toBe(200);
    expect(await second.json()).toEqual({ received: true, duplicate: true });

    const db = php<{
        events: number;
        status: string;
        paidEvents: number;
        credits: number;
    }>(
        `(function () { $p = App\\Models\\Payment::where('uuid', '${state.paymentUuid}')->first(); return ['events' => App\\Models\\WebhookEvent::where('transaction_id', '${tx}')->count(), 'status' => App\\Models\\WebhookEvent::where('transaction_id', '${tx}')->value('status')->value, 'paidEvents' => $p->events()->where('to_status', 'paid')->count(), 'credits' => App\\Models\\VisitCredit::where('site_visit_id', $p->site_visit_id)->count()]; })()`,
    );
    expect(db.events).toBe(1);
    expect(db.status).toBe('processed');
    expect(db.paidEvents).toBe(1);
    expect(db.credits).toBe(1);
});

test('5. la visita aparece pagada en el panel', async ({ browser }) => {
    const page = await staffPage(browser, 'comercial');
    await page.goto(`/admin/visitas/${state.visitUuid}`);
    await expect(page.getByText('Agendada').first()).toBeVisible();
    await expect(page.getByText('Pagado').first()).toBeVisible();
    await page.context().close();
});

test('6. técnico (móvil) inicia la visita, captura y envía la medición y la completa', async ({
    browser,
}) => {
    const page = await staffPage(browser, 'tecnico', true);
    await expect(page).toHaveURL(/\/tecnico/);
    await page.goto('/tecnico/visitas');
    await page
        .getByRole('link', { name: new RegExp(CUSTOMER.first) })
        .first()
        .click();
    await expect(page).toHaveURL(
        new RegExp(`/tecnico/visitas/${state.visitUuid}`),
    );

    await page.getByRole('button', { name: 'Iniciar visita' }).click();
    await page.getByRole('button', { name: 'Registrar medición' }).click();
    await expect(page).toHaveURL(/\/tecnico\/mediciones\/\d+/);

    await page
        .getByRole('textbox', { name: /Empieza por un área|Agregar otra área/ })
        .fill('Cocina TEST');
    await page.getByRole('button', { name: 'Área', exact: true }).click();
    const area = page.getByRole('region', { name: 'Área Cocina TEST' });
    // Un área nueva ya trae una fila de Ancho.
    await page.getByLabel(/^Ancho en (milímetros|centímetros)$/).fill('3200');
    await area.getByRole('button', { name: 'Alto', exact: true }).click();
    await page.getByLabel(/^Alto en (milímetros|centímetros)$/).fill('2400');
    await page
        .getByRole('radiogroup', { name: 'Tipo de espacio' })
        .getByRole('radio', { name: 'Cocina' })
        .click();
    const saved = page.waitForResponse(
        (r) =>
            r.request().method() === 'PUT' &&
            /\/tecnico\/mediciones\/\d+$/.test(r.url()),
    );
    await page.getByRole('button', { name: 'Guardar' }).click();
    expect((await saved).status()).toBeLessThan(400);

    await page.getByRole('button', { name: 'Enviar medición' }).click();
    await page
        .getByRole('alertdialog')
        .getByRole('button', { name: 'Enviar medición' })
        .click();
    await expect(page.getByText('Enviada').first()).toBeVisible();

    await page.goto(`/tecnico/visitas/${state.visitUuid}`);
    await page.getByRole('button', { name: 'Completar visita' }).click();
    await page
        .getByRole('alertdialog')
        .getByRole('button', { name: 'Completar visita' })
        .click();
    await expect(page).toHaveURL(/\/tecnico\/?$/);
    expect(
        php<string>(
            `App\\Models\\SiteVisit::where('uuid', '${state.visitUuid}')->first()->status->value`,
        ),
    ).toBe('completed');
    expect(
        php<string>(
            `App\\Models\\Measurement::where('site_visit_id', App\\Models\\SiteVisit::where('uuid', '${state.visitUuid}')->value('id'))->first()->status->value`,
        ),
    ).toBe('submitted');
    await page.context().close();
});

test('7. comercial crea el proyecto desde el prospecto', async ({
    browser,
}) => {
    const page = await staffPage(browser, 'comercial');
    await page.goto(`/admin/prospectos/${state.leadUuid}`);
    await page.getByRole('button', { name: 'Crear proyecto' }).click();
    await page
        .getByRole('alertdialog')
        .getByRole('button', { name: 'Crear proyecto' })
        .click();
    // Los proyectos se direccionan por UUID (no por id incremental).
    await expect(page).toHaveURL(/\/admin\/proyectos\/[0-9a-f-]{36}$/);
    state.projectUrl = new URL(page.url()).pathname;
    const projectUuid = state.projectUrl.split('/').pop()!;
    state.projectId = Number(
        php<number>(
            `App\\Models\\Project::where('uuid', '${projectUuid}')->value('id')`,
        ),
    );
    await expect(page.getByText(/^MLH-P-\d{4}-\d{5}$/).first()).toBeVisible();
    await page.context().close();
});

test('8. diseño crea la versión 1 con un render y la envía al cliente', async ({
    browser,
}) => {
    const page = await staffPage(browser, 'diseno');
    await page.goto(`${state.projectUrl}?tab=diseno`);
    await page.getByRole('button', { name: 'Crear versión 1' }).click();
    const sheet = page.getByRole('dialog');
    await sheet.getByLabel('Título').fill('Cocina TEST propuesta');
    await sheet.getByRole('button', { name: 'Guardar' }).click();
    await expect(sheet).toBeHidden();
    await expect(
        page.getByText(/Diseño v1 · Cocina TEST propuesta/),
    ).toBeVisible();

    const attached = page.waitForResponse(
        (r) =>
            r.request().method() === 'POST' &&
            /\/admin\/disenos\/\d+\/archivos$/.test(r.url()),
    );
    await page
        .getByLabel(/Arrastra archivos aquí o elige/)
        .setInputFiles('tests/e2e/fixtures/evidencia-test.jpg');
    expect((await attached).status()).toBeLessThan(400);

    const send = page.getByRole('button', { name: 'Enviar al cliente' });
    await expect(send).toBeEnabled();
    await send.click();
    await page
        .getByRole('alertdialog')
        .getByRole('button', { name: 'Enviar', exact: true })
        .click();
    await expect(
        page.getByText(/Enviado|En revisión|Esperando/i).first(),
    ).toBeVisible();
    expect(
        php<string>(
            `App\\Models\\DesignVersion::where('project_id', ${state.projectId})->first()->status->value`,
        ),
    ).toBe('sent_to_client');
    await page.context().close();
});

test('9. comercial invita al cliente al portal y el cliente crea su contraseña', async ({
    browser,
    page,
}) => {
    const staff = await staffPage(browser, 'comercial');
    const customerUuid = php<string>(
        `App\\Models\\Lead::where('uuid', '${state.leadUuid}')->first()->customer->uuid`,
    );
    await staff.goto(`/admin/clientes/${customerUuid}`);
    await staff.getByRole('button', { name: 'Invitar al portal' }).click();
    await staff
        .getByRole('alertdialog')
        .getByRole('button', { name: 'Enviar invitación' })
        .click();
    await expect(staff.getByText(/Invitación enviada/)).toBeVisible();
    await staff.context().close();

    // El enlace firmado es el mismo que lleva el correo (se obtiene de la notificación).
    const link = php<string>(
        `(new App\\Notifications\\Platform\\UserInvitation('E2E'))->toMail(App\\Models\\User::where('email', '${CUSTOMER.email}')->first())->actionUrl`,
    );
    await page.goto(link);
    await expect(page.getByLabel('Correo electrónico')).toHaveValue(
        CUSTOMER.email,
    );
    await page
        .getByLabel('Contraseña', { exact: true })
        .fill(CUSTOMER_PASSWORD);
    await page.getByLabel('Confirma la contraseña').fill(CUSTOMER_PASSWORD);
    await page.getByRole('button', { name: 'Activar mi cuenta' }).click();
    // Fortify guarda la contraseña y regresa al inicio de sesión.
    await expect(page).toHaveURL(/\/login/);
    await loginAs(page, CUSTOMER.email, CUSTOMER_PASSWORD);
    await expect(page).toHaveURL(/\/portal/);
});

test('10. el cliente aprueba el diseño en el portal', async ({ page }) => {
    await loginAs(page, CUSTOMER.email, CUSTOMER_PASSWORD);
    await page.goto('/portal/disenos');
    await page
        .getByRole('radiogroup', { name: 'Tu decisión' })
        .getByRole('radio', { name: 'Aprobar' })
        .click();
    await page.getByLabel(/La distribución/).click();
    await page.getByLabel(/Los acabados/).click();
    await page.getByRole('button', { name: 'Aprobar diseño' }).click();
    await expect(
        page.getByRole('button', { name: 'Aprobar diseño' }),
    ).toBeHidden();
    expect(
        php<string>(
            `App\\Models\\DesignVersion::where('project_id', ${state.projectId})->first()->status->value`,
        ),
    ).toBe('client_approved');
});

test('11. diseño da la aprobación técnica', async ({ browser }) => {
    const page = await staffPage(browser, 'diseno');
    await page.goto(`${state.projectUrl}?tab=diseno`);
    await page.getByRole('button', { name: 'Aprobación técnica' }).click();
    await page
        .getByRole('dialog')
        .getByRole('button', { name: 'Aprobar técnicamente' })
        .click();
    await expect(page.getByRole('dialog')).toBeHidden();
    expect(
        php<string | null>(
            `App\\Models\\DesignVersion::where('project_id', ${state.projectId})->first()->technical_approved_at`,
        ),
    ).not.toBeNull();
    await page.context().close();
});
