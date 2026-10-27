import { launch, BASE, SHOTS, login, collectErrors } from './lib.mjs';

// Capturas de todas las pantallas admin. Uso: node admin-visual.mjs "1440,390"
const widths = (process.argv[2] ?? '1440,390').split(',').map(Number);
const pages = [
    'admin', 'admin/studio', 'admin/calendario', 'admin/publicaciones', 'admin/publicaciones/create',
    'admin/multimedia', 'admin/categorias', 'admin/categorias/create', 'admin/paginas', 'admin/paginas/mi-proceso',
    'admin/redes-sociales', 'admin/apariencia', 'admin/inicio', 'admin/almacenamiento', 'admin/actividad', 'settings/profile',
];
const b = await launch();
const errors = [];
for (const width of widths) {
    const anon = await b.newContext({ viewport: { width, height: 900 } });
    const lp = await anon.newPage();
    collectErrors(lp, errors, `login${width}`);
    await lp.goto(`${BASE}/login`, { waitUntil: 'networkidle' });
    await lp.screenshot({ path: `${SHOTS}adm_login-${width}.png`, fullPage: true });
    await anon.close();

    const ctx = await b.newContext({ viewport: { width, height: 900 } });
    const page = await ctx.newPage();
    collectErrors(page, errors, `w${width}`);
    await login(page);
    for (const p of pages) {
        const r = await page.goto(`${BASE}/${p}`, { waitUntil: 'networkidle' });
        if (!r || r.status() >= 400) errors.push(`[w${width}] /${p} → HTTP ${r?.status()}`);
        await page.waitForTimeout(400);
        await page.screenshot({ path: `${SHOTS}adm_${p.replace(/\//g, '_')}-${width}.png`, fullPage: true });
        if (await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth + 1)) errors.push(`[w${width}] desbordamiento en /${p}`);
    }
    await ctx.close();
}
await b.close();
console.log(errors.length ? errors.join('\n') : 'sin errores ni desbordamientos');
process.exitCode = errors.length ? 1 : 0;
