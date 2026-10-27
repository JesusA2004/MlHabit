# Auditoría UI/UX — MR. INSIGHT (estado previo al rediseño)

Fecha: 2026-10-15 · Alcance: `resources/js/pages/{public,admin}`, `components`, `layouts`, `resources/css/app.css`.

## Limitaciones de referencias
- `https://uizard.io/` y `.../startup-landing-page/`: accesibles solo como **texto extraído** (sin capturas, CSS ni medidas). Se tomaron patrones estructurales: hero breve con valor + CTA, secciones modulares duplicables, galerías de imagen protagonistas, testimonios en tarjeta, footer por columnas.
- `https://app.uizard.io/templates/yxnWe9j4EXHP45E0Q6qV/preview`: aplicación JS que **no se renderizó**; no se pudo ver. No se afirma nada sobre su diseño.
- `Mr_Insight_Diseno_Premium.pdf`: **no se encontró** en el repositorio ni en el equipo (búsqueda en el perfil de usuario y `C:\wamp64\www`). La dirección parte de la identidad ya implementada (colores, Fraunces + Instrument Sans, voz editorial "Sin tapujos").

## Hallazgos — sitio público
| # | Problema | Dónde | Severidad |
|---|---|---|---|
| P1 | Composición monótona: 4 de 5 secciones de Home son "título + grid de 3 tarjetas" o split simple con el mismo `py-20` | `Home.vue` | Alta |
| P2 | Hero sin variedad ni control: única composición split, sin video, sin overlay, alt fijo "Zuriel Ávila" | `Home.vue` | Alta |
| P3 | Placeholders técnicos visibles al público ("Se administra desde /admin/inicio") | Home, MiHistoria, MiProceso | Alta |
| P4 | Tarjetas idénticas en Tips, Home y relacionados (markup copiado 3 veces) | Tips, Home, PostShow | Media |
| P5 | Mi historia renderiza solo `intro`: el `body` (Tiptap) del CMS **no se muestra** | `MiHistoria.vue` | Alta |
| P6 | Apariencia permite Playfair/Cormorant/Inter/Work Sans pero **no se cargan** las fuentes → caen a la fuente del sistema | `PublicLayout.vue` | Alta |
| P7 | Escala tipográfica incompleta (solo `.text-headline`); tamaños sueltos `text-2xl sm:text-3xl` | global | Media |
| P8 | Header siempre opaco; sin "saltar al contenido"; sin estado activo de navegación | `SiteHeader.vue` | Media |
| P9 | Sin movimiento de entrada ni microinteracciones salvo zoom de imagen | global | Baja |
| P10 | Mi proceso: timeline plano con miniaturas de 80 px; acordeón sin `aria-expanded` | `MiProceso.vue` | Media |
| P11 | Aviso de privacidad no muestra `body` del CMS | `AvisoPrivacidad.vue` | Media |

## Hallazgos — administración
| # | Problema | Dónde | Severidad |
|---|---|---|---|
| A1 | Sidebar plano de 10 ítems sin agrupar; etiqueta "Panel" duplicada | `AppSidebar.vue`, `NavMain.vue` | Alta |
| A2 | Tema shadcn gris por defecto: el admin no comparte nada de la marca | `app.css` | Alta |
| A3 | Dashboard: 8 tarjetas KPI idénticas (texto + número), sin actividad, sin medios recientes, sin almacenamiento ni calendario | `Dashboard.vue` | Alta |
| A4 | Sin encabezado de pantalla consistente: cada página arma su `h1` distinto | admin/* | Media |
| A5 | Edición de Inicio separada de la composición real (formulario de textos sin vista del resultado) | `inicio/Form.vue` | Alta |
| A6 | Apariencia: formulario largo de una columna con preview pequeña | `apariencia/Form.vue` | Media |
| A7 | Selector multimedia sin filtros por tipo visibles, sin metadatos, sin estado de procesamiento | `MediaPickerDialog.vue` | Media |
| A8 | No existe constructor visual ni versiones de páginas | — | Crítica |
| A9 | No hay calendario editorial pese a existir `scheduled_at` | — | Media |

## Decisiones
1. Crear tokens de marca para admin (claro/oscuro) y escala tipográfica pública completa.
2. Introducir **Studio** como fuente de composición de Inicio, Mi historia, Mi proceso, Redes y páginas personalizadas, reutilizando Media/Posts/Categories/Pages/SocialLinks por referencia.
3. Reemplazar los placeholders técnicos públicos por estados vacíos editoriales.
4. Unificar tarjetas en componentes editoriales reutilizables.
5. Cargar dinámicamente las fuentes elegidas en Apariencia/Studio (Bunny Fonts, lista blanca).
