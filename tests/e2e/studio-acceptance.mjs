import { launch, BASE, SHOTS, login, collectErrors } from './lib.mjs';

const results = [];
function check(name, ok, detail = '') {
    results.push(`${ok ? 'PASS' : 'FAIL'}  ${name}${detail ? ` — ${detail}` : ''}`);
}

const b = await launch();
const ctx = await b.newContext({ viewport: { width: 1600, height: 960 } });
const page = await ctx.newPage();
page.setDefaultTimeout(8000);
const errors = [];
collectErrors(page, errors, 'editor');
await login(page);
try {

// 1-3 Entrar, elegir página, ver estructura real
await page.goto(BASE + '/admin/studio', { waitUntil: 'networkidle' });
check('1 Entrar a Studio', (await page.locator('h1').textContent()).includes('Diseña'));
await page.click('text=Editar >> nth=0');
await page.waitForURL(/admin\/studio\/\d+$/);
const frame = page.frameLocator('iframe[title="Lienzo de edición"]');
const canvas = () => page.frames().find((f) => f.url().includes('/canvas'));
await frame.locator('[data-type="heading"]').first().waitFor({ timeout: 20000 });
await page.waitForTimeout(800);
check('2 Seleccionar página (Inicio)', (await page.locator('header').first().textContent()).includes('Inicio'));
const sections = await frame.locator('section[data-n]').count();
const treeRows = await page.locator('aside[aria-label="Estructura de la página"] .drag-section').count();
check('3 Estructura real', sections > 0 && sections === treeRows, `${sections} secciones en canvas / ${treeRows} en árbol`);

const heading = () => frame.locator('[data-type="heading"] .sn-t').first();
const css = (loc, prop) => loc.evaluate((el, p) => getComputedStyle(el)[p], prop);

// 4-8 Título
await frame.locator('[data-type="heading"]').first().click();
await page.waitForTimeout(400);
check('4 Seleccionar título', (await page.locator('aside[aria-label="Propiedades"]').textContent()).includes('Título'));
await page.click('role=tab[name="Estilo"]');
const size = page.locator('input[aria-label="Tamaño (valor)"]');
await size.fill('82');
await size.press('Enter');
await page.waitForTimeout(500);
check('5 Cambiar tamaño 64→82', (await css(heading(), 'fontSize')) === '82px', await css(heading(), 'fontSize'));
await page.locator('aside[aria-label="Propiedades"] button[title="Oro"]').first().click();
await page.waitForTimeout(500);
check('6 Cambiar color', (await css(heading(), 'color')) === 'rgb(199, 170, 125)', await css(heading(), 'color'));
await page.locator('aside[aria-label="Propiedades"] select[aria-label="Familia"]').selectOption('Playfair Display');
await page.waitForTimeout(1200);
check('7 Cambiar fuente', (await css(heading(), 'fontFamily')).includes('Playfair'), await css(heading(), 'fontFamily'));
await page.click('role=tab[name="Diseño"]');
await page.locator('aside[aria-label="Propiedades"] [role=radiogroup][aria-label="Posición en la columna"] >> text=Centro').click();
await page.waitForTimeout(400);
const alignSelf = await frame.locator('[data-type="heading"]').first().evaluate((el) => getComputedStyle(el).alignSelf);
const headingIdBefore = await frame.locator('[data-type="heading"]').first().getAttribute('data-n');
await frame.locator('.studio-toolbar button[title^="Bajar"]').click();
await page.waitForTimeout(500);
const order = await canvas().evaluate(() => [...document.querySelector('[data-slot="content"]').children].map((c) => c.dataset.type).join(','));
check('8 Elegir dónde aparece (alinear + mover)', alignSelf === 'center' && order.indexOf('heading') > order.indexOf('buttons'), `align=${alignSelf} orden=${order}`);
await page.keyboard.press('Control+z');
await page.waitForTimeout(400);
const orderUndo = await canvas().evaluate(() => [...document.querySelector('[data-slot="content"]').children].map((c) => c.dataset.type).join(','));
check('   Deshacer', orderUndo.indexOf('heading') < orderUndo.indexOf('buttons'), orderUndo);

// 9-11 Imagen
await frame.locator('section[data-n] [data-type="image"]').first().click();
await page.waitForTimeout(300);
check('9 Seleccionar imagen', (await page.locator('aside[aria-label="Propiedades"]').textContent()).includes('Imagen'));
const srcBefore = await frame.locator('[data-type="image"] img').first().getAttribute('src');
await page.click('role=tab[name="Contenido"]');
await page.locator('aside[aria-label="Propiedades"] button:has-text("Cambiar")').first().click();
await page.locator('role=dialog >> button:has-text("paisaje.jpg")').click();
await page.waitForTimeout(800);
const srcAfter = await frame.locator('[data-type="image"] img').first().getAttribute('src');
check('10 Cambiar fotografía', srcBefore !== srcAfter, `${srcBefore} → ${srcAfter}`);
await page.click('role=tab[name="Estilo"]');
const focal = page.locator('[aria-label="Punto focal de la imagen"]');
await focal.focus();
for (let i = 0; i < 3; i++) await page.keyboard.press('Shift+ArrowRight');
await page.waitForTimeout(400);
const objPos = await frame.locator('[data-type="image"] img').first().evaluate((el) => getComputedStyle(el).objectPosition);
check('11 Ajustar encuadre', objPos.startsWith('80%'), objPos);

// 14 Composición de sección (hero → cinematográfico)
await frame.locator('[data-type="image"]').first().click({ modifiers: ['Alt'] });
await page.waitForTimeout(300);
await page.click('role=tab[name="Diseño"]');
await page.locator('[role=radiogroup][aria-label="Composición"] >> text=Cinematográfico').click();
await page.waitForTimeout(700);
check('14 Elegir composición de sección', (await frame.locator('.sn-hero--cinematic').count()) === 1);
await page.screenshot({ path: SHOTS + 'full-cinematic.png' });
await page.locator('[role=radiogroup][aria-label="Composición"] >> text=Editorial clásico').click();

// 15 Reordenar secciones (botón del árbol)
const firstTitle = await page.locator('aside[aria-label="Estructura de la página"] .drag-section').nth(1).locator('xpath=..').textContent();
await page.locator('aside[aria-label="Estructura de la página"] button[aria-label="Bajar sección"]').nth(1).click({ force: true });
await page.waitForTimeout(400);
const secondTitle = await page.locator('aside[aria-label="Estructura de la página"] .drag-section').nth(2).locator('xpath=..').textContent();
check('15 Reorganizar secciones', firstTitle === secondTitle, firstTitle.trim().slice(0, 30));

// 12-13 Insertar video y cambiar tamaño
await page.locator('aside[aria-label="Estructura de la página"] button:has-text("Agregar sección")').click();
await page.locator('role=dialog >> button:has-text("Estructura")').click();
await page.locator('role=dialog >> button:has-text("Video y texto")').click();
await page.waitForTimeout(700);
const videoBlocks = await frame.locator('[data-type="video"]').count();
check('12 Insertar video', videoBlocks >= 1, `${videoBlocks} bloque(s) de video`);
await frame.locator('[data-type="video"]').first().click();
await page.waitForTimeout(500);
check('   Clic en video vacío abre la biblioteca', await page.locator('role=dialog').isVisible());
await page.keyboard.press('Escape');
await page.waitForTimeout(300);
await page.click('role=tab[name="Diseño"]');
const width = page.locator('input[aria-label="Anchura (valor)"]');
await width.fill('70');
await width.press('Enter');
await page.waitForTimeout(400);
const vw = await frame.locator('[data-type="video"]').first().evaluate((el) => el.style.width || getComputedStyle(el).width);
check('13 Cambiar tamaño del video', vw.endsWith('px') || vw.endsWith('%'), vw);

// 16-19 Agregar, duplicar, ocultar, eliminar
await page.click('role=tab[name="Agregar"] >> nth=0').catch(() => {});
await page.locator('aside[aria-label="Estructura de la página"] [role=tab]:has-text("Agregar")').click();
await page.locator('aside[aria-label="Estructura de la página"] button:has-text("Cita destacada")').click();
await page.waitForTimeout(500);
const quotes = await frame.locator('[data-type="quote"]').count();
check('16 Agregar bloque', quotes >= 1);
await page.keyboard.press('Control+d');
await page.waitForTimeout(400);
check('17 Duplicar', (await frame.locator('[data-type="quote"]').count()) === quotes + 1);
await frame.locator('.studio-toolbar button[title="Ocultar"]').click();
await page.waitForTimeout(400);
check('18 Ocultar', (await frame.locator('[data-type="quote"][data-hidden]').count()) === 1);
await page.keyboard.press('Delete');
await page.waitForTimeout(400);
check('19 Eliminar', (await frame.locator('[data-type="quote"]').count()) === quotes);

// 20 Animación
await frame.locator('[data-type="heading"]').first().click();
await page.click('role=tab[name="Movimiento"]');
await page.locator('[role=radiogroup][aria-label="Animación"] >> text=Escalar').click();
await page.waitForTimeout(400);
const hasReveal = await frame.locator('[data-type="heading"]').first().getAttribute('data-reveal');
check('20 Aplicar animación', hasReveal !== null);

// 21-23 Dispositivos
for (const [n, label, w] of [[21, 'Escritorio', 1440], [22, 'Tablet', 768], [23, 'Móvil', 390]]) {
    await page.click(`button[aria-label^="${label}"]`);
    await page.waitForTimeout(900);
    const iw = await canvas().evaluate(() => window.innerWidth);
    await page.screenshot({ path: SHOTS + `full-device-${w}.png` });
    check(`${n} Ver ${label.toLowerCase()}`, iw === w, `${iw}px`);
}
const mobileSize = await css(heading(), 'fontSize');
check('   Auto-escalado del título en móvil', parseFloat(mobileSize) < 82, mobileSize);
await page.click('button[aria-label^="Escritorio"]');

// 24 Guardar borrador
await page.keyboard.press('Control+s');
await page.waitForTimeout(2000);
check('24 Guardar borrador', (await page.locator('[role=status]').first().textContent()).includes('guardado'));
await page.reload({ waitUntil: 'networkidle' });
await frame.locator('[data-type="heading"]').first().waitFor();
await page.waitForTimeout(800);
check('   Persistencia tras recargar', (await css(heading(), 'fontSize')) === '82px');

// 25 Vista previa sin publicar
const preview = await ctx.newPage();
await preview.goto(page.url() + '/vista-previa', { waitUntil: 'networkidle' });
await preview.waitForTimeout(800);
const prevSize = await preview.locator('h1').first().evaluate((el) => getComputedStyle(el).fontSize);
const pub = await ctx.newPage();
await pub.goto(BASE + '/', { waitUntil: 'networkidle' });
const pubSizeBefore = await pub.locator('h1').first().evaluate((el) => getComputedStyle(el).fontSize);
check('25 Previsualizar sin publicar', prevSize === '82px' && pubSizeBefore !== '82px', `preview=${prevSize} público=${pubSizeBefore}`);
await preview.screenshot({ path: SHOTS + 'full-preview.png' });

// 26 Publicar
await page.click('header button:has-text("Publicar")');
await page.waitForTimeout(600);
await page.screenshot({ path: SHOTS + 'full-publish-dialog.png' });
await page.locator('role=dialog >> button:has-text("Publicar")').click();
await page.waitForTimeout(1500);
await pub.reload({ waitUntil: 'networkidle' });
const pubSizeAfter = await pub.locator('h1').first().evaluate((el) => getComputedStyle(el).fontSize);
check('26 Publicar cambios', pubSizeAfter === '82px', pubSizeAfter);
const editorLeftovers = await pub.locator('.sn-media-empty, .sn-slot-empty, .sn-placeholder, .studio-overlay, [data-editing]').count();
const publicFont = await pub.locator('h1').first().evaluate((el) => getComputedStyle(el).fontFamily);
check('28 Resultado real en el sitio', editorLeftovers === 0 && publicFont.includes('Playfair'), `restos del editor=${editorLeftovers}, fuente=${publicFont.split(',')[0]}`);

// 27 Restaurar versión anterior
await page.locator('button[aria-label="Más opciones"]').click();
await page.locator('text=Historial de versiones').click();
await page.waitForTimeout(500);
await page.screenshot({ path: SHOTS + 'full-versions.png' });
await page.locator('li:has-text("Versión 1") button:has-text("Restaurar")').click();
await page.waitForTimeout(1500);
check('27a Restaurar versión en borrador', (await css(heading(), 'fontSize')) !== '82px', await css(heading(), 'fontSize'));
await page.click('header button:has-text("Publicar")');
await page.waitForTimeout(500);
await page.locator('role=dialog >> button:has-text("Publicar")').click();
await page.waitForTimeout(1500);
await pub.reload({ waitUntil: 'networkidle' });
check('27b Restaurada y publicada en el sitio', (await pub.locator('h1').first().evaluate((el) => getComputedStyle(el).fontSize)) !== '82px');

} catch (e) {
    results.push('ABORT ' + e.message.split('\n')[0]);
    await page.screenshot({ path: SHOTS + 'full-abort.png' });
}
console.log(results.join('\n'));
console.log(errors.length ? '\nERRORES:\n' + errors.join('\n') : '\nsin errores de consola');
await b.close();
process.exitCode = results.some((r) => !r.startsWith('PASS')) || errors.length ? 1 : 0;
