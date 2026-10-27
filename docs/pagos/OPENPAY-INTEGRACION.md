# Openpay México — integración en ML Habitat (módulo visits)

Fecha: 2026-10-27. Base técnica: `docs/pagos/OPENPAY-INVESTIGACION.md` (en adelante **INV**). No se usa el SDK PHP (INV §11): todo va por el cliente `Http` de Laravel.

## 1. Piezas

| Pieza                                  | Archivo                                                                                                      |
| -------------------------------------- | ------------------------------------------------------------------------------------------------------------ |
| Contrato de pasarela                   | `app/Payments/PaymentGateway.php` (`#[Bind]` → `OpenpayPaymentGateway`)                                      |
| Cliente REST Openpay                   | `app/Payments/Openpay/OpenpayPaymentGateway.php`                                                             |
| Configuración                          | `app/Payments/Openpay/OpenpayConfig.php` + `config/services.php` (`openpay`)                                 |
| Errores → mensajes seguros             | `app/Payments/Openpay/OpenpayErrorMap.php`                                                                   |
| Redacción de payloads                  | `app/Payments/Openpay/OpenpayRedactor.php`                                                                   |
| Servicio de pagos (contrato EQUIPO.md) | `app/Payments/PaymentService.php`                                                                            |
| Respuesta JSON del cobro               | `app/Payments/ChargeResponse.php`                                                                            |
| Webhook                                | `app/Http/Controllers/Webhooks/OpenpayWebhookController.php` + `app/Jobs/Payments/ProcessOpenpayWebhook.php` |
| Formulario de tarjeta                  | `resources/js/components/payments/OpenpayCardForm.vue` (+ `openpay.ts`)                                      |
| Visitas ↔ pagos                        | `app/Services/Visits/VisitPaymentHandler.php` (listeners en `app/Listeners/Visits`)                          |

## 2. Variables de entorno

| Variable                                            | Uso                                                                                     | ¿Llega al navegador?        |
| --------------------------------------------------- | --------------------------------------------------------------------------------------- | --------------------------- |
| `OPENPAY_MERCHANT_ID`                               | Id del comercio (URL `/v1/{id}`)                                                        | Sí (Openpay.js lo requiere) |
| `OPENPAY_PUBLIC_KEY`                                | `pk_…`, solo crea tokens                                                                | Sí                          |
| `OPENPAY_PRIVATE_KEY`                               | `sk_…`, HTTP Basic del API                                                              | **Nunca**                   |
| `OPENPAY_PRODUCTION`                                | `false` = `sandbox-api.openpay.mx`; `true` = `api.openpay.mx` y `setSandboxMode(false)` | Solo el booleano            |
| `OPENPAY_WEBHOOK_USER` / `OPENPAY_WEBHOOK_PASSWORD` | HTTP Basic que Openpay envía al webhook                                                 | **Nunca**                   |
| `OPENPAY_3DS_MODE`                                  | `auto` (por defecto), `always`, `never`                                                 | No                          |
| `OPENPAY_TIMEOUT`                                   | Segundos por petición (30)                                                              | No                          |

`/admin/openpay` muestra solo configurado/no configurado y los últimos 4 del merchant id.

## 3. Flujos

### 3.1 Checkout de visita

```
/agendar-visita ─ CP ─► POST /agendar-visita/cobertura (CoverageService::acceptsVisits + fechas)
               ─ día ─► GET /agendar-visita/horarios (SlotFinder por técnico de la zona, cupo diario)
               ─ datos + revisión + términos
               ─► POST /agendar-visita  [transacción]
                    lock zona → re-valida política/cobertura/ventana → BookingService::reserve(holdUntil)
                    en el técnico menos cargado → SiteVisit(pending_payment, tarifa/crédito CONGELADOS)
                    → PaymentService::createIntent(visit_fee, idempotency "visit:{uuid}")
               ─► 302 a /agendar-visita/pago/{payment} (firmado, cuenta regresiva del apartado)
```

### 3.2 Cobro con tarjeta

```
Navegador: openpay.v1.min.js + openpay-data.v1.min.js
  setId / setApiKey(pública) / setSandboxMode → deviceData.setup() → device_session_id
  OpenPay.token.create(tarjeta) → token_id      (la tarjeta nunca toca nuestro servidor)
  POST chargeUrl {token_id, device_session_id, attempt_key}
Servidor: PaymentService::chargeCard
  attempt_key repetida ─────────────────────────► devuelve el estado actual (sin cargo)
  pago en processing ─► reconcile() primero (GET por id u order_id)
  lock(payments) → PaymentAttempt n (sending) → payment processing
  POST /v1/{merchant}/charges  order_id = "{order_id}-{n}", X-Forwarded-For = IP del cliente
     completed ─────────► markPaid → afterCommit PaymentConfirmed
     charge_pending+url ─► processing + redirect_url (3DS)
     error 4xx ─────────► failed + mensaje seguro (OpenpayErrorMap)
     3005 y modo auto ──► intento n+1 con use_3d_secure + redirect_url (mismo token, INV §4)
     1006 ──────────────► buscar por order_id y converger (nunca re-cobrar)
     timeout/5xx ───────► attempt unknown, pago processing → conciliar antes de reintentar
```

Un `order_id` por intento: un rechazo no bloquea el reintento con otra tarjeta (1006) y la conciliación por `GET /charges?order_id=` es determinista. `payments.order_id` es la raíz (`MLH-XXXXXXXXXXXX`).

Si un intento no aparece en Openpay tras `unknown_grace_seconds` (120 s) se marca `expired` y se permite otro. Un 3DS sin terminar tras `pending_3ds_minutes` (30) se da por abandonado.

### 3.3 Retorno 3D Secure

`/agendar-visita/pago/{payment}/retorno?id=…` (visitas) o `/pagos/{payment}/retorno` (genérico): **ignora** el `id` de la URL, ejecuta `reconcile()` (GET con llave privada) y redirige al estado firmado, que sondea el backend cada 4 s. Nunca se muestra éxito sin confirmación del backend.

### 3.4 Webhook

```
POST /webhooks/openpay  (sin CSRF, HTTP Basic con hash_equals; 401 si no coincide o no está configurado)
  type=verification → guarda verification_code (visible en /admin/openpay) → 200
  otro → webhook_events (payload redactado, dedupe_key = type:transaction.id:event_date UNIQUE)
         duplicado → 200 {duplicate:true} (si el previo falló, se re-encola)
         nuevo → job ProcessOpenpayWebhook → 200
Job: localiza el pago (provider_transaction_id → intento → order_id "{order_id}-{n}")
     desconocido → status ignored (sin llamar al API)
     conocido → PaymentService::reconcile(payment, txId) → GET /charges/{id} → converge
```

Convergencia: `completed` → pagado (o contracargo ganado si estaba en disputa); `failed/cancelled` → rechazado (si no estaba pagado); `refunded` → registra el reembolso (completa los "por confirmar" o crea uno `remote:` si se hizo desde el dashboard); `chargeback_*` → `disputed`. Un segundo cargo completado para un pago ya pagado se registra como `duplicate_charge_detected` (ActivityLog `pago.cobro_duplicado`) para reembolso manual.

### 3.5 Reembolsos

`PaymentService::refund` (permiso `payments.refund`, clave de idempotencia): bloquea el pago, valida `monto ≤ pagado − reembolsado − pendientes`, crea `Refund(pending)`, `POST /charges/{id}/refund` con monto decimal exacto. Completado → `refunded_amount`, estado `partially_refunded`/`refunded`, `PaymentRefunded` tras commit. Rechazado → `failed`. Timeout → queda `pending` y `payments:reconcile` lo confirma cuando Openpay reporta el cargo como reembolsado. Reembolsos múltiples parciales requieren activación en Openpay (INV §7).

### 3.6 Visita ↔ pago

- `PaymentConfirmed(visit_fee)` → `BookingService::confirm`; si el apartado venció y otro tomó el horario la visita queda `scheduled` con `attention_reason` (aviso urgente al equipo; el dinero no se pierde) → `paid_at`, `VisitCredit` si la política congelada es acreditable (`percent` de la tarifa o `fixed` ≤ tarifa; vigencia en días) → prospecto `visit_paid` (LeadWorkflow, force solo hacia adelante) → correos.
- `PaymentRefunded(visit_fee)` → crédito disponible: se anula si no queda nada pagado o se reduce a lo efectivamente pagado. Crédito ya **aplicado** a un contrato: no se toca el contrato; ActivityLog `visita.credito_aplicado_reembolsado` + aviso urgente a finanzas para ajustar el anticipo.
- Pago fallido: el apartado se conserva hasta su vencimiento para reintentar con otra tarjeta; `visits:expire-holds` concilia los pagos en curso, cancela la intención (ya no se puede cobrar) y libera el horario.

## 4. Comandos (registrar en `routes/console.php`)

```php
Schedule::command('visits:expire-holds')->everyMinute()->withoutOverlapping();
Schedule::command('payments:reconcile')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('visits:send-reminders')->everyFifteenMinutes()->withoutOverlapping();
```

## 5. Alta del webhook en el dashboard

**Sandbox** (`https://sandbox-dashboard.openpay.mx`):

1. Define `OPENPAY_WEBHOOK_USER` y `OPENPAY_WEBHOOK_PASSWORD` (valores largos aleatorios) y despliega.
2. Perfil → Webhooks → **+ Agregar Webhook**. URL: la que muestra `/admin/openpay` (`https://TU-DOMINIO/webhooks/openpay`). Autenticación: los mismos usuario/contraseña.
3. Eventos: `charge.succeeded`, `charge.failed`, `charge.cancelled`, `charge.refunded`, `charge.rescored.to.decline`, `chargeback.created`, `chargeback.accepted`, `chargeback.rejected`.
4. Openpay envía el evento `verification`: copia el código desde `/admin/openpay` y pulsa **Verificar** en el dashboard.
5. Prueba: un cargo con `4111 1111 1111 1111` debe aparecer como "Último webhook" procesado.

**Producción** (`https://dashboard.openpay.mx`): mismos pasos con credenciales de producción; la URL debe ser HTTPS (TLS 1.2, dominio con certificado de CA pública, puerto 443).

## 6. Checklist de salida a producción

- [ ] Solicitud de producción aprobada; `OPENPAY_MERCHANT_ID`, `OPENPAY_PUBLIC_KEY`, `OPENPAY_PRIVATE_KEY` de producción en el servidor; `OPENPAY_PRODUCTION=true`.
- [ ] `/admin/openpay` → "Probar conexión" correcto en producción.
- [ ] Webhook de producción registrado y **verificado**; credenciales Basic nuevas (distintas a sandbox).
- [ ] `TrustProxies` configurado (Cloudflare/balanceador) para que `$request->ip()` sea la IP real (X-Forwarded-For obligatorio, INV §5).
- [ ] CSP permite `https://js.openpay.mx` (scripts), `https://*.openpay.mx` (conexiones/iframe antifraude) y la redirección 3DS al banco.
- [ ] Cola de trabajos activa (webhooks) y scheduler con los 3 comandos.
- [ ] Política de visita activa con la tarifa comercial decidida.
- [ ] Cargo real de monto bajo + reembolso desde `/admin/pagos`.
- [ ] Confirmar con Openpay: política de 3DS obligatoria, aceptación AMEX, reembolsos parciales múltiples activados.
- [ ] Revisar logs: sin PAN, CVV, tokens ni llaves (solo order_id, código HTTP, estado y código de error).

## 7. Verificado vs. supuesto

**Verificado en la documentación oficial (vía INV, sin consultas web adicionales en esta fase):** URLs base y Basic con llave privada (§1); scripts y `deviceData.setup()` después de `setSandboxMode` (§2); campos del cargo, `order_id` único y error 1006 (§3); flujo 3DS `charge_pending` → `payment_method.url` → `redirect_url?id=` → GET (§4); X-Forwarded-For obligatorio y 3005 antifraude (§5); listado por `order_id` (§6); reembolso total/parcial (§7); webhooks sin firma, Basic, verificación, reintentos y catálogo de eventos (§8); códigos de error (§9); tarjetas de prueba (§10).

**Supuestos propios (marcados [S] en INV o decisiones de esta integración):**

1. Modo 3DS por defecto `auto` (autenticación selectiva tras 3005): no existe política oficial de obligatoriedad.
2. `customer` y `device_session_id` se envían siempre, también con 3DS.
3. `order_id` por intento `{order_id}-{n}` (para no chocar con 1006 tras un rechazo).
4. Monto enviado como número JSON con 2 decimales exactos (sin float).
5. Errores 1000/1004/1015/1017 y 5xx se tratan como resultado **incierto** (se concilia, no se reintenta el POST).
6. El estado `refunded` del cargo se interpreta como reembolso total.
7. Un 3DS abandonado se da por terminado tras 30 min sin cambio.
8. No se filtra por IP de origen del webhook (no hay lista oficial).
9. El campo `fee` de la transacción solo se guarda si Openpay lo envía.

## 8. Decisiones comerciales pendientes

- **Tarifa de la visita** ($1,000 o $1,500): no se siembra ninguna política. Sin versión activa, `/agendar-visita` muestra "Por ahora agenda tu visita con un asesor". Se crea y activa en `/admin/visitas/politica`.
- **Crédito abonable** (% o monto fijo y vigencia).
- **Política de cancelación/reembolso**: el texto es libre en la política; el monto a reembolsar lo decide el personal al cancelar (por defecto el total).
- **Política 3DS** (`OPENPAY_3DS_MODE`), a confirmar con Openpay.
- **Apartado de enlaces enviados por el equipo**: 24 h (`VisitController::STAFF_LINK_HOLD_HOURS`).
