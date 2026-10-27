#!/usr/bin/env bash
# Prepara la base mlhabit_e2e para las pruebas de navegador (Playwright).
# Uso (Git Bash, desde la raíz del repo):  bash tests/e2e/setup.sh
set -euo pipefail

cd "$(dirname "$0")/../.."

MARIADB="${MARIADB:-/c/wamp64/bin/mariadb/mariadb11.4.9/bin/mariadb.exe}"
DB_PORT="${DB_PORT:-3307}"
E2E_PORT="${E2E_PORT:-8123}"

if [ ! -f .env ]; then
  echo "Falta .env (cópialo de .env.example y ejecuta php artisan key:generate)." >&2
  exit 1
fi

# 1) .env.e2e: copia de .env con los valores del entorno de pruebas.
#    Laravel lo carga cuando APP_ENV=e2e (php artisan ... --env=e2e).
grep -v -E '^(APP_ENV|APP_URL|APP_DEBUG|DB_DATABASE|QUEUE_CONNECTION|MAIL_MAILER|CACHE_STORE|SESSION_DRIVER|CRM_LEAD_SUBMISSIONS_PER_HOUR|OPENPAY_[A-Z0-9_]*)=' .env > .env.e2e
cat >> .env.e2e <<EOF

# ---- Pruebas E2E (generado por tests/e2e/setup.sh; no editar a mano) ----
APP_ENV=e2e
APP_DEBUG=false
APP_URL=http://127.0.0.1:${E2E_PORT}
DB_DATABASE=mlhabit_e2e
QUEUE_CONNECTION=sync
MAIL_MAILER=log
CACHE_STORE=file
SESSION_DRIVER=file
# Las pruebas envían varias solicitudes desde 127.0.0.1.
CRM_LEAD_SUBMISSIONS_PER_HOUR=1000
# Pasarela simulada (App\Payments\Testing\E2eSandboxGateway). NO son credenciales reales:
# merchant y llave publica ficticios para que el formulario se dibuje; openpay.js se
# intercepta en Playwright con un stub (tests/e2e/support/openpay-stub.js).
OPENPAY_E2E_FAKE=true
OPENPAY_MERCHANT_ID=me2etestmerchant
OPENPAY_PUBLIC_KEY=pk_e2e_test_public
OPENPAY_PRIVATE_KEY=
OPENPAY_PRODUCTION=false
OPENPAY_WEBHOOK_USER=e2e-webhook
OPENPAY_WEBHOOK_PASSWORD=e2e-webhook-password
EOF

# 2) Base dedicada.
"$MARIADB" -uroot -P"$DB_PORT" -h127.0.0.1 -e \
  "CREATE DATABASE IF NOT EXISTS mlhabit_e2e CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# 3) Esquema + configuración base + datos TEST.
php artisan migrate:fresh --seed --force --env=e2e
php artisan e2e:seed --env=e2e

# 4) Estado limpio de la pasarela simulada, sesiones y correos registrados.
php artisan cache:clear --env=e2e >/dev/null
rm -f storage/framework/sessions/* 2>/dev/null || true

echo "Listo. Inicia el servidor con:"
echo "  php artisan serve --env=e2e --host=127.0.0.1 --port=${E2E_PORT}"
