import { spawnSync } from 'node:child_process';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

/**
 * Antes de cada corrida: limpia la caché del entorno e2e (contadores de
 * límite de peticiones por IP y transacciones de la pasarela simulada de
 * corridas anteriores). La base se prepara con tests/e2e/setup.sh.
 */
export default function globalSetup(): void {
    const root = path.resolve(
        path.dirname(fileURLToPath(import.meta.url)),
        '../..',
    );
    const result = spawnSync('php', ['artisan', 'cache:clear', '--env=e2e'], {
        cwd: root,
        encoding: 'utf8',
    });

    if (result.status !== 0) {
        throw new Error(
            `No se pudo limpiar la caché e2e: ${result.stdout}${result.stderr}`,
        );
    }
}
