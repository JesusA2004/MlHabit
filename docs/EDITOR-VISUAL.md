# Editor visual de la portada

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
