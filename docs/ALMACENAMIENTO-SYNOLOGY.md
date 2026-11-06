# Almacenamiento de fotos y videos: servidor y Synology

Guía para el dueño y el administrador de ML Hábitat. Explica dónde se guardan
las fotos, renders, videos y documentos, qué pasa si el NAS Synology no
responde y cómo mover la multimedia del servidor al Synology (o de regreso).

Todo se maneja desde **Panel → Almacenamiento** (`/admin/almacenamiento`).
Solo pueden entrar personas con el permiso «Configuración del sistema»
(`settings.manage`).

---

## Respuesta corta

- **¿Si el Synology no se conecta, se sube al servidor?** Sí. Si el Synology
  está activo pero no responde, el archivo se guarda en el servidor para que
  nadie pierda su trabajo. Queda marcado como «esperando subir al Synology» y
  se avisa a los administradores **una sola vez** (correo + campana de
  notificaciones).
- **¿En cuanto conecte se pasa la información al Synology?** Sí, de dos
  formas:
  1. Lo que quedó pendiente por una falla se sube **solo**: la plataforma
     revisa cada 10 minutos y también al pulsar «Probar conexión» o
     «Sincronizar ahora».
  2. Lo que **ya existía** en el servidor se mueve con el botón **«Mover
     multimedia del servidor al Synology»**. Cada archivo se copia, se
     verifica y solo entonces cambia de lugar.

---

## 1. Cómo funciona

- Hay dos lugares posibles para la multimedia del sitio:
  - **Servidor** (disco `media_local`): siempre disponible, es el respaldo.
  - **Synology** (disco `media_synology`): el NAS de la oficina, conectado por
    SFTP a través de la red privada Tailscale.
- El **proveedor activo** es el que recibe las subidas nuevas. Se cambia con
  «Activar proveedor».
- **Cada archivo recuerda en qué disco vive.** Por eso puede haber archivos
  repartidos entre servidor y Synology y todos siguen funcionando: el sitio los
  sirve igual por la dirección `/media/…`.
- Cada foto tiene además «versiones optimizadas» (tamaños para celular, tablet
  y escritorio) y cada video su miniatura. Se mueven siempre junto con el
  original.
- **Los documentos privados** (contratos, identificaciones, evidencias de
  obra, fotos de la vivienda del cliente) **nunca salen del servidor**. Se
  guardan en un disco aparte y solo se entregan a quien tiene permiso.

En la pantalla verás, en «Dónde están tus archivos», cuántos archivos y cuánto
espacio hay en cada lugar, y cuántos están esperando subir al Synology.

---

## 2. Respaldo automático cuando el NAS no responde

1. Alguien sube una foto o video con el Synology activo.
2. Si el NAS no responde (internet de la oficina caído, NAS apagado,
   Tailscale desconectado), el archivo se guarda en el **servidor** y queda
   marcado como pendiente. La persona que subió no nota nada.
3. Durante los siguientes 5 minutos las subidas van directo al servidor sin
   volver a intentar el NAS, para que nadie espere.
4. Se avisa a los administradores **una vez por corte** (no un correo por cada
   foto) y queda registro en la Bitácora (`almacenamiento.respaldo_local`).
5. Cada 10 minutos la tarea `media:sync-pending` prueba el NAS. En cuanto
   responde, sube los pendientes (original y versiones), verifica cada copia y
   borra la del servidor. Queda registro (`almacenamiento.sincronizado`).

También puedes forzarlo:
- **«Sincronizar ahora»** en la tarjeta «Esperando subir al Synology».
- **«Probar conexión»** del Synology: si sale bien, arranca la subida de
  pendientes de inmediato.

> Si mientras tanto decides volver a activar el **servidor** como proveedor,
> los pendientes se quedan en el servidor (donde ya funcionan) y se quita la
> marca.

---

## 3. Conectar el Synology

### 3.1 En el NAS (DSM)

1. **Habilitar SFTP**: *Panel de control → Servicios de archivos → FTP →
   SFTP → Habilitar servicio SFTP*. Puerto: 22 (o el que elijas, p. ej. 2222).
2. **Carpeta compartida**: *Panel de control → Carpeta compartida → Crear*,
   por ejemplo `ml-habitat`.
3. **Usuario dedicado**: *Panel de control → Usuario y grupo → Crear*, por
   ejemplo `mlhabitat`, con contraseña larga.
   - Permisos: **Lectura/Escritura** solo en `ml-habitat`; **Sin acceso** en las
     demás carpetas.
   - Aplicaciones: permitir **SFTP**; negar las demás (File Station, SMB, etc.)
     si no se usan.
4. **Tailscale**: instala el paquete Tailscale en el NAS y en el servidor, en
   la misma red (tailnet). Usa el nombre MagicDNS del NAS (p. ej.
   `nas.tu-tailnet.ts.net`) o su IP 100.x.y.z.
5. **No abras el puerto SFTP a Internet** en el router. La conexión va solo
   por Tailscale.

### 3.2 En el servidor (`.env`)

```
NAS_SFTP_ROOT=/ml-habitat        # carpeta compartida, tal como la ve SFTP
# Opcionales (si no se capturan en el panel):
NAS_SFTP_HOST=nas.tu-tailnet.ts.net
NAS_SFTP_PORT=22
NAS_SFTP_USERNAME=mlhabitat
NAS_SFTP_PASSWORD=
NAS_SFTP_PRIVATE_KEY_PATH=
NAS_SFTP_TIMEOUT=10              # segundos para considerar que no responde
NAS_SFTP_MAX_TRIES=0             # reintentos de conexión (0 = uno solo)
```

La carpeta raíz (`NAS_SFTP_ROOT`) solo se cambia en `.env`. Host, puerto,
usuario, contraseña o llave se capturan en el panel y se guardan **cifrados**;
lo del panel tiene prioridad sobre `.env`.

Para usar llave en vez de contraseña: genera la llave en el servidor
(`ssh-keygen -t ed25519`), agrega la pública al usuario del NAS
(*Usuario → Avanzado → Claves SSH* o `~/.ssh/authorized_keys` del usuario) y
captura en el panel la **ruta** del archivo de la llave privada en el
servidor.

### 3.3 Procesador de tareas

Los traslados y la sincronización corren en segundo plano. En el servidor
deben estar encendidos:

- el procesador de cola: `php artisan queue:work` (como servicio, p. ej. con
  Supervisor);
- el programador: `php artisan schedule:run` cada minuto (cron).

Si un traslado se queda «En cola» más de un minuto, la pantalla lo avisa.

---

## 4. Probar y activar

1. En «Conexión con Synology» captura host, puerto, usuario y contraseña (o
   llave). **Guardar configuración**.
2. Pulsa **Probar conexión**: escribe, lee y borra un archivo de prueba. Debe
   salir «Conexión, lectura y escritura correctas».
3. Pulsa **Activar proveedor** en la tarjeta del Synology. Desde ese momento
   las subidas nuevas van al NAS (con el respaldo automático del punto 2).

Activar el Synology **no mueve** lo que ya existe; eso es el siguiente paso.

---

## 5. Mover la multimedia del servidor al Synology

1. En «Mover multimedia entre el servidor y el Synology» pulsa **Mover
   multimedia del servidor al Synology**. (Se habilita cuando la última prueba
   de conexión salió bien.)
2. Confirma. Deja encendido **«Conservar copia en el servidor»**
   (recomendado): así, si algo sale mal, el original sigue en el servidor.
3. Verás el avance en vivo: *X de Y archivos*, MB transferidos, errores y
   tiempo restante. Puedes cerrar la página y volver.
4. Durante el traslado el sitio sigue funcionando: cada archivo cambia de
   lugar solo cuando su copia ya está verificada.
5. Al terminar verás el resultado. Si hubo errores aparece la lista con el
   motivo y el botón **Reintentar los fallidos**.
6. **Cancelar** detiene el traslado: lo que ya se movió se queda en el NAS y
   funciona; lo pendiente se queda en el servidor. Puedes reanudar después.

Cómo se protege cada archivo:
- Se copia por partes (sin cargarlo completo en memoria), respetando el
  **límite de transferencia (KB/s)** configurado para no saturar el internet
  de la oficina.
- Se verifica el tamaño y, en el original, una **huella digital** (xxh128)
  releyendo la copia en el NAS.
- Solo después se actualiza la base de datos, y solo después (si pediste no
  conservar copia) se borra el original del servidor.
- Se puede repetir sin riesgo: lo que ya está en el NAS no se vuelve a mandar.
  Si un traslado anterior se interrumpió, las copias completas se aprovechan.

Desde la terminal del servidor (mismo proceso, con barra de progreso):

```
php artisan media:migrate --to=synology --dry-run        # solo cuenta, no mueve nada
php artisan media:migrate --to=synology                  # mueve y conserva copia en el servidor
php artisan media:migrate --to=synology --delete-source  # mueve y borra del servidor lo verificado
php artisan media:migrate --to=synology --queue          # lo manda a la cola (como el botón)
php artisan media:sync-pending                           # sube ya los pendientes del respaldo
```

---

## 6. Verificar

- En «Dónde están tus archivos», el **Servidor** debe quedar en 0 archivos
  públicos (o solo los que fallaron) y el **Synology** con el total.
- `php artisan media:migrate --to=synology --dry-run` debe decir «Archivos por
  mover: 0».
- Abre el sitio público y la Biblioteca multimedia: fotos y videos deben verse
  normal (también en iPad/Safari para videos).
- La Bitácora registra `almacenamiento.traslado_iniciado` y
  `almacenamiento.traslado_finalizado` con los conteos.

Cuando todo se vea bien, pulsa **Liberar espacio en el servidor**. Antes de
borrar cada copia se vuelve a comprobar que el archivo está completo en el NAS.
Este paso no se puede deshacer.

---

## 7. Regresar (rollback) y emergencias

- **El NAS falla unas horas**: no hagas nada. Las subidas van al servidor y se
  pasan solas cuando vuelva. Si conservaste copia en el servidor, el sitio
  sigue mostrando esas fotos aunque el NAS no responda.
- **Volver a usar el servidor para lo nuevo**: activa «Almacenamiento local».
- **Traer todo de vuelta al servidor** (cambio de NAS, falla prolongada):
  botón **Traer multimedia al servidor** o
  `php artisan media:migrate --to=local`. Si se conservaron copias en el
  servidor, se reutilizan sin volver a descargarlas. Revisa antes que haya
  espacio libre suficiente (la tarjeta del servidor lo muestra).
- **Cambiar de NAS**: trae todo al servidor, configura y prueba el nuevo NAS,
  y vuelve a moverlo.

---

## 8. Problemas comunes

| Síntoma | Qué revisar |
| --- | --- |
| «No se pudo conectar con el NAS» | Tailscale encendido en NAS y servidor; host y puerto; servicio SFTP habilitado. |
| Conecta pero no escribe | Permiso Lectura/Escritura del usuario en la carpeta; `NAS_SFTP_ROOT` correcto. |
| Traslado «En cola» sin avanzar | `queue:work` apagado en el servidor. |
| Archivos «esperando subir» que no bajan | El NAS sigue sin responder (ver «Último error»); `schedule:run` apagado. |
| Un archivo falla con «huella distinta» | Se reintenta solo; si persiste, revisa el disco del NAS. El original sigue intacto. |
| «No se encontró el archivo en el origen» | El archivo ya no existía en disco antes del traslado; no se puede recuperar desde aquí. |

---

## Detalles técnicos (para desarrolladores)

- Discos en `config/filesystems.php`: `media_local`, `media_synology` (driver
  `synology` = SFTP con credenciales del panel, `App\Services\Storage\SynologyDisk`)
  y `media_private`.
- Respaldo: `App\Services\Storage\MediaStorage` (cortacircuitos en caché,
  aviso único `StorageFallbackActivated`). Columnas en `media`: `checksum`,
  `pending_sync_to`, `sync_attempts`, `sync_error`.
- Traslado de un archivo: `App\Services\Storage\MediaRelocator` (candado por
  archivo compartido con `GenerateImageVariants` y `ProcessVideoMedia`).
- Traslados: `App\Services\Storage\StorageMigrationService`, tablas
  `storage_migrations` y `storage_migration_items`, job
  `App\Jobs\Storage\MigrateMediaItem` en un Bus batch.
- Ajustes en `config/media.php` → `storage` (`MEDIA_STORAGE_*`).
- Pruebas: `tests/Feature/Platform/StorageFallbackTest.php` y
  `StorageMigrationTest.php` (NAS simulado con `FlakyDisk`).
