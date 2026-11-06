# Guía para activar los cobros con tarjeta (Openpay)

Para: el dueño de ML Habitat. No hace falta saber programar. Si algo no coincide con lo que ves en pantalla, revisa la tabla de problemas al final.

Todo se configura en el panel, en **Administración → Openpay** (`https://TU-DOMINIO/admin/openpay`). Esa página tiene una lista llamada **"Pasos para activar cobros"**: cada paso se marca solo con una palomita verde cuando queda listo. Esta guía sigue el mismo orden.

> **Importante:** las llaves de Openpay son como las llaves de una caja fuerte. Nunca las mandes por WhatsApp ni por correo. Pégalas solo en el panel. La llave privada se guarda cifrada y después solo se muestran sus últimos 4 caracteres.

---

## Paso 1. Crear tu cuenta de Openpay

1. Entra a <https://www.openpay.mx> y elige **Crear cuenta** (o "Regístrate").
2. Captura los datos del negocio y confirma tu correo.
3. Al terminar entras al **dashboard de pruebas (sandbox)**: <https://sandbox-dashboard.openpay.mx>. Ahí todo es de prueba; no se mueve dinero real.

## Paso 2. Copiar tus llaves de prueba (sandbox)

En el dashboard de sandbox busca la sección de **credenciales / llaves de API**. Verás tres datos:

| Dato en Openpay | Cómo se ve        | Dónde va en el panel |
| --------------- | ----------------- | -------------------- |
| ID (comercio)   | `m` + letras      | **ID de comercio**   |
| Llave privada   | empieza con `sk_` | **Llave privada**    |
| Llave pública   | empieza con `pk_` | **Llave pública**    |

## Paso 3. Pegar las llaves en el panel

1. Abre **Administración → Openpay** (necesitas un usuario con permiso de Configuración; el Super administrador lo tiene).
2. En **Credenciales de Openpay**:
    - Deja **Ambiente de producción** apagado (sandbox).
    - Pega el **ID de comercio**, la **llave pública** y la **llave privada**.
    - **3D Secure:** deja **Automático (recomendado)**. Solo pide la autenticación del banco cuando el antifraude de Openpay lo necesita.
3. En la misma sección, en **Usuario del webhook** y **Contraseña del webhook**, pulsa el botón de la varita para generar valores seguros. **Copia los dos** en un lugar seguro (los necesitas en el paso 5; la contraseña ya no se vuelve a mostrar).
4. Pulsa **Guardar credenciales**.
5. Pulsa **Probar conexión** (arriba a la derecha). Debe decir _"Conexión correcta: Openpay aceptó las credenciales."_ Esta prueba solo consulta; no cobra nada.

> Lo que guardas en el panel tiene prioridad sobre el archivo `.env` del servidor. Si algún día quieres volver a usar solo el `.env`, usa **Usar solo el .env**.

## Paso 4. Precio de la asesoría y videollamadas

En **Administración → Configuración → Asesorías**:

- **Precio de la asesoría (MXN, total):** es exactamente lo que se cobra en la pantalla de pago. No se le suma IVA ni comisiones. Si facturas con IVA, escribe el precio con IVA incluido.
- Cada cita "congela" el precio cuando el cliente aparta: si cambias el precio, solo aplica a reservas nuevas.
- **Enlace de las videollamadas:**
    - **Automático con Jitsi** (recomendado): al confirmarse el pago se crea una sala única `https://meet.jit.si/MLHabitat-…` y el cliente la recibe por correo y en su pantalla de confirmación.
    - **Manual:** el asesor pega el enlace (Meet, Zoom, Teams) en cada asesoría.
- En cualquier caso, desde **Asesorías → detalle** puedes pegar otro enlace o generar uno nuevo de Jitsi.
- Nota de Jitsi: el **asesor** debe entrar primero a la sala e iniciar sesión con Google, GitHub o Facebook (requisito de meet.jit.si). El cliente entra sin cuenta.

El precio de la **visita técnica** se define aparte, en **Administración → Visitas → Política**.

## Paso 5. Registrar el webhook (los avisos de Openpay)

El webhook es la dirección a la que Openpay nos avisa de pagos, reembolsos y contracargos.

1. En el panel (**Openpay → Webhook**) copia la **URL para registrar** con el botón de copiar. Se ve así: `https://TU-DOMINIO/webhooks/openpay`.
2. En el dashboard de Openpay ve a **Perfil → Webhooks → Agregar webhook**.
3. Pega la URL, activa la **autenticación básica** y escribe el mismo **usuario** y **contraseña** del paso 3.
4. Marca estos eventos: `charge.succeeded`, `charge.failed`, `charge.cancelled`, `charge.refunded`, `charge.rescored.to.decline`, `chargeback.created`, `chargeback.accepted`, `chargeback.rejected`.
5. Guarda. Openpay manda un **código de verificación**: recarga la página del panel, aparecerá en **Código de verificación**. Cópialo, pégalo en Openpay y pulsa **Verificar**.
6. El paso "Verifica el webhook" se marca en verde cuando llega el primer aviso real (por ejemplo, tras el pago de prueba del paso 6).

> Para registrar el webhook el sitio debe estar publicado en internet. En una computadora local (`http://localhost`) Openpay no puede llegar.

## Paso 6. Hacer pagos de prueba

1. Abre el sitio como si fueras cliente y agenda una asesoría (o una visita técnica).
2. En la pantalla de pago usa una tarjeta de prueba (también están en el panel, sección **Tarjetas de prueba**). Vencimiento: cualquier fecha futura. CVV: cualquier número de 3 dígitos (4 en American Express).

| Tarjeta             | Resultado                                |
| ------------------- | ---------------------------------------- |
| 4111 1111 1111 1111 | Aprobada (Visa)                          |
| 5555 5555 5555 4444 | Aprobada (Mastercard)                    |
| 4222 2222 2222 2220 | Rechazada por el banco                   |
| 4444 4444 4444 4448 | Fondos insuficientes                     |
| 5454 5454 5454 5454 | Pide autenticación del banco (3D Secure) |

3. Revisa que:
    - Aparezca la pantalla **"¡Listo! Tu asesoría está confirmada"** con folio, fecha y hora, asesor, monto pagado, últimos 4 de la tarjeta, botones de **Agregar a mi calendario** y **Ver comprobante**.
    - Llegue el correo de confirmación con el archivo de calendario adjunto y, en videollamadas, el enlace de Jitsi.
    - La asesoría aparezca como **Confirmada** en **Administración → Asesorías** y el pago como **Pagado** en **Administración → Pagos**.
4. Prueba también una tarjeta rechazada: debe verse un mensaje claro y el botón **Intentar de nuevo** mientras el horario siga apartado (15 minutos).

## Paso 7. Requisitos del servidor (pídelos a quien administra tu hosting)

Sin esto los pagos se cobran, pero los avisos, correos y la conciliación automática no funcionan.

1. **HTTPS obligatorio** en producción: dominio con certificado válido (por ejemplo, Let's Encrypt). Openpay rechaza webhooks sin HTTPS.
2. **Cola de trabajos** (procesa webhooks y correos). En el `.env` del servidor: `QUEUE_CONNECTION=database`, y un proceso que siempre esté corriendo:
    ```
    php artisan queue:work --tries=3 --timeout=90
    ```
    Lo ideal es dejarlo con **Supervisor** para que se reinicie solo. Si el hosting no lo permite (hosting compartido), un cron cada minuto:
    ```
    * * * * * cd /ruta/al/sitio && php artisan queue:work --stop-when-empty --max-time=55 >> /dev/null 2>&1
    ```
3. **Tareas programadas** (vencen apartados, concilian pagos, mandan recordatorios). Un cron cada minuto:
    ```
    * * * * * cd /ruta/al/sitio && php artisan schedule:run >> /dev/null 2>&1
    ```
4. Después de cada cambio de código en el servidor: `php artisan config:cache` y `php artisan queue:restart`.

El panel te avisa: los pasos **"Cola de trabajos en marcha"** y **"Tareas programadas en marcha"** se ponen en verde cuando detectan que funcionan.

## Paso 8. Solicitar producción en Openpay

1. En el dashboard de sandbox busca la opción para **solicitar la cuenta productiva** (o "pasar a producción") y envía la documentación que te pidan (identificación, comprobante de domicilio, datos bancarios, acta o constancia fiscal según tu régimen).
2. Openpay revisa y te avisa por correo. Pregunta en ese momento:
    - si exigen 3D Secure para tu giro o a partir de cierto monto;
    - si aceptas American Express;
    - que te activen **reembolsos parciales**.

## Paso 9. Cambiar a producción

1. Entra al **dashboard de producción**: <https://dashboard.openpay.mx>. Copia ahí el **ID**, la **llave privada** y la **llave pública** de producción (son distintas a las de sandbox).
2. En el panel (**Openpay → Credenciales**): enciende **Ambiente de producción**, pega las tres llaves nuevas (usa **Reemplazar** en la llave privada) y genera un **usuario y contraseña nuevos** para el webhook. Guarda.
3. Pulsa **Probar conexión**: debe salir correcta en producción.
4. Repite el **paso 5** en el dashboard de **producción** (el webhook de sandbox no sirve en producción).
5. Haz un cobro real de monto bajo con tu propia tarjeta y reembólsalo desde **Administración → Pagos → detalle → Reembolsar**.
6. Las tarjetas de prueba ya no funcionan en producción (Openpay responde con error 2007).

---

## Problemas frecuentes

| Lo que ves                                                                     | Qué significa                                                         | Qué hacer                                                                                                                                       |
| ------------------------------------------------------------------------------ | --------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------- |
| "Credenciales rechazadas" al probar conexión                                   | La llave privada no corresponde al ID o al ambiente                   | Revisa que las tres llaves sean del mismo ambiente (sandbox con sandbox, producción con producción) y que el interruptor de producción coincida |
| "El merchant id no existe en este ambiente"                                    | ID de sandbox con interruptor en producción (o al revés)              | Corrige el interruptor de producción o el ID                                                                                                    |
| "Se configuró una llave pública donde se requiere la privada"                  | Se pegó la `pk_` en el campo de la privada                            | Pega la `sk_` en **Llave privada**                                                                                                              |
| La pantalla de pago dice que los pagos con tarjeta no están disponibles        | Faltan llaves                                                         | Completa el paso 3                                                                                                                              |
| No aparece el código de verificación del webhook                               | Openpay no pudo llegar al sitio, o el usuario/contraseña no coinciden | Revisa que el sitio esté publicado con HTTPS, que la URL sea exacta y que el usuario y la contraseña sean idénticos en ambos lados              |
| "Hay N trabajos esperando más de 5 minutos"                                    | La cola no está corriendo                                             | Arranca `php artisan queue:work` (paso 7.2)                                                                                                     |
| "Aún no detectamos el programador"                                             | El cron de `schedule:run` no está                                     | Agrega el cron del paso 7.3                                                                                                                     |
| El cliente pagó pero la página dice "Estamos confirmando tu pago" mucho tiempo | El banco tardó (3D Secure) o se perdió el aviso                       | No cobres de nuevo. La conciliación automática (cada 5 min) lo resuelve; también puedes pulsar **Conciliar** en **Pagos → detalle**             |
| La videollamada no abre o pide iniciar sesión                                  | meet.jit.si pide que el primer participante (el asesor) inicie sesión | El asesor entra primero con Google/GitHub; si prefieres otra plataforma, cambia a **Manual** y pega tu enlace                                   |
| Hay credenciales que "ya no se pueden leer"                                    | Se cambió la `APP_KEY` del servidor                                   | Vuelve a capturar las llaves en el panel                                                                                                        |
| El correo de confirmación no llega                                             | Cola detenida o correo del servidor mal configurado                   | Revisa el paso 7.2 y la configuración de correo (`MAIL_*`)                                                                                      |

Detalles técnicos para el programador: `docs/pagos/OPENPAY-INTEGRACION.md` y `docs/pagos/OPENPAY-INVESTIGACION.md`.
