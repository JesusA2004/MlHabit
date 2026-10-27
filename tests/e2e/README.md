# Pruebas E2E de MR. INSIGHT Studio

Recorridos en navegador real (Chromium/Edge vía `playwright-core`) sobre una **base SQLite desechable**. Nunca tocan la base MySQL.

| Script | Qué verifica |
|---|---|
| `studio-acceptance.mjs` | Criterios 1–28: entrar a Studio, estructura real, título (tamaño, color, fuente, posición), imagen (cambiar, encuadre), video (insertar, tamaño), composición, reordenar, agregar/duplicar/ocultar/eliminar, animación, dispositivos, guardar/recargar, vista previa sin publicar, publicar, restaurar versión y resultado público. Aserciones sobre el DOM real. |
| `studio-ipad.mjs` | Criterio 30: operación táctil en 1024×768 y 768×1024. |
| `public-visual.mjs` | Capturas a varios anchos y detección de desbordamiento horizontal. `node public-visual.mjs "tips,mi-historia" "390,768,1024,1440"` |

## Preparación (una vez por corrida)

```bash
# 1) dependencias del navegador (no se agregan a package.json)
npm i --no-save playwright-core@1.48.2

# 2) base desechable + datos de prueba (desde la raíz del proyecto, Git Bash)
export DB_CONNECTION=sqlite DB_DATABASE="$PWD/storage/e2e.sqlite" DB_URL= CACHE_STORE=array QUEUE_CONNECTION=sync APP_URL=http://127.0.0.1:8123
rm -f storage/e2e.sqlite && touch storage/e2e.sqlite
php artisan migrate:fresh --seed --force
php tests/e2e/seed.php          # se niega a correr si DB_CONNECTION no es sqlite

# 3) servidor de pruebas (otra terminal, mismas variables)
php artisan serve --host=127.0.0.1 --port=8123

# 4) ejecutar
npm run build
node tests/e2e/studio-acceptance.mjs
node tests/e2e/studio-ipad.mjs
```

Variables opcionales: `E2E_BASE_URL`, `E2E_BROWSER` (ruta a Chrome/Edge), `E2E_EMAIL`, `E2E_PASSWORD`.

`seed.php` sube imágenes generadas a la colección `e2e-pruebas` (`storage/app/media/e2e-pruebas`); bórrala al terminar. Las capturas quedan en `tests/e2e/screenshots/` (ignoradas por git).

Salida: una línea `PASS`/`FAIL` por criterio; código de salida ≠ 0 si algo falla.
