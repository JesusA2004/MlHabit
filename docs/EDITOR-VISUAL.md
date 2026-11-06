# Editor visual (portada y páginas propias)

`/admin/sitio/editor` (permiso `content.manage`; publicar requiere además `content.publish`).

## Modelo de datos

- `site_sections` = lo **publicado**. Columnas nuevas (migración `2026_10_28_000100`): `key` (uuid), `layout` (JSON) y `layout_version`. Ya no hay `UNIQUE(page, type)`: puede haber varias secciones del mismo tipo; el tipo nuevo `canvas` es un lienzo libre.
- `site_page_drafts` = borrador de la página completa (`snapshot.sections[]`: `key, type, isVisible, content, layout`) con `revision` para evitar pisar cambios (409).
- `site_page_versions` = historial inmutable (`number`, `snapshot`, `note`, `published_by`, `published_at`). Restaurar copia la versión al borrador; se ve en el sitio al publicar.
- Validación: `App\Cms\Layout\LayoutValidator` (lista blanca de bloques, props, tokens de color/#hex, enlaces seguros, texto saneado con `App\Support\Html`, límites numéricos, medios públicos del tipo correcto, un solo H1 al publicar). Los bloques dinámicos validan sus datos con los campos de los esquemas de `App\Cms\Sections` (p. ej. nunca prometer crédito) y al publicar se reflejan en `content` (otras páginas lo leen).
- Conversión: `App\Cms\Layout\LegacyLayouts` convierte las 11 secciones curadas en lienzos fieles al diseño (la migración lo hace con las existentes; `SiteSectionSeeder` y las plantillas lo usan).

## Lienzo y responsivo automático

Escritorio: 12 columnas sobre 1200 px de contenido (lienzo de referencia 1280), filas de 24 px (`2cqi`, crecen solas si el texto lo pide). El dueño solo acomoda escritorio; `components/site-builder/grid.ts` calcula:

- Tableta (768–1023): 8 columnas, cada borde `b → round(b·8/12)` (sin huecos ni encimados por redondeo).
- Móvil (< 768): una columna en orden de lectura — franjas horizontales de arriba abajo; dentro de cada franja, columnas de izquierda a derecha. Textos encimados sobre una foto/forma/video comparten celda (la foto de fondo). Fotos y videos conservan la proporción del marco de escritorio. Respeta `hideOn`.

Todo se aplica con **container queries** sobre `.sb-page`, por eso el editor puede previsualizar tableta y móvil en un marco. Pruebas: `npx vp test run resources/js/components/site-builder/grid.test.ts`.

Movimiento: cada bloque emite `data-motion` / `data-motion-delay` (el motor global de `lib/motion` los anima); cada sección emite `data-journey` (recorrido guiado) y su ancla (`visita`, `pagos`, `como-funciona`).

## Arrastrar y redimensionar

Implementado con Pointer Events propios (`editor/useEditor.ts`), sin dependencias nuevas: las librerías Vue 3 de drag/resize (vue-draggable-resizable, vue3-draggable-resizable, grid-layout-plus) o están sin mantenimiento o imponen su propio layout absoluto/retícula incompatible con renderizar la página real con CSS grid + container queries. El propio código: transform temporal en `requestAnimationFrame`, sombra del destino ajustada a la retícula, guías de alineación (bordes y centros con otros bloques y el centro de la sección), confirmación al soltar en un paso del historial; ratón, táctil y lápiz (asas con área táctil ampliada para iPad).

## Páginas propias

Sitio web → **Páginas** (`/admin/sitio?pestana=paginas-web`). El dueño crea páginas con el **mismo editor** de la portada (`/admin/sitio/paginas-web/{id}/editor`).

- Modelo `App\Models\SitePage` (`site_pages`: título, slug, SEO, imagen para compartir, plantilla, `is_published`). El contenido usa las tablas del editor con la clave de página `pagina-{id}` (`site_sections.page`, `site_page_drafts`, `site_page_versions`); la portada sigue en `home` sin cambios.
- Plantillas de inicio en `App\Cms\Layout\PageTemplates` (en blanco con encabezado, servicio, galería/proyecto, nosotros, landing con video), armadas con bloques existentes; cada una con un solo H1.
- Publicar desde el editor (o «Publicar» en la lista) hace visible la página; despublicar la oculta (y sus enlaces del menú). Solo se borra si está despublicada (se borran secciones, borrador e historial). Duplicar regenera las claves de sección.
- **URL pública: `/{slug}`**, registrada como ruta de respaldo (`->fallback()`, `routes/modules/public.php`): Laravel la evalúa después de todas las demás, así nunca tapa una ruta existente. Además el slug se rechaza si coincide con el primer tramo de cualquier ruta registrada, una carpeta de `public/` o la lista fija de `App\Services\Web\SitePages::reserved()`. Sin publicar responde 404; el equipo con `content.manage` ve el borrador como vista previa (`noindex`). Entra al sitemap y genera su imagen OG (`/og/page/{slug}.png`) si no se eligió una.
- Bloques nuevos: **Galería** (`gallery`: hasta 24 fotos públicas, cuadrícula/mosaico/carrusel, visor a pantalla completa) y **YouTube / Vimeo** (`embed`: solo esos dominios, se guarda proveedor + id y el reproductor usa `youtube-nocookie.com` / `player.vimeo.com`, permitidos en `frame-src` de `config/security.php`). Validación en `LayoutValidator` (`App\Cms\Layout\VideoEmbed`).

## Menú del sitio

Sitio web → **Menú** (`/admin/sitio?pestana=menu`, requiere `content.manage` + `content.publish`). Enlaces de la cabecera en orden (arrastrar), un nivel de submenú, visibilidad, abrir en otra pestaña y el botón destacado.

- Se guarda en `Settings('navigation')` (`items`, `cta`); `null` = `App\Services\Web\Navigation::defaults()`, idéntico a la cabecera anterior (Catálogo e Inspiración se ocultan solas sin contenido publicado). Destinos: sección fija del sitio (`builtin`), página propia (`page`, solo si está publicada), enlace (`url`: ruta interna, https://, mailto:, tel:) o solo submenú (`group`).
- `Navigation::resolve()` llega a todas las páginas públicas como `site.navigation` / `site.cta` (caché de `PublicSite`, se invalida al guardar el menú o cualquier página).
- `SiteHeader.vue` lo dibuja: indicador deslizante (pastilla + subrayado de roble) que sigue al puntero, submenús animados, mega-menú del Catálogo, menú móvil a pantalla completa con submenús desplegables; todo sin animación con `prefers-reduced-motion`. El pie aplana los submenús en «Explora».

## Portada v3 (rediseño, noviembre de 2026)

La portada por defecto vive en `App\Cms\Layout\HomeBlueprint` (orden, textos sugeridos y lienzos) y la usan la siembra (`SiteSectionSeeder`), `SiteBuilder::ensureSections()` y la migración de abajo. `LegacyLayouts` genera ya el diseño nuevo de cada tipo curado (también al «Agregar sección»); el lienzo anterior quedó congelado en `App\Cms\Layout\Legacy\HomeV1Layouts` solo para comparar.

Recorrido: portada a sangre (degradado `forest-night`, titular `display` palabra por palabra, escena ilustrada `shape: interior` con lámparas que respiran y etiquetas flotantes, polvo de luz) → cifras que cuentan (`stat` con `data-countup`) → líneas de servicio → por qué nosotros (muro de listones `shape: slats`) → **cómo funciona** (`#como-funciona`, línea que se rellena) → materiales (`shape: materials`, marquesina a sangre) → ideas y proyectos → **visita** (`#visita`) → cobertura → precios (`service_pricing`) → **formas de pago** (`#pagos`) → testimonios (carrusel) → preguntas → llamada final con partículas.

- **Bloque nuevo `service_pricing`** («Precios de asesoría y visita»): el costo sale de `ConsultationSettings` (precio y duración; se oculta si las asesorías están desactivadas) y de la política de visita activa (sin política: «Costo según tu zona»). Nunca se escribe un monto a mano.
- **«Mostrar contenido de ejemplo»** (`show_example`) en categorías, proyectos, testimonios y preguntas: si la lista viva está vacía, en lugar de ocultar la sección se dibuja un respaldo honesto (líneas de servicio ilustradas, ideas de diseño con comparador plano → espacio, compromisos de servicio —nunca testimonios inventados— y respuestas generales). Por defecto está apagado (una sección agregada después se sigue ocultando sola); la portada v3 lo trae encendido. En el editor el respaldo lleva un aviso «Ejemplo».
- **Opciones nuevas**: tamaño de título `display` (2XL), movimientos `split`, `blur-up`, `rise`, `wipe-up/left/right`, `pop`, `tilt-in`, `zoom-out`; formas `interior`, `materials`, `slats`; degradado `forest-night`. Plantillas nuevas: Cifras, Materiales y Precios.
- Todo respeta `prefers-reduced-motion` (sin lámparas, marquesina ni partículas animadas) y no usa librerías nuevas.

### Cómo llega a producción

Migración `2026_11_06_000200_publish_redesigned_home` → `App\Cms\Layout\HomeRedesign::apply()` (también `php artisan mlhabitat:redesign-home [--force]`):

1. Respalda SIEMPRE la portada publicada como versión del historial «Portada anterior (antes del rediseño)» (y un borrador con cambios sin publicar como «Borrador sin publicar (antes del rediseño)»). Se restaura desde el editor → Historial → Restaurar → Publicar.
2. Si la portada es la de fábrica (mismo orden de secciones, todas visibles y cada lienzo igual al v1 normalizado: sin ids de bloque ni `journey`) o el equipo nunca publicó desde el editor (sin versiones y `updated_by` vacío), publica la v3, registra la versión «Rediseño de la portada (v3)» y deja el borrador igual a lo publicado.
3. Si el equipo ya la personalizó, no la toca: guarda la v3 como versión «Portada rediseñada (v3): restáurala para usarla».
4. Solo trabaja con `page = home`; las páginas propias (`pagina-{id}`) nunca se tocan. Es idempotente (si ya existe alguna de esas versiones no hace nada; `--force` publica la v3 aunque esté personalizada, respaldando antes). En una base sin portada la migración no hace nada (la siembra ya crea la v3).

Pruebas: `tests/Feature/Web/HomeRedesignTest.php`.
