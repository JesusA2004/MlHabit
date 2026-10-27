import { launch, BASE, SHOTS, collectErrors } from './lib.mjs';

// Uso: node public.mjs "ruta1,ruta2" "1440,390"   (rutas sin barra inicial; vacío = inicio)
const paths = (process.argv[2] ?? ',mi-historia,mi-proceso,redes').split(',');
const widths = (process.argv[3] ?? '1440,390').split(',').map(Number);

const browser = await launch();
const errors = [];
for (const width of widths) {
    const ctx = await browser.newContext({ viewport: { width, height: width > 500 ? 900 : 844 } });
    const page = await ctx.newPage();
    collectErrors(page, errors, `w${width}`);
    for (const path of paths) {
        await page.goto(`${BASE}/${path}`, { waitUntil: 'networkidle' });
        await page.evaluate(async () => {
            for (let y = 0; y < document.body.scrollHeight; y += 400) {
                window.scrollTo(0, y);
                await new Promise((r) => setTimeout(r, 60));
            }
            window.scrollTo(0, 0);
        });
        await page.waitForTimeout(900);
        const name = path.replace(/\//g, '_') || 'home';
        await page.screenshot({ path: `${SHOTS}pub_${name}-${width}.png`, fullPage: true });
        const overflow = await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth);
        if (overflow) errors.push(`[w${width}] desbordamiento horizontal en /${path}`);
    }
    await ctx.close();
}
await browser.close();
console.log(errors.length ? errors.join('\n') : 'sin errores ni desbordamientos');
