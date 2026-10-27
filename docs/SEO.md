# SEO, vistas previas y Google — ML Habitat

Guía para dejar **https://mlhabitat.com.mx** bien indexado y con vistas previas
bonitas al compartir enlaces (WhatsApp, Facebook, Instagram DM, LinkedIn, X,
iMessage, Slack, Telegram).

> **Nota honesta:** todo lo de este documento hace que Google pueda rastrear,
> entender y mostrar bien el sitio. **No garantiza** el primer lugar. El
> posicionamiento depende sobre todo de contenido útil y propio (fotos reales,
> descripciones, preguntas frecuentes, proyectos), de enlaces desde otros sitios
> (directorios, proveedores, prensa, Google Business Profile), de las reseñas y
> del tiempo: un dominio nuevo suele tardar de semanas a meses en ganar
> visibilidad.

---

## 1. Cómo funciona (resumen técnico)

| Pieza             | Dónde                                                                   | Qué hace                                                                                                                                          |
| ----------------- | ----------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------- |
| Prop `seo`        | `App\Services\Web\Seo::page()` (lo llama cada controlador público)      | Título (≤ 60), descripción (≤ 155), canónica, robots, Open Graph, Twitter y JSON-LD. Incluye `seo.head`: las etiquetas ya escritas.               |
| HTML del servidor | `resources/views/app.blade.php`                                         | Imprime `seo.head` dentro de `<head>`. **No depende de JavaScript**: los rastreadores de redes sociales y muchas herramientas SEO no ejecutan JS. |
| Navegación SPA    | `resources/js/app.ts` → `createInertiaApp({ serverHead })`              | Inertia reutiliza esas mismas etiquetas (`data-inertia="og:title"`, etc.) y las reemplaza al navegar, sin duplicarlas.                            |
| Imágenes OG       | `App\Services\Web\OgImage` + `GET /og/{tipo}/{slug}.png`                | Tarjeta de 1200×630 generada con GD y las fuentes de marca (`resources/fonts`, licencia OFL).                                                     |
| Sitemap           | `GET /sitemap.xml` (índice + `/sitemap-N.xml` si hay más de 5 000 URLs) | Solo contenido publicado, con `lastmod` e `image:image`.                                                                                          |
| robots.txt        | `GET /robots.txt`                                                       | Permite el sitio público; bloquea panel, portal, técnicos, archivos privados y páginas transaccionales.                                           |
| Manifest          | `GET /site.webmanifest`                                                 | Iconos 192/512 y _maskable_ (`public/icons`).                                                                                                     |
| Dominio canónico  | `App\Http\Middleware\CanonicalHost`                                     | Solo en producción: 301 de `http://` y `www.` a `https://mlhabitat.com.mx`.                                                                       |

Páginas que **no** se indexan (`noindex, nofollow`): acceso (login, recuperar
contraseña, 2FA), panel, portal de clientes, app de técnicos, "gracias",
agendar/pagar visita técnica, reservar asesoría, precalificación,
comprobantes de pago e invitaciones. También los listados de inspiración
filtrados o paginados (la versión sin filtros sí se indexa).

### Datos estructurados (JSON-LD)

| Página                 | Tipos                                                                                                                                                                                                                                                         |
| ---------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Portada                | `Organization` (logo, redes en `sameAs`, `contactPoint` si hay teléfono/correo), `WebSite` (sin `SearchAction`: no hay buscador interno), `HomeAndConstructionBusiness` **solo si** hay dirección capturada en Contacto. No se inventan domicilio ni horario. |
| Catálogo / categoría   | `BreadcrumbList`, `FAQPage` (si la categoría tiene preguntas).                                                                                                                                                                                                |
| Producto               | `Product` (sin `offers` salvo que el precio se muestre públicamente), `BreadcrumbList`, `FAQPage`.                                                                                                                                                            |
| Inspiración (proyecto) | `BreadcrumbList`, `ImageGallery` con `ImageObject`.                                                                                                                                                                                                           |
| Preguntas frecuentes   | `FAQPage`, `BreadcrumbList`.                                                                                                                                                                                                                                  |
| Legal                  | `BreadcrumbList`.                                                                                                                                                                                                                                             |

---

## 2. Imágenes de vista previa (Open Graph)

Ejemplo: `https://mlhabitat.com.mx/og/product/cocina-nordica.png?v=06b47dc1e38e`

- **Producto, categoría, proyecto:** fondo lino, fotografía de portada con
  esquinas redondeadas, antetítulo (categoría), título en Familjen Grotesk,
  monograma ML y el dominio.
- **Portada:** si el hero del CMS tiene imagen, fotografía a sangre con
  degradado espresso; si no, tarjeta tipográfica con el abanico de acabados.
- **Páginas fijas y legales:** tarjeta tipográfica con el abanico.
- **Imagen propia:** en _Sitio web → Marca_, la "Imagen para redes sociales (OG)"
  (`brand.og_media_id`) sustituye a la tarjeta de la portada (se recorta a
  1200×630).

Se generan la primera vez que alguien las pide y se guardan en
`storage/app/private/og`. El `?v=` es un hash del contenido (título, portada,
marca): si editas el producto cambia la URL y Facebook/WhatsApp piden la
imagen nueva. Para regenerarlas todas: borrar `storage/app/private/og` (o
subir `OgImage::VERSION` si cambia el diseño).

**Probar vistas previas:**

- Facebook / WhatsApp / Instagram: <https://developers.facebook.com/tools/debug/> → "Volver a extraer".
- LinkedIn: <https://www.linkedin.com/post-inspector/>.
- X: publica el enlace en un borrador.
- WhatsApp guarda en caché la vista previa de cada URL por un tiempo; si cambiaste algo, prueba con la URL + `?v=2`.

---

## 3. Puesta en producción

1. `.env`:

    ```dotenv
    APP_ENV=production
    APP_URL=https://mlhabitat.com.mx   # sin www y sin barra final
    APP_CANONICAL_REDIRECT=true        # false si el servidor/CDN ya redirige
    ```

2. DNS: registros `A`/`AAAA` para `mlhabitat.com.mx` **y** para
   `www.mlhabitat.com.mx` (o `CNAME www → mlhabitat.com.mx`), y certificado TLS
   que cubra ambos (Let's Encrypt con los dos nombres). El dominio elegido es
   el **apex sin www**; `www` responde 301 hacia él.
3. `php artisan config:cache && php artisan route:cache && php artisan view:cache`.
4. `npm run build`.
5. Abrir `https://www.mlhabitat.com.mx/catalogo` y `http://mlhabitat.com.mx/`:
   ambos deben responder **301** a `https://mlhabitat.com.mx/...`.

> Detrás de un proxy o CDN que termina TLS (Cloudflare, balanceador), el
> middleware respeta `X-Forwarded-Proto: https` para no crear bucles. Si usas
> uno, configura también `trustProxies` de Laravel.

### Servidor web (caché y compresión)

`public/.htaccess` (Apache) ya incluye caché de un año para `/build/*`
(archivos con hash), caché de una semana para iconos y compresión `deflate`.
En **nginx**:

```nginx
location /build/ {
    add_header Cache-Control "public, max-age=31536000, immutable";
    try_files $uri =404;
}
location ~* \.(ico|svg|webmanifest)$ {
    add_header Cache-Control "public, max-age=604800";
    try_files $uri /index.php?$query_string;
}
# Las imágenes OG las sirve Laravel: NO uses una regla que responda 404 a
# los .png que no existen en disco.
location /og/ {
    try_files $uri /index.php?$query_string;
}
gzip on;
gzip_types text/css application/javascript application/json application/xml image/svg+xml application/manifest+json;
# (o brotli si el módulo está disponible)
```

### Rendimiento (Core Web Vitals)

- Fuentes autoalojadas (`resources/fonts/web`, WOFF2 variables, `latin` +
  `latin-ext`, `font-display: swap`) y precargadas desde `app.blade.php`. Sin
  peticiones a terceros (más rápido y sin compartir datos de visitantes).
- La imagen del hero de la portada se precarga con `fetchpriority="high"`
  (`<link rel="preload" as="image">`, en AVIF si existe).
- Imágenes responsivas WebP/AVIF con `width`/`height` (evitan CLS).

### SSR de Inertia (opcional)

Las etiquetas SEO **no necesitan SSR**: ya salen en el HTML del servidor. El
SSR solo añadiría el contenido visible de la página al HTML inicial (útil
para el primer pintado y para buscadores distintos de Google, que sí ejecuta
JavaScript). Si se quiere activar:

1. `npm run build:ssr` (genera `bootstrap/ssr/`).
2. Limitarlo al sitio público en `AppServiceProvider::boot()`:
   `Inertia::withoutSsr(['admin*', 'portal*', 'tecnico*', 'settings*', 'login', 'notificaciones*']);`
3. Dejar el proceso Node siempre activo con Supervisor:

    ```ini
    [program:mlhabitat-ssr]
    command=php /var/www/mlhabitat/artisan inertia:start-ssr
    autostart=true
    autorestart=true
    user=www-data
    ```

4. En cada despliegue: `php artisan inertia:stop-ssr` (Supervisor lo reinicia).

Si el proceso SSR se cae, Laravel sirve la página normal (renderizada en el
navegador) y las etiquetas SEO siguen saliendo igual. Con SSR activo, el
`<head>` lo arma Inertia con las mismas etiquetas de `seo.head` (sin
duplicados) y el `noindex` de las páginas privadas se imprime fuera de ese
bloque, así que se conserva.

Verificado en local con el SSR del servidor de desarrollo de Vite (`npm run
dev` + `public/hot`): catálogo, categoría, producto, inspiración, preguntas,
solicitar, agendar visita y acceso responden con `data-server-rendered` y
una sola copia de cada etiqueta. Antes de activarlo en producción, prueba
`npm run build:ssr` y revisa que ninguna página pública nueva use
`window`/`localStorage` al cargar el módulo.

---

## 4. Google Search Console

1. Entra a <https://search.google.com/search-console> con la cuenta de Google
   de la empresa.
2. **Agregar propiedad → Dominio** → escribe `mlhabitat.com.mx`.
3. Google muestra un registro **TXT** (`google-site-verification=…`). Agrégalo
   en el DNS del dominio (en el panel de tu registrador: Host `@`, Tipo `TXT`).
   Espera unos minutos y pulsa **Verificar**. Con la propiedad de dominio se
   cubren `https`, `http`, `www` y subdominios.
    - Alternativa (propiedad de **prefijo de URL** `https://mlhabitat.com.mx/`):
      método **Etiqueta HTML**; copia la etiqueta y pégala en
      _Administración → Configuración → Buscadores → Código de verificación de
      Google_. El sitio imprime `<meta name="google-site-verification">` en las
      páginas públicas.
4. **Sitemaps** → agrega `https://mlhabitat.com.mx/sitemap.xml` → Enviar.
5. **Inspección de URLs** → pega la portada y 2–3 productos → _Solicitar
   indexación_.
6. Revisa en 1–2 semanas: _Páginas_ (indexadas / excluidas), _Mejoras_
   (Productos, Rutas de navegación, Preguntas frecuentes) y _Métricas web
   principales_.

## 5. Bing Webmaster Tools (también alimenta a DuckDuckGo y Copilot)

1. <https://www.bing.com/webmasters> → **Importar desde Google Search
   Console** (lo más rápido), o agrega el sitio y verifica con el código
   `msvalidate.01` en _Configuración → Buscadores → Código de verificación de
   Bing_.
2. Envía `https://mlhabitat.com.mx/sitemap.xml`.

## 6. Google Business Profile (muy importante para búsquedas locales)

Crea o reclama el perfil en <https://business.google.com>: categoría
"Fabricante de muebles de cocina" / "Diseñador de cocinas", zona de servicio,
teléfono, horario, fotos reales de proyectos y enlace a
`https://mlhabitat.com.mx`. Pide reseñas a clientes al cerrar cada proyecto.
Es lo que más influye para aparecer en el mapa ("cocinas integrales cerca de
mí").

---

## 7. Qué resultados enriquecidos esperar

- **Rutas de navegación** (Inicio › Catálogo › Cocinas integrales) en lugar
  de la URL.
- **Logo y datos de la empresa** en el panel de conocimiento (depende de
  Google; ayuda tener Business Profile y redes enlazadas).
- **Imágenes** de productos y proyectos en Google Imágenes (sitemap de
  imágenes).
- **Fragmentos de producto**: nombre, imagen y descripción. Sin precio salvo
  que se publique.
- Las **preguntas frecuentes** desplegables (FAQ) hoy Google solo las muestra
  a sitios gubernamentales o de salud; el marcado no estorba y ayuda a
  entender la página.

## 8. Lista de verificación

- [ ] `APP_URL=https://mlhabitat.com.mx` y `APP_ENV=production`.
- [ ] `www` y `http` redirigen con 301 al dominio canónico.
- [ ] Certificado TLS válido para `mlhabitat.com.mx` y `www`.
- [ ] Search Console verificado (DNS) y sitemap enviado.
- [ ] Bing importado desde Search Console.
- [ ] Google Business Profile creado y enlazado.
- [ ] _Sitio web → SEO_: título y descripción generales revisados.
- [ ] _Sitio web → Contacto_: teléfono, WhatsApp, correo (y dirección si
      atiendes en un local; si no, déjala vacía).
- [ ] _Sitio web → Redes_: perfiles reales (alimentan `sameAs`).
- [ ] Cada categoría y producto con portada real, resumen propio y, si
      aplica, título/descripción SEO.
- [ ] Probar 3 enlaces en el depurador de Facebook y en WhatsApp.
- [ ] Revisar _Métricas web principales_ (PageSpeed Insights) en móvil.
- [ ] Publicar proyectos de inspiración con fotos reales con regularidad.
