# MR. INSIGHT Studio — Arquitectura

Constructor visual por bloques integrado en Laravel + Inertia + Vue. Ruta: `/admin/studio`.

## 1. Piezas

| Capa | Archivos | Responsabilidad |
|---|---|---|
| Esquema compartido | `resources/js/studio/schema.json` | Tipos de sección/bloque, propiedades, rangos, enums, grupos de estilo, presets de movimiento, breakpoints. Lo leen TS **y** PHP. |
| Validación | `app/Studio/StudioDocumentValidator.php` | Sanea y valida todo documento antes de guardarlo. Lista blanca estricta. |
| Migración | `app/Studio/StudioMigrator.php`, `resources/js/studio/migrate.ts` | Versiones de esquema por documento (`schema`) y por bloque (`v`). Normaliza `[]`→`{}`. |
| Datos dinámicos | `app/Studio/StudioResolver.php` | Resuelve medios, feeds de publicaciones, categorías, entradas, páginas del CMS y redes. |
| Referencias | `app/Studio/StudioReferences.php` | Extrae IDs de medios/páginas guiándose por el esquema; verifica integridad antes de publicar. |
| Flujo editorial | `app/Studio/StudioService.php` | Páginas del sistema, borrador con revisión, publicar, restaurar, descartar. |
| Datos iniciales | `app/Studio/StudioDefaults.php` | Documentos de inicio construidos desde el contenido existente del CMS. |
| Modelos | `StudioPage`, `StudioVersion` | Borrador (`draft`, `draft_revision`), versión publicada, historial inmutable. |
| API editor | `Admin\StudioController` | JSON para autoguardado/publicación/restauración; Inertia para listado, editor, canvas y vista previa. |
| Público | `Public\{Home,Page,Social,StudioPage}Controller` | Renderizan la **versión publicada**. Páginas personalizadas vía `Route::fallback`. |
| Render | `resources/js/components/blocks/*` | `PageRenderer` + registro de secciones y bloques. Lo usan público, vista previa y canvas. |
| CSS por bloque | `resources/js/studio/css.ts` | Genera CSS responsive desde valores validados. |
| Editor | `pages/admin/studio/Editor.vue`, `components/studio/*`, `studio/store.ts` | Árbol, canvas, inspector, historial, autoguardado, publicación. |
| Canvas | `pages/public/StudioCanvas.vue` | Iframe del mismo origen con la plantilla pública real; selección, edición en línea, barra contextual. |

## 2. Documento

```jsonc
{
  "schema": 1,
  "sections": [{
    "id": "n_3fa2c1d0",          // estable (regex ^[a-z0-9_-]{4,40}$)
    "type": "hero",              // registrado en schema.json
    "v": 1,                      // versión del tipo
    "props": { "layout": "editorial", "tone": "ink", "spacing": "editorial" },
    "style": { "base": { "minHeight": 80 }, "mobile": { "paddingTop": 64 } },
    "motion": { "preset": "slide-up", "duration": 800, "delay": 0, "distance": 24, "trigger": "scroll", "mobile": "reduce", "once": true },
    "hidden": false,
    "slots": {
      "content": [{ "id": "n_…", "type": "heading", "v": 1, "props": { "text": "…", "level": "h1" }, "style": {}, "motion": null, "hidden": false }],
      "media":   [{ "id": "n_…", "type": "image",   "v": 1, "props": { "media": 12 } }]
    }
  }]
}
```

- **Orden**: posición en el arreglo. **Contenedor padre**: el slot. **Estado**: `hidden`.
- **Medios**: solo IDs de `media` → URL vía `Media::url()` (nunca rutas del VPS/NAS).
- **Contenido del CMS por referencia**: `bind: "title" | "intro" | "cover"` + `bindPage`, `page-body`, `timeline.source = entries`, secciones `posts`/`categories`/`social` (guardan configuración, no copias).

## 3. Responsive determinista

Cascada desktop-first: `base (≥1280) → laptop (≤1279) → tablet (≤1023) → mobile (≤767)`.

- Editar con un dispositivo activo escribe solo `style[bp]`; restablecer elimina la clave.
- Medidas heredadas (tamaño de fuente, márgenes, espacios, alturas) se **auto-escalan** (×0.9 / ×0.78 / ×0.62, fuente mínima 14 px) para evitar desbordes.
- Estructura (`columns`, `stack`, `reverse`) **no** se hereda: cada dispositivo usa su valor o el comportamiento por defecto.
- El canvas es un iframe con el ancho real (1440 / 1024 / 768 / 390), así las media queries son las reales.

## 4. Seguridad

- Sin HTML, JS, CSS ni clases libres: el renderizador resuelve tipos con un registro cerrado (`elementRegistry.ts`, `sectionRegistry.ts`).
- Textos como texto plano (interpolación de Vue). Colores: token o `#RRGGBB`. Números acotados. Fuentes de lista blanca.
- Enlaces: rutas internas `/…`, `https?://`, `mailto:`, `tel:`, red social configurada o ancla. Nunca `javascript:`/`data:`.
- Editor y canvas solo para `admin`/`editor`. Vista previa compartida: URL firmada con caducidad de 48 h y `X-Robots-Tag: noindex`.
- Concurrencia: `draft_revision`; guardar con revisión vieja → 409 (el editor ofrece cargar la otra versión o conservar la propia).

## 5. Flujo editorial

1. Abrir página → se carga el borrador (o se genera desde el CMS la primera vez).
2. Editar → historial deshacer/rehacer (120 pasos) + autoguardado (1,2 s).
3. Vista previa (`/admin/studio/{id}/vista-previa`) o enlace temporal compartible.
4. Comparar con lo publicado (canvas en solo lectura).
5. Publicar → valida + verifica referencias + crea `StudioVersion` + bitácora.
6. Restaurar → copia una versión al borrador; antes respalda el borrador si tenía cambios (`kind = snapshot`).

## 6. Integridad multimedia

`MediaUsageService` reporta usos en borradores y versiones publicadas de Studio. El borrado forzado limpia las referencias del borrador; las versiones publicadas son historial inmutable y el renderizador omite medios inexistentes (el sitio no se rompe). Publicar está bloqueado si falta un medio.

## 7. Añadir un bloque nuevo

1. Declararlo en `schema.json` (`elements` o `sections`).
2. Crear `components/blocks/elements/Block<Tipo>.vue` (o `sections/Section<Tipo>.vue`) y registrarlo.
3. Si es dinámico: resolver en `StudioResolver`.
4. Preset en `studio/presets.ts` e icono en `components/studio/icons.ts`.
5. Tests en `tests/Feature/Admin/StudioTest.php` y recorrido E2E (`tests/e2e`).

## 8. Bundles

El sitio público carga `PageRenderer` (≈35 kB) y CSS de bloques. `vue-draggable-plus`, inspector y editor viven en chunks propios de `admin/studio/Editor` (code splitting por página de Inertia).

## 9. Criterios de aceptación — verificación

Ver `tests/e2e/README.md`: el recorrido automatizado `studio-acceptance.mjs` cubre los criterios 1–28 con aserciones sobre el DOM real (tamaño, color, fuente, encuadre, composición, orden, duplicar/ocultar/eliminar, animación, dispositivos, guardado, recarga, vista previa, publicación, restauración y resultado público). El 30 (iPad) se cubre con el recorrido táctil en 1024×768.
