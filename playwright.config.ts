import { defineConfig, devices } from '@playwright/test';

/**
 * Pruebas de navegador (E2E) de ML Habitat. Ver tests/e2e/README.md.
 * No descarga navegadores: usa Microsoft Edge instalado (channel msedge).
 */
const baseURL = process.env.E2E_BASE_URL ?? 'http://127.0.0.1:8123';
const edgePath =
    process.env.E2E_EDGE_PATH ??
    'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe';

const browser = process.env.E2E_EDGE_PATH
    ? { launchOptions: { executablePath: edgePath } }
    : { channel: 'msedge' as const };

export default defineConfig({
    testDir: 'tests/e2e',
    globalSetup: './tests/e2e/global-setup.ts',
    outputDir: 'test-results/playwright',
    fullyParallel: false,
    workers: 1,
    retries: 0,
    timeout: 90_000,
    expect: { timeout: 10_000 },
    reporter: [
        ['list'],
        [
            'html',
            { outputFolder: 'test-results/playwright-report', open: 'never' },
        ],
    ],
    use: {
        baseURL,
        locale: 'es-MX',
        timezoneId: 'America/Mexico_City',
        screenshot: 'only-on-failure',
        trace: 'retain-on-failure',
        actionTimeout: 15_000,
        navigationTimeout: 30_000,
        ...browser,
    },
    projects: [
        {
            name: 'desktop',
            use: {
                ...devices['Desktop Edge'],
                ...browser,
                viewport: { width: 1440, height: 900 },
            },
        },
        {
            name: 'mobile',
            testMatch: /smoke\.spec\.ts/,
            use: {
                ...browser,
                viewport: { width: 390, height: 844 },
                isMobile: true,
                hasTouch: true,
                deviceScaleFactor: 3,
            },
        },
    ],
});
