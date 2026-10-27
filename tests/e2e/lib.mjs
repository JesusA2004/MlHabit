import { mkdirSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { chromium } from 'playwright-core';

/**
 * Configuración por variables de entorno:
 *   E2E_BASE_URL   (por defecto http://127.0.0.1:8123)
 *   E2E_BROWSER    ruta a Chrome/Edge (por defecto Edge en Windows)
 *   E2E_EMAIL / E2E_PASSWORD  (las del seed.php)
 */
export const BASE = process.env.E2E_BASE_URL ?? 'http://127.0.0.1:8123';
export const SHOTS = fileURLToPath(new URL('./screenshots/', import.meta.url));
mkdirSync(SHOTS, { recursive: true });

export async function launch() {
    return chromium.launch({
        executablePath: process.env.E2E_BROWSER ?? 'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe',
        headless: true,
    });
}

export async function login(page) {
    await page.goto(`${BASE}/login`);
    await page.fill('input[name=email]', process.env.E2E_EMAIL ?? 'e2e@mrinsight.test');
    await page.fill('input[name=password]', process.env.E2E_PASSWORD ?? 'secreto-e2e-123');
    await page.click('button[type=submit]');
    await page.waitForURL(/admin|dashboard/, { timeout: 15000 });
}

export function collectErrors(page, bag, tag) {
    page.on('pageerror', (e) => bag.push(`[${tag}] pageerror: ${e.message}`));
    page.on('console', (m) => {
        if (m.type() === 'error' && !m.text().includes('404')) bag.push(`[${tag}] console: ${m.text()}`);
    });
}
