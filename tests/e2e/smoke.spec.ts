import { expect, test } from '@playwright/test';
import type { Page } from '@playwright/test';
import { collectConsoleErrors } from './support/helpers';

/**
 * Humo del sitio público: corre en escritorio (1440×900) y móvil (390×844).
 */

async function expectNoHorizontalOverflow(page: Page) {
    const overflow = await page.evaluate(
        () =>
            document.documentElement.scrollWidth -
            document.documentElement.clientWidth,
    );
    expect(overflow, 'desbordamiento horizontal en px').toBeLessThanOrEqual(0);
}

const PAGES: { path: string; heading: RegExp }[] = [
    { path: '/', heading: /Muebles pensados para tu espacio/ },
    { path: '/catalogo', heading: /Muebles a la medida para cada espacio/ },
    { path: '/catalogo/cocinas-test', heading: /Cocinas TEST/ },
    {
        path: '/catalogo/cocinas-test/cocina-integral-test',
        heading: /Cocina Integral TEST/,
    },
    { path: '/inspiracion', heading: /Proyectos y propuestas/ },
    { path: '/preguntas-frecuentes', heading: /Preguntas frecuentes/ },
];

test.describe('sitio público', () => {
    for (const { path, heading } of PAGES) {
        test(`renderiza ${path} sin errores ni desbordamiento`, async ({
            page,
        }) => {
            const errors = collectConsoleErrors(page);
            const response = await page.goto(path);
            expect(response?.status()).toBe(200);
            await expect(
                page.getByRole('heading', { level: 1, name: heading }),
            ).toBeVisible();
            await page.waitForLoadState('networkidle');
            await expectNoHorizontalOverflow(page);
            expect(errors).toEqual([]);
        });
    }

    test('portada: hero con CTA "Solicita" que lleva a /solicitar', async ({
        page,
    }) => {
        await page.goto('/');
        const hero = page.getByRole('region', {
            name: /Muebles pensados para tu espacio/,
        });
        await expect(hero.getByRole('heading', { level: 1 })).toBeVisible();
        const cta = hero.getByRole('link', { name: /^Solicita/ });
        await expect(cta).toBeVisible();
        await cta.click();
        await expect(page).toHaveURL(/\/solicitar$/);
    });

    test('catálogo: navega de índice a categoría y producto', async ({
        page,
    }) => {
        await page.goto('/catalogo');
        await page
            .getByRole('main')
            .getByRole('link', { name: /Cocinas TEST/ })
            .first()
            .click();
        await expect(page).toHaveURL(/\/catalogo\/cocinas-test$/);
        await page
            .getByRole('main')
            .getByRole('link', { name: /Cocina Integral TEST/ })
            .first()
            .click();
        await expect(page).toHaveURL(
            /\/catalogo\/cocinas-test\/cocina-integral-test$/,
        );
        await expect(
            page.getByRole('heading', {
                level: 1,
                name: /Cocina Integral TEST/,
            }),
        ).toBeVisible();
    });

    test('404 con marca', async ({ page }) => {
        const errors = collectConsoleErrors(page);
        const response = await page.goto('/esta-pagina-no-existe');
        expect(response?.status()).toBe(404);
        await expect(
            page.getByRole('heading', {
                level: 1,
                name: 'No encontramos esta página',
            }),
        ).toBeVisible();
        await expect(
            page.getByRole('link', { name: /ML Habitat, inicio/ }),
        ).toBeVisible();
        await expect(
            page.getByRole('link', { name: 'Ir al inicio' }),
        ).toBeVisible();
        await expectNoHorizontalOverflow(page);
        // El único error permitido es el propio 404 del documento.
        expect(errors.filter((e) => !/status of 404/.test(e))).toEqual([]);
    });
});
