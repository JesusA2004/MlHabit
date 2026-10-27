import { launch, BASE, SHOTS, login, collectErrors } from './lib.mjs';

/**
 * Criterio 30: operaciones básicas de Studio en un iPad (1024×768 y
 * 768×1024, táctil). Paneles plegables, selección por toque, cambio de
 * texto, guardado.
 */
const results = [];
const check = (name, ok, detail = '') => results.push(`${ok ? 'PASS' : 'FAIL'}  ${name}${detail ? ` — ${detail}` : ''}`);

const b = await launch();
for (const [w, h] of [[1024, 768], [768, 1024]]) {
    const ctx = await b.newContext({ viewport: { width: w, height: h }, hasTouch: true, deviceScaleFactor: 2 });
    const page = await ctx.newPage();
    page.setDefaultTimeout(10000);
    const errors = [];
    collectErrors(page, errors, `ipad${w}`);
    try {
        await login(page);
        await page.goto(`${BASE}/admin/studio`, { waitUntil: 'networkidle' });
        await page.tap('text=Editar >> nth=0');
        await page.waitForURL(/admin\/studio\/\d+$/);
        const frame = page.frameLocator('iframe[title="Lienzo de edición"]');
        await frame.locator('[data-type="heading"]').first().waitFor({ timeout: 20000 });
        await page.waitForTimeout(800);

        check(`[${w}] Paneles plegables visibles`, await page.locator('button[aria-label="Estructura"]').isVisible() && await page.locator('button[aria-label="Propiedades"]').isVisible());
        await frame.locator('[data-type="heading"]').first().tap();
        await page.waitForTimeout(500);
        check(`[${w}] Tocar un título lo selecciona y abre propiedades`, (await page.locator('aside[aria-label="Propiedades"]').textContent()).includes('Título'));

        await page.tap('role=tab[name="Contenido"]');
        const textarea = page.locator('aside[aria-label="Propiedades"] textarea').first();
        await textarea.fill(`Escrito desde el iPad ${w}`);
        await page.waitForTimeout(600);
        const canvasText = await frame.locator('[data-type="heading"]').first().textContent();
        check(`[${w}] Editar texto`, canvasText.includes(`Escrito desde el iPad ${w}`));

        await page.tap('button[aria-label="Propiedades"]');
        await page.tap('button[aria-label="Estructura"]');
        await page.waitForTimeout(300);
        const sectionsBtn = page.locator('aside[aria-label="Estructura de la página"] button[aria-label="Bajar sección"]').first();
        check(`[${w}] Controles de orden accesibles por toque`, await sectionsBtn.isVisible());

        await page.waitForTimeout(2200);
        const status = (await page.locator('header [role=status]').first().textContent()) ?? '';
        check(`[${w}] Autoguardado`, status.toLowerCase().includes('guardado'), status.trim());
        await page.screenshot({ path: `${SHOTS}ipad-${w}x${h}.png` });
        await page.keyboard.press('Control+z');
    } catch (e) {
        results.push(`ABORT [${w}] ${e.message.split('\n')[0]}`);
        await page.screenshot({ path: `${SHOTS}ipad-abort-${w}.png` });
    }
    if (errors.length) results.push(...errors);
    await ctx.close();
}
await b.close();
console.log(results.join('\n'));
process.exitCode = results.some((r) => !r.startsWith('PASS')) ? 1 : 0;
