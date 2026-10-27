# Catálogo de componentes — MR. INSIGHT

Regla: antes de crear un componente, buscarlo aquí. Si existe, se extiende con props/variantes.

## Tokens (resources/css/app.css)
- Público: `bg-ink`, `bg-ink-soft`, `bg-ink-raised`, `text-paper`, `text-gold`, `bg-gold-soft`, `text-mist`, `border-line`.
- Admin (claro/oscuro): `bg-background`, `bg-card`, `text-foreground`, `text-muted-foreground`, `bg-primary`, `bg-brand`, `text-brand`, `bg-success`, `bg-warning`, `bg-destructive`, `border-border`, `ring-ring`.
- Tipografía pública: `.type-display-xl|lg|md`, `.type-title`, `.type-lead`, `.type-body`, `.type-eyebrow`, `.type-caption`.
- Contenedores: `.container-editorial` (`--narrow`, `--wide`, `--full`).
- Botones públicos: `.btn-editorial` + `--primary | --secondary | --link`.
- Microinteracciones: `.link-underline`, `.hover-lift`, `.img-zoom`.
- Tonos de sección: `.tone-ink | tone-ink-soft | tone-paper | tone-gold` (definen `--sn-bg/fg/muted/accent/line/card`).
- Espaciado de sección: `.spacing-compact | balanced | editorial | wide | cinematic`.

## Sitio público y Studio (`components/blocks`)
| Componente | Uso |
|---|---|
| `PageRenderer` | Renderiza un documento de Studio (público, vista previa, canvas). |
| `SectionView`, `SlotView`, `ElementView` | Envoltorios con `data-n`, tono, espaciado, movimiento. |
| `sections/SectionHero` | 7 composiciones: editorial, split, cinematic, centered, asymmetric, framed, video. |
| `sections/SectionColumns` | one, two, two-wide-left, two-wide-right, three, bento (+ modo tarjetas). |
| `sections/SectionPosts` | cards, magazine, spotlight, carousel, list (feed dinámico del CMS). |
| `sections/SectionCategories` | cards, list, bento («Empieza aquí»). |
| `sections/SectionGallery` | grid, bento, masonry, carousel, spotlight. Origen `manual` (fotos elegidas) o `post` (galería de la publicación actual). |
| `sections/SectionTimeline` | vertical, alternating, chapters (entradas del CMS o capítulos manuales). |
| `sections/SectionCta` | band, card, split. |
| `sections/SectionSocial` | cards, list, pills. |
| `elements/Block*` | eyebrow, heading, text, buttons, image, video, quote, question, divider, spacer, social-links, page-body, **post-meta** (categoría/fecha/tiempo de lectura de la publicación), **share** (compartir/copiar enlace), unsupported. |
| `shared/EditorialCard` | **Única** tarjeta de artículo: default, feature, horizontal, overlay. |
| `shared/SectionHeader` | Antetítulo + título + enlace de sección. |
| `shared/Carousel` | Carrusel accesible por scroll-snap (botones + teclado, sin autoplay). |
| `public/SiteHeader`, `SiteFooter`, `StudioPageView`, `PostBody`, `SocialIcon` | Estructura pública. |

## Administración (`components/admin`)
| Componente | Uso |
|---|---|
| `AdminPageHeader` | Título, descripción, antetítulo y acciones de cada pantalla. |
| `StatCard` | KPI con cifra display, ícono y tono. |
| `BarChart` | Gráfica de barras de una serie, accesible (tooltip + tabla). |
| `TrendChart` | Gráfica de líneas/área de dos series (visitas + únicos) para Estadísticas; SVG sin dependencias, theme-aware, tooltip + tabla accesible. |
| `EmptyState` | Estado vacío con acción. |
| `FormSection` | Sección de formulario en dos columnas. |
| `StatusBadge` | Estado: draft, scheduled, published, archived, changes, unpublished. |
| `MediaPickerDialog` | Biblioteca multimedia (buscar, filtrar, subir, arrastrar, elegir). |

## Studio (`components/studio`)
| Componente | Uso |
|---|---|
| `StructurePanel` | Árbol con arrastrar y soltar + botones/teclado; paleta de bloques. |
| `CanvasFrame` | Iframe con ancho de dispositivo y escala. |
| `InspectorPanel` | Pestañas Contenido · Diseño · Estilo · Movimiento · Responsive · Avanzado. |
| `PropField`, `StyleField` | Controles generados desde `schema.json`. |
| `controls/SegmentedControl`, `SliderControl`, `ColorControl`, `MediaControl`, `LinkControl`, `LayoutPicker`, `FocalPointControl`, `PresetThumb` | Controles reutilizables del inspector. |
| `AddBlockDialog`, `PublishDialog`, `VersionsSheet`, `PageSettingsDialog` | Flujos del editor. |

## Primitivos (`components/ui`, shadcn-vue)
alert, alert-dialog, avatar, badge, breadcrumb, button, card, checkbox, collapsible, dialog, dropdown-menu, input, label, navigation-menu, select, separator, sheet, sidebar, skeleton, sonner, spinner, switch, table, textarea, toggle, tooltip.
