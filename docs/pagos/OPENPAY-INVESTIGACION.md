# Openpay México: investigación técnica para cobros con tarjeta (Laravel / PHP 8.4 + Vue)

Fecha de investigación: 2026-10-27.

**Método.** Los datos salen de la documentación oficial vigente. El sitio `documents.openpay.mx` es una SPA de Nuxt, así que el contenido de cada guía se extrajo de los bundles JS que sirve el propio sitio. La referencia del API es un HTML estático (Slate) en `https://documents.openpay.mx/docs/api/index.html`. Cada afirmación marcada como **[V]** está verificada en la URL citada. Lo marcado como **[S]** es supuesto o recomendación propia y se resume al final.

Fuentes principales:

| Clave     | URL                                                                                                  |
| --------- | ---------------------------------------------------------------------------------------------------- |
| API       | https://documents.openpay.mx/docs/api/ (contenido: https://documents.openpay.mx/docs/api/index.html) |
| INTRO     | https://documents.openpay.mx/docs/introduction                                                       |
| JS        | https://documents.openpay.mx/docs/openpay-js                                                         |
| FRAUDE    | https://documents.openpay.mx/docs/fraud-tool                                                         |
| CARGO     | https://documents.openpay.mx/docs/card-charge                                                        |
| 3DS       | https://documents.openpay.mx/docs/three-d-secure                                                     |
| SELECTIVA | https://documents.openpay.mx/docs/selective-auth                                                     |
| WEBHOOKS  | https://documents.openpay.mx/docs/webhooks                                                           |
| PRUEBAS   | https://documents.openpay.mx/docs/testing                                                            |
| ERRORES   | https://documents.openpay.mx/docs/errors                                                             |
| VERSIONES | https://documents.openpay.mx/docs/versions                                                           |
| SDK       | https://github.com/open-pay/openpay-php · https://packagist.org/packages/openpay/sdk                 |

---

## 1. URLs base, autenticación y merchant id

Fuentes: API `#api-endpoints`, `#autenticaci-n`; INTRO.

- **[V]** URI base de sandbox: `https://sandbox-api.openpay.mx`. Producción: `https://api.openpay.mx`.
- **[V]** Dashboards: `https://sandbox-dashboard.openpay.mx` y `https://dashboard.openpay.mx`.
- **[V]** Un endpoint se forma con la URI base, `/v1/{MERCHANT_ID}` y el recurso. Ejemplo: `POST https://sandbox-api.openpay.mx/v1/mzdtln0bmtms6o3kck8f/customers`.
- **[V]** Hay recursos a nivel comercio (`/v1/{MERCHANT_ID}/charges`, `/tokens`, `/cards`, `/customers`, `/webhooks`…) y a nivel cliente (`/v1/{MERCHANT_ID}/customers/{CUSTOMER_ID}/charges`, …).
- **[V]** La autenticación es HTTP Basic. Cita: "la llave de API es el nombre de usuario. La contraseña no es requerida y debe dejarse en blanco". En curl: `-u sk_xxx:`.
- **[V]** "Por razones de seguridad todas las peticiones deben ser vía **HTTPS**."
- **[V]** Cada ambiente tiene dos llaves:
    - **Llave pública** (`pk_…`): solo para front-end y solo crea tokens.
    - **Llave privada** (`sk_…`): solo en back-end.
- **[V]** Las credenciales de producción son distintas y se generan cuando se aprueba la solicitud de producción.
- **[V]** Usar la llave pública para algo que requiere la privada, o la privada desde JavaScript, devuelve el error `1010` (HTTP 403).
- **[V]** El versionado va en la URL (`/v1`). Openpay considera compatibles estos cambios, y tu integración debe tolerarlos (VERSIONES):
    - agregar atributos opcionales a las peticiones;
    - agregar atributos a las respuestas y a los webhooks;
    - cambiar el orden de los atributos;
    - cambiar el formato de los IDs (longitud de hasta 255);
    - agregar tipos de webhook nuevos.

## 2. Tokenización en el frontend (Openpay.js + openpay-data.js)

Fuentes: JS, FRAUDE, CARGO, API `#tokens`.

**[V] Scripts oficiales**, ambos responden 200 al 2026-10-27:

```html
<script
    type="text/javascript"
    src="https://js.openpay.mx/openpay.v1.min.js"
></script>
<script
    type="text/javascript"
    src="https://js.openpay.mx/openpay-data.v1.min.js"
></script>
```

- Las guías JS, FRAUDE y CARGO usan estas URLs "v1" sin número de parche.
- La guía de botón de pago (`/docs/boton-de-pago`) usa además una URL versionada: `https://resources.openpay.mx/lib/openpay-js/1.2.39/openpay.v1.min.js`, que también responde 200.
- No encontré la equivalente versionada de `openpay-data` (la ruta análoga devuelve 403).
- **[S]** Usar las URLs de `js.openpay.mx`, que son las documentadas.

**[V] Configuración:**

```js
OpenPay.setId('MERCHANT_ID');
OpenPay.setApiKey('PUBLIC_API_KEY'); // nunca la privada
OpenPay.setSandboxMode(true); // OpenPay.getSandboxMode() para consultarlo
```

**[V] Device session, de FRAUDE:**

- `var deviceSessionId = OpenPay.deviceData.setup("formId", "deviceIdHiddenFieldName");` crea un hidden en el form con ese nombre.
- Para manejarlo manualmente (por ejemplo, AJAX o Vue): `var deviceSessionId = OpenPay.deviceData.setup();`.
- Nota oficial: "openpay-data.js depende de la libreria openpay.js. Cuida ejecutar primero el metodo setSandboxMode() … y despues el metodo setup."
- En el API, `device_session_id` es "string (requerido, longitud = 32)".

**[V] Creación del token.** Hay dos formas:

- `OpenPay.token.create(params, success, error)`, con un objeto JS:
  `{card_number, holder_name, expiration_year, expiration_month, cvv2, address?}`.
- `OpenPay.token.extractFormAndCreate(formOrId, success, error)`. Lee los inputs marcados con los atributos `data-openpay-card` (tarjeta) y `data-openpay-card-address` (dirección).
    - La guía CARGO recomienda **no** poner `name` en los inputs de tarjeta. Así los datos no viajan en el submit a tu servidor.

**[V] Campos del token** (API `#crear-un-nuevo-token`):

| Campo              | Tipo      | Notas                                                                     |
| ------------------ | --------- | ------------------------------------------------------------------------- |
| `holder_name`      | requerido |                                                                           |
| `card_number`      | requerido | 16 o 19 dígitos                                                           |
| `cvv2`             | requerido |                                                                           |
| `expiration_month` | requerido |                                                                           |
| `expiration_year`  | requerido | 2 dígitos en los ejemplos, p. ej. `"20"`                                  |
| `address`          | opcional  | `line1`, `line2`, `line3`, `city`, `state`, `postal_code`, `country_code` |

**[V] Respuesta en el callback** (JS):

- Forma general: `{ status, message, data }`.
    - `status`: HTTP status; vale 0 si el error ocurrió antes de enviar la petición.
    - `message`: solo en error. Valores: "Unknown error", "Request error", "Response error (Unknown final status)", "Empty or invalid OpenPay ID", "Empty or invalid API Key", "Browser error", "Timeout after X milliseconds".
    - `data`: en éxito, el objeto token; en error, el objeto Error.
- Objeto token: `{ id, card: { card_number (enmascarado), holder_name, expiration_year, expiration_month, address, creation_date, brand, points_card } }`. En el back-end se usa `response.data.id`.

**[V] Características del token** (API `#tokens`):

- Se crea a nivel comercio.
- No se liga a ningún cliente.
- "Después de crearse solo se puede usar una sola vez".

**[V] Validadores disponibles:**

- `OpenPay.card.validateCardNumber()`, `validateCVC(cvc[, number])`, `validateExpiry(mm, yyyy)` y `cardType()`.
- El ejemplo oficial dice que `validateCardNumber('378282246310005')` devuelve FALSE: "Número de tarjeta válido pero no aceptado por OpenPay (AMEX)". Ver "Supuestos".

## 3. Crear un cargo con token desde el servidor

Fuente: API `#con-id-de-tarjeta-o-token`, `#objeto-transacci-n`, `#objeto-transaction-status`; CARGO.

**[V] Endpoints:**

- **Sin cliente** (nivel comercio): `POST /v1/{MERCHANT_ID}/charges`.
- **Con cliente** previamente creado: `POST /v1/{MERCHANT_ID}/customers/{CUSTOMER_ID}/charges`.

**[V] Cuerpo de la petición:**

| Campo               | Tipo / regla oficial                                                                                                                                                                                                                                                                                             |
| ------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `method`            | requerido, `"card"`                                                                                                                                                                                                                                                                                              |
| `source_id`         | requerido (longitud 45). Id del token o de la tarjeta guardada                                                                                                                                                                                                                                                   |
| `amount`            | requerido. "mayor a cero, con hasta dos dígitos decimales"                                                                                                                                                                                                                                                       |
| `currency`          | opcional. Solo `MXN` y `USD`                                                                                                                                                                                                                                                                                     |
| `description`       | requerido (longitud 250)                                                                                                                                                                                                                                                                                         |
| `order_id`          | opcional (longitud 100). "Debe ser único entre todas las transacciones." Si se repite: error `1006` HTTP 409 "The order_id has already been processed"                                                                                                                                                           |
| `device_session_id` | requerido (longitud 32)                                                                                                                                                                                                                                                                                          |
| `customer`          | objeto, marcado **requerido** en el cargo a nivel comercio. Usa los mismos campos que al crear cliente (`name` requerido, `email` requerido, `last_name` y `phone_number` opcionales, `address` opcional), pero "no se creará una cuenta al cliente". "Solo se puede utilizar creando el cargo a nivel comercio" |
| `capture`           | opcional, default `true`. Con `false` es preautorización; se confirma después con `POST …/charges/{id}/capture` por un monto menor o igual                                                                                                                                                                       |
| `payment_plan`      | opcional, para meses sin intereses                                                                                                                                                                                                                                                                               |
| `metadata`          | opcional, campos antifraude personalizados (requiere activación con soporte)                                                                                                                                                                                                                                     |
| `use_card_points`   | opcional: `NONE` / `ONLY_POINTS` / `MIXED`                                                                                                                                                                                                                                                                       |
| `use_3d_secure`     | opcional, `true` para 3DS                                                                                                                                                                                                                                                                                        |
| `redirect_url`      | "Nota: Este parámetro solo se puede utilizar si se especifica el manejo de 3D Secure."                                                                                                                                                                                                                           |

**[V] Otros detalles del cargo:**

- El ejemplo curl incluye un objeto `http_context`: `ip`, `browser`, `domain`, `browser_screen_height`, `browser_screen_width`, `browser_user_agent`, `browser_language`, `browser_java_enabled`, `browser_javascript_enabled`. **No aparece en la tabla de parámetros.** Ver "Supuestos".
- Errores de monto en la guía ERRORES:
    - `1020`: "The number of decimal digits is not valid for this currency".
    - `1012`: monto fuera de los límites permitidos.
- **[S]** Enviar `amount` como número con a lo sumo 2 decimales (p. ej. `round($x, 2)`) y manejar internamente el monto en centavos (enteros).

**[V] Respuesta: objeto Transacción.** Campos del ejemplo de cargo:

```
id, amount, authorization, method ("card"), operation_type ("in"), transaction_type ("charge"),
card{ id?, type, brand, address, card_number (enmascarado), holder_name, expiration_year,
      expiration_month, allows_charges, allows_payouts, creation_date, bank_name, bank_code },
status, conciliated, currency, creation_date, operation_date, description,
error_message, order_id, customer_id, payment_method{type,url} (solo en redirect/3DS)
```

- **[V]** Enum de `status` (Transaction Status): `IN_PROGRESS`, `COMPLETED`, `REFUNDED`, `CHARGEBACK_PENDING`, `CHARGEBACK_ACCEPTED`, `CHARGEBACK_ADJUSTMENT`, `CHARGE_PENDING`, `CANCELLED`, `FAILED`.
    - En el JSON aparecen en minúsculas: `completed`, `charge_pending`, `in_progress`, etc.
    - La tabla del objeto Transacción lista solo `completed`, `in_progress` y `failed`. El enum completo está en `#objeto-transaction-status`.
- **[V]** `error_message` contiene la razón cuando `status = failed`.
- **[V]** Un cargo rechazado en línea normalmente **no** devuelve una transacción. Devuelve un **objeto Error** con HTTP 4xx:

```json
{
    "category": "request|internal|gateway",
    "description": "...",
    "http_code": 402,
    "error_code": 3001,
    "request_id": "...",
    "fraud_rules": ["..."]
}
```

## 4. 3D Secure

Fuentes: 3DS, SELECTIVA, API `#con-id-de-tarjeta-o-token`, `#con-redireccionamiento`.

**[V] Petición.** El mismo `POST /v1/{MERCHANT_ID}/charges` (o el de cliente) con:

```json
{
    "method": "card",
    "source_id": "<token>",
    "amount": 6000.0,
    "description": "...",
    "order_id": "...",
    "use_3d_secure": "true",
    "redirect_url": "https://tu-sitio/pagos/retorno"
}
```

- La tabla de la guía 3DS marca `use_3d_secure` como "Boolean (requerido)" y `redirect_url` como "String (opcional)".
- En esa misma tabla, `customer` aparece como opcional.
- El ejemplo de petición 3DS no incluye `device_session_id`. **[S]** Enviarlo de todos modos.

**[V] Respuesta y flujo:**

1. La respuesta trae `status: "charge_pending"` y `payment_method: { "type": "redirect", "url": "https://api.openpay.mx/v1/{merchant}/charges/{id}/redirect/" }`.
2. El comercio guarda el `id` y redirige al cliente a `payment_method.url`.
3. El cliente se autentica con su banco.
4. Openpay redirige a `redirect_url?id={transaction_id}`. Ejemplo oficial: `http://www.openpay.mx/index.html?id=treqwygvw0hrjuvwbsf5`.
5. "Confirmación de Transacción. – El comercio mediante el ID obtiene estado de la transacción", es decir, `GET /v1/{MERCHANT_ID}/charges/{id}`.

**[V] Estados y errores relevantes:**

- Inicial: `charge_pending`.
- Final: `completed` o `failed`; `cancelled` existe en el enum.
- Error `2010`: "3D Secure authentication failed" (HTTP 402).

**[V] Autenticación selectiva.** Primero se intenta el cargo normal con token. Si responde `3005` (rechazo antifraude), se reintenta con 3DS. La guía 3DS dice que "se puede usar el mismo token creado" tras un 3005. Tarjeta de prueba: `5454545454545454`.

**[V] Política de obligatoriedad: no la encontré.** Ninguna página oficial de documents.openpay.mx establece cuándo 3DS es obligatorio. Una fuente de terceros (no oficial) menciona un umbral de 10,000 MXN, sin verificar. Ver "Supuestos".

## 5. Antifraude

Fuentes: API `#cargos`, FRAUDE.

- **[V] Cita textual** (API, sección Cargos, "Cambios por disposiciones oficiales para la prevención de fraude E-commerce"):
    > "Toda integración que se realice mediante SDK o peticiones directas al API de Openpay, es de carácter obligatorio enviar el header X-Forwarded-For donde se deberá indicar la IP del dispositivo del cliente."
    - El ejemplo curl usa `-H "X-Forwarded-For: 129.18.168.184"`.
    - El SDK PHP lo envía con el 4º parámetro de `Openpay::getInstance($id, $sk, 'MX', $ip)`.
- **[V]** `device_session_id` es requerido. Lo genera openpay-data.js; con apps móviles lo generan los SDK Android/iOS.
- **[V]** La herramienta evalúa número de tarjeta, dirección de la tarjeta, correo electrónico e información del dispositivo.
- **[V]** Respuesta ante fraude: error `3005`, categoría `gateway`, HTTP 402. FRAUDE: "No hay otra opción mas que decirle al cliente que vuelva a intentar el cargo con información de otra tarjeta." (Alternativa: autenticación selectiva con 3DS, sección 4.)
- **[V]** Campos recomendados:
    - el `customer` completo (`name`, `last_name`, `email`, `phone_number`);
    - `metadata` con campos personalizados (requiere activación con soporte);
    - `address` en el token;
    - el ejemplo incluye `http_context`.
- **[S]** En Laravel, tomar la IP de `$request->ip()` con `TrustProxies` bien configurado si hay balanceador o Cloudflare.

## 6. Consultar un cargo

Fuente: API `#obtener-un-cargo`, `#listado-de-cargos`.

- **[V]** `GET /v1/{MERCHANT_ID}/charges/{TRANSACTION_ID}`. La versión de cliente es `…/customers/{CUSTOMER_ID}/charges/{TRANSACTION_ID}`.
- **[V] Búsqueda por `order_id`:** sí, con el listado `GET /v1/{MERCHANT_ID}/charges?order_id=XXX`.
    - El listado devuelve un arreglo en orden descendente por fecha de creación.
    - Otros filtros: `creation`, `creation[gte]`, `creation[lte]` (yyyy-mm-dd), `offset` (default 0), `limit` (default 10), `amount`, `amount[gte]`, `amount[lte]` y `status`.
    - No existe un `GET` directo por `order_id`.
- **[S]** Usar la búsqueda por `order_id` para conciliar cuando la llamada de creación sufra timeout y no tengamos el `id` (idempotencia de facto, combinada con el error 1006).

## 7. Reembolsos

Fuente: API `#devolver-un-cargo`; VERSIONES (changelog).

- **[V]** `POST /v1/{MERCHANT_ID}/charges/{TRANSACTION_ID}/refund`. Versión de cliente: `…/customers/{CUSTOMER_ID}/charges/{TRANSACTION_ID}/refund`.
- **[V]** Cuerpo:
    - `description`: opcional, 250 caracteres.
    - `amount`: opcional, "mayor a cero y menor o igual al cargo original, con hasta dos dígitos decimales". Si se omite, se reembolsa el total.
- **[V]** "El monto a devolver será por el total del cargo o un monto menor." "la devolución puede tardar en aparecer en el estado de cuenta … de 1 a 3 días hábiles." Solo aplica a cargos con tarjeta.
- **[V]** Changelog 2018-08: "Se permiten reembolsos multiples … tantos como se requieran hasta llegar al total del cargo, es necesario solicitar la activación de la funcionalidad".
- **[V]** Respuesta: el objeto transacción del **cargo original** con un objeto anidado `refund`:
  `{ id, amount, authorization, method, operation_type:"out", transaction_type:"refund", status, currency, creation_date, operation_date, description, error_message, order_id, customer_id }`.
- **[V]** Para cancelar una preautorización (`capture=false`) también se usa refund. API `#confirmar-un-cargo`: "Para cancelar el cargo creado se debe hacer una llamada al método Devolver un cargo".

## 8. Webhooks

Fuentes: WEBHOOKS (guía), API `#webhooks`.

**[V] Registro.** Dos opciones:

- **Dashboard:** Perfil → Webhooks → "+Agregar Webhook" → URL → autenticación (opcional) → Guardar.
- **API:** `POST /v1/{MERCHANT_ID}/webhooks` con `{ url, user, password, event_types[] }`. Para administrarlos: `GET /webhooks/{id}`, `DELETE /webhooks/{id}` y `GET /webhooks` (listado).
- Respuesta: `{ id, url, user, event_types, status: "verified"|"unverified" }`.

**[V] Verificación:**

- "Para que Openpay invoque un webhook, es importante que se encuentre verificado."
- Al registrarlo, Openpay hace un POST con `{ "type":"verification", "event_date":"…", "verification_code":"UY1qqrxw" }`.
- Tu servicio debe guardar ese código y responder **200 OK**. Luego, en el dashboard, se usa "Verificar" para capturar el código. "Reenviar Código" genera uno nuevo.

**[V] Autenticación:** "Actualmente solo se soporta autenticación HTTP Basic" (campos `user`/`password`).

**[V] Firma: no existe.** La documentación oficial no menciona ningún header de firma ni HMAC. **Recomendación [S]:**

1. Exigir HTTP Basic con credenciales fuertes.
2. Tratar el payload como un aviso, no como prueba.
3. Re-consultar siempre `GET /v1/{MERCHANT_ID}/charges/{transaction.id}` con la llave privada y actuar solo con ese resultado.
4. Hacer el procesamiento idempotente por `transaction.id` + `type`.

**[V] Reintentos:**

- "Openpay intentará entregar la notificación hasta recibir una respuesta de éxito. Esto puede causar que algunas notificaciones se envíen dos veces."
- "Los Webhook deberán regresar un estado HTTP 200 OK siempre que reciban una notificación, de otra manera Openpay reintentará el envío continuamente."
- No se documentan intervalos ni número máximo de reintentos.

**[V] Payload:**

- Campos: `{ type, event_date, transaction{…objeto Transacción…} }`. Para `verification` llega `verification_code` en lugar de `transaction`.
- "Openpay puede agregar más campos en un futuro … tu Webhook pueda manejar datos adicionales desconocidos" y "aceptar notificaciones de tipos no conocidos".

**[V] Requisitos del endpoint:**

- Solo dominios (no IPs).
- Protocolo HTTPS/TLS 1.2.
- Certificado válido firmado por una CA pública y que coincida con el dominio.
- Puertos: la guía lista 443, 1518, 1519, 8443 y 10443; la referencia del API lista 443, 8443 y 10443. **[S]** Usar 443.
- "en el ambiente de producción es requerido que la URL del servicio pueda ser accesada mediante HTTPS" (sandbox permite HTTP).

**[V] Tipos de evento.** Las dos fuentes oficiales difieren. La referencia del API (`#objeto-webhook`) es la más completa:

| Categoría      | Eventos                                                                                                                                        |
| -------------- | ---------------------------------------------------------------------------------------------------------------------------------------------- |
| Cargos         | `charge.refunded`, `charge.failed`, `charge.cancelled`, `charge.created`, `charge.succeeded`, `charge.rescored.to.decline`                     |
| Suscripción    | `subscription.charge.failed`                                                                                                                   |
| Pagos          | `payout.created`, `payout.succeeded`, `payout.failed`                                                                                          |
| Transferencias | `transfer.succeeded`                                                                                                                           |
| Comisiones     | `fee.succeeded`, `fee.refund.succeeded`                                                                                                        |
| SPEI           | `spei.received`                                                                                                                                |
| Contracargos   | `chargeback.created`, `chargeback.rejected`, `chargeback.accepted`                                                                             |
| Orden          | `order.created`, `order.activated`, `order.payment.received`, `order.completed`, `order.expired`, `order.cancelled`, `order.payment.cancelled` |

- La guía WEBHOOKS agrega `verification` y no lista `charge.failed`, `charge.cancelled`, `charge.rescored.to.decline`, `subscription.*`, `fee.refund.*` ni `order.*`.
- Semántica según la guía: `chargeback.rejected` = "El contracargo se ha ganado a favor del comercio"; `chargeback.accepted` = "El contracargo se ha perdido".
- No existe `verification.*` como evento suscribible; `verification` es el `type` del mensaje de verificación.
- **[S]** Para tarjeta, suscribir: `charge.succeeded`, `charge.failed`, `charge.cancelled`, `charge.refunded`, `charge.rescored.to.decline`, `chargeback.created`, `chargeback.accepted`, `chargeback.rejected`.

**[V] Errores de webhooks:**

- `6001`: "The webhook has already been processed".
- `6002`: "Could not connect with webhook service, verify URL".
- `6003`: el servicio respondió con error.

## 9. Códigos de error

Fuentes: ERRORES (guía, más completa) y API `#c-digos-de-error`.

| Código             | HTTP            | Significado (oficial)                                                            |
| ------------------ | --------------- | -------------------------------------------------------------------------------- |
| 1000               | 500             | Error interno del servidor de Openpay                                            |
| 1001               | 400             | Petición no es JSON, formato incorrecto o faltan campos requeridos               |
| 1002               | 401             | Llamada no autenticada / autenticación incorrecta                                |
| 1003               | 422             | Uno o más parámetros con valor incorrecto                                        |
| 1004               | 503             | Servicio necesario no disponible                                                 |
| 1005               | 404             | Recurso no existe                                                                |
| **1006**           | 409             | **Ya existe una transacción con el mismo ID de orden**                           |
| 1007               | 402             | Transferencia de fondos no aceptada (rechazo del procesador)                     |
| 1008               | 423             | Cuenta desactivada                                                               |
| 1009               | 413             | Cuerpo demasiado grande                                                          |
| 1010               | 403             | Llave pública usada donde se requiere privada, o privada desde JS                |
| 1011               | 404             | Recurso eliminado previamente                                                    |
| 1012               | 412             | Monto fuera de los límites permitidos                                            |
| 1013               | 412             | Operación no permitida para el recurso                                           |
| 1014               | 401             | Cuenta inactiva                                                                  |
| 1015               | 504             | Sin respuesta del gateway                                                        |
| 1016               | 409             | Mail del comercio ya procesado                                                   |
| 1017               | 502             | Gateway no disponible                                                            |
| 1018               | 402             | Número de intentos de cargo mayor al permitido                                   |
| 1020               | 400             | Número de decimales inválido para la moneda                                      |
| 1023               | 412             | Transacciones del paquete agotadas                                               |
| 1024               | 412             | Monto excede límite por TPV                                                      |
| 1025               | 412             | Transacciones CoDi bloqueadas                                                    |
| 2001               | 409             | CLABE ya registrada en el cliente                                                |
| 2002               | 409             | Tarjeta ya registrada en el cliente (solo en la referencia API)                  |
| 2003               | 409             | external_id ya existe                                                            |
| 2004               | 422             | Dígito verificador inválido (Luhn)                                               |
| 2005               | 400             | Tarjeta con fecha de expiración pasada                                           |
| 2006               | 400             | CVV2 no proporcionado                                                            |
| 2007               | 412             | Número de tarjeta de prueba, solo válido en sandbox                              |
| 2008               | 412             | Tarjeta no válida para puntos                                                    |
| 2009               | 412             | CVV2 inválido                                                                    |
| 2010               | 402             | Autenticación 3D Secure fallida                                                  |
| 2011               | 422             | Tipo de tarjeta no soportada                                                     |
| **3001**           | 402             | Tarjeta declinada (por el banco)                                                 |
| **3002**           | 402             | Tarjeta expirada                                                                 |
| **3003**           | 402             | Fondos insuficientes                                                             |
| **3004**           | 402             | Tarjeta robada                                                                   |
| **3005**           | 402             | Rechazada por el sistema antifraude ("Fraud risk detected by anti-fraud system") |
| 3006               | 412             | Operación no permitida para este cliente o transacción                           |
| 3008               | 412             | Tarjeta no soportada en transacciones en línea (solo en la referencia API)       |
| 3009               | 402             | Tarjeta reportada como perdida                                                   |
| 3010               | 402             | Banco restringió la tarjeta                                                      |
| 3011               | 402             | Banco solicita retener la tarjeta                                                |
| 3012               | 412             | Se requiere autorización del banco                                               |
| 3201               | 412             | Comercio no autorizado para meses sin intereses                                  |
| 3203               | 412             | Promoción no válida para el tipo de tarjeta                                      |
| 3204               | 412             | Monto menor al mínimo de la promoción                                            |
| 3205               | 412             | Promoción no permitida                                                           |
| 4001               | 412             | Cuenta Openpay sin fondos suficientes                                            |
| 4002               | 412             | Comisiones pendientes de pago                                                    |
| 6001 / 6002 / 6003 | 409 / 412 / 502 | Webhooks (ver sección 8)                                                         |

- **[V]** El objeto Error tiene `category`, `error_code`, `description`, `http_code`, `request_id` y `fraud_rules` (este último solo en la referencia API).
- **[S]** No mostrar al usuario final el `description` crudo. Mapear códigos a mensajes propios; para 3004, 3005, 3009 y 3011 usar un mensaje genérico de "tarjeta rechazada".

## 10. Tarjetas de prueba (sandbox)

Fuente: PRUEBAS.

- **[V] Vigencia y CVV:** "cualquier fecha de vigencia mayor al mes actual y cualquier código de seguridad de 3 dígitos para VISA, MASTERCARD y uno de 4 dígitos para AMEX". Cualquier otro número de tarjeta devuelve error.
- **[V] Tarjetas aprobadas** (cargo con token, redireccionamiento, 3DS, IVR):

| Número                                               | Marca / Banco                           |
| ---------------------------------------------------- | --------------------------------------- |
| 4111111111111111                                     | Visa / BANAMEX                          |
| 5555555555554444                                     | MasterCard / SANTANDER (acepta puntos)  |
| 5105105105105100                                     | MasterCard / SCOTIABANK (acepta puntos) |
| 345678000000007, 341111111111111, 343434343434343    | American Express                        |
| 5062541600005232, 5064050100000063, 5064510000300020 | Carnet                                  |

- **[V] Tarjetas con error:**

| Número           | Error                     |
| ---------------- | ------------------------- |
| 4222222222222220 | 3001 rechazada            |
| 4000000000000069 | 3002 expirada             |
| 4444444444444448 | 3003 fondos insuficientes |
| 4000000000000119 | 3004 robada               |
| 4000000000000044 | 3005 antifraude           |
| 5454545454545454 | 3005 antifraude           |
| 340000000000009  | 3001 (Amex)               |
| 373737373737374  | 3002 (Amex)               |
| 370000000000002  | 3003 (Amex)               |

- **[V] Autenticación selectiva:** `5454545454545454` (MasterCard HSBC). "Esta tarjeta será aprobada en un cargo con 3D Secure, en cualquier otra modalidad será rechazada por la herramienta antifraude."
- **[V]** En producción, una tarjeta de prueba devuelve el error `2007`.
- **[V]** "El ambiente de sandbox está preparado para enviar las notificaciones [webhooks] tal como si se tratará de un ambiente productivo."

## 11. SDK oficial PHP (`openpay/sdk`, repo open-pay/openpay-php)

Fuentes: https://packagist.org/packages/openpay/sdk (metadatos en https://repo.packagist.org/p2/openpay/sdk.json) · https://github.com/open-pay/openpay-php

- **[V]** Última versión: **3.1.1**, publicada el 2024-10-28. Anteriores: 3.1.0 (2024-06-18) y 3.0.0 (2021-12-03).
- **[V]** Licencia **Apache-2.0**.
- **[V]** `require`: `php >=5.2.1`, `ext-curl`, `ext-hash`. `require-dev`: `phpunit 4.8.*`.
- **[V]** Estado del repo: último push el 2024-10-28, no archivado, 20 issues abiertos (API de GitHub, 2026-10-27).
- **[V]** El README dice "PHP 5.2 or later". No declara soporte explícito de PHP 8.x.
- **[V] Prueba local en PHP 8.4.15:** `php -l` limpio en todos los archivos. Una llamada `charges->get` con llave inválida y `E_ALL` no emitió deprecations ni warnings y lanzó `OpenpayApiAuthError` como se esperaba. Es una prueba superficial, no una suite.
- **[V] Diseño del SDK** (código fuente 3.1.1):
    - Configuración en **estado estático global** (`Openpay::$id`, `$apiKey`, `$publicIp`, `$sandboxMode`).
    - `getInstance($id, $apiKey, $country, $publicIp)`. La IP pública se manda como `X-Forwarded-For`.
    - Producción con `Openpay::setProductionMode(true)`.
    - cURL con timeout de 80 s y `CURLOPT_CAINFO` apuntando a un `cacert.pem` embebido.
    - Valida localmente que los IDs sean "alphanumeric string not longer than 20 characters". Esto choca con VERSIONES, que dice que los IDs pueden crecer hasta 255.
    - Lanza `OpenpayApiTransactionError`, `OpenpayApiRequestError`, `OpenpayApiAuthError` y `OpenpayApiConnectionError`.

**Recomendación [S]: no usar el SDK. Llamar al REST con el cliente `Http` de Laravel.** Motivos:

1. El estado estático global (IP del cliente, llaves) es peligroso con Octane, workers de cola o peticiones concurrentes en el mismo proceso. La IP de un cliente se podría filtrar a la petición de otro.
2. No declara compatibilidad con PHP 8.x, se actualiza poco y usa phpunit 4.8.
3. El `cacert.pem` embebido envejece.
4. La validación local de longitud de IDs puede romperse ante cambios que Openpay declara compatibles.
5. La superficie que necesitamos es pequeña: crear cargo, obtener cargo, listar por `order_id`, reembolsar y, opcionalmente, crear y listar webhooks. Con `Http::withBasicAuth($sk, '')->withHeaders(['X-Forwarded-For' => $ip])->timeout(…)->retry(…)` queda en un servicio tipado, testeable con `Http::fake()` y con control fino de timeouts.

Usar el SDK solo si se necesita rápidamente algo de su superficie extendida (suscripciones, CFDI…).

## 12. Producción y requisitos

Fuentes: INTRO, API, WEBHOOKS, CARGO, JS.

- **[V]** El paso a producción se solicita desde la cuenta sandbox llenando un formulario con datos de la empresa. Openpay lo revisa. Al aprobarse, llega un correo para crear la contraseña del dashboard de producción y se obtienen **credenciales nuevas**. Nota oficial: "ten cuidado a que ambiente estás realizando tus peticiones".
- **[V]** Todas las peticiones al API van por HTTPS.
- **[V]** El webhook en producción debe ser HTTPS/TLS 1.2, con dominio (no IP) y certificado de una CA pública.
- **[V] PCI:** tokenizar con Openpay.js "permite minimizar el alcance de una certificación PCI Compliance". Los datos de tarjeta van directo del navegador a Openpay y no pasan por tu servidor.
- **[V]** `X-Forwarded-For` con la IP del cliente es obligatorio. `device_session_id` es requerido.
- **[V]** La llave privada no debe usarse nunca en JS (error 1010).
- **[V] Lista blanca de IPs:** la documentación no menciona una lista blanca de IPs salientes ni entrantes. **[S]** No asumir IPs fijas de Openpay para filtrar webhooks; usar Basic Auth más re-consulta.

**Checklist [S] para salir a producción:**

- llaves y merchant de producción en `.env`, con el flag de sandbox desactivado tanto en el JS (`setSandboxMode(false)`) como en la URL base del back-end;
- webhook de producción registrado y verificado;
- `TrustProxies` correcto para obtener la IP real;
- `order_id` único por intento y su manejo de 1006;
- páginas de retorno 3DS que confirmen con GET;
- logs sin PAN, CVV ni llaves;
- CSP que permita `js.openpay.mx` y el iframe y las redirecciones 3DS;
- una prueba de cargo real de monto bajo con reembolso.

---

## Supuestos / no verificado

1. **Política de obligatoriedad de 3DS:** no hay página oficial que la defina, por ejemplo por monto o por giro. La única mención de un umbral (10,000 MXN) es de un tercero (Nexio). Confirmar con el ejecutivo o con soporte de Openpay.
2. **`http_context`:** aparece en el ejemplo curl del cargo, pero no en la tabla de parámetros. Es probable que sea opcional y que ayude al antifraude y a 3DS 2.x. No verificado.
3. **`customer` en el cargo sin cliente:** la referencia API lo marca como "requerido" y la guía 3DS como "opcional". **[S]** Enviarlo siempre con `name`, `last_name`, `email` y `phone_number`.
4. **`device_session_id` en 3DS:** el ejemplo 3DS no lo incluye. **[S]** Enviarlo siempre.
5. **AMEX:** Openpay.js afirma en un ejemplo que AMEX "no es aceptado", pero sandbox incluye tarjetas AMEX y el changelog menciona SafeKey para Amex "en modo agregador". La aceptación depende del contrato del comercio. Validar con Openpay.
6. **Reintentos de webhook:** no se documentan intervalos, duración ni número máximo. Tampoco IPs de origen.
7. **Firma de webhooks:** no existe en la documentación oficial (verificado por ausencia). La recomendación de re-consultar el cargo es propia.
8. **Discrepancias entre documentos oficiales:**
    - puertos de webhook (guía: 443/1518/1519/8443/10443; API: 443/8443/10443);
    - catálogo de eventos (sección 8);
    - códigos de error: 2002 y 3008 solo en la API; 1011–1025, 2010, 2011, 32xx, 4002 y 600x solo en la guía.
9. **Estados finales tras 3DS:** la guía no enumera explícitamente qué estado queda si el cliente abandona la autenticación. Se infiere `charge_pending` y luego `cancelled` o `failed` según el enum. **[S]** Tratar `charge_pending` como "pendiente" y expirarlo del lado propio.
10. **Compatibilidad del SDK con PHP 8.4:** solo una prueba de humo local, sin declaración oficial.
11. **Script versionado `resources.openpay.mx/lib/openpay-js/1.2.39/`:** existe y se cita en la guía de botón de pago, pero las guías principales usan `js.openpay.mx/*.v1.min.js`. No hay changelog público de versiones de Openpay.js.
12. **Montos:** no encontré en la documentación MX un mínimo ni un máximo por cargo. Los límites dependen de la cuenta (errores 1012 y 1024).
