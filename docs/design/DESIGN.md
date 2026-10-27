# ML Habitat — Sistema de diseño

> Fuente única de verdad visual. Los valores viven como variables CSS en
> `resources/css/app.css`; las páginas usan **nombres de token** (`bg-surface`,
> `text-muted-foreground`, `bg-primary`), nunca hex sueltos.

## 1. Dirección

ML Habitat diseña, fabrica e instala muebles a medida: cocinas integrales,
clósets, recámaras, centros de entretenimiento y zapateras. La interfaz debe
sentirse como **un buen taller de carpintería con showroom**: materiales
honestos, luz natural, orden y oficio. No es una fintech, un ERP ni un
"panel de IA".

**Palabras guía:** vivienda · diseño · confianza · calidad · modernidad ·
calidez · aspiración alcanzable.

**Referentes de ánimo (no copiar):** plantillas de landing de uizard.io para
startups — mucho aire, jerarquía editorial, fotografía protagonista — y
showrooms de mobiliario escandinavo (orden, materiales a la vista).

> Nota: esta dirección no se contrastó con una biblioteca de sitios reales
> (UXKIN no está conectado); se basa en el brief y en los referentes citados.

**Firma visual (un solo gesto memorable):** el _muestrario de acabados_ —
franjas de lino, roble, eucalipto y espresso como las muestras de chapa y
laminado que un asesor pone sobre la mesa. Aparece en el monograma, en el
panel de acceso, en las páginas de error y como acento fino en marketing.
Todo lo demás es silencioso y disciplinado.

## 2. Color

Neutros cálidos de **lino y piedra**, texto **espresso**, un único color de
acción **eucalipto profundo** y el **roble** como acento material (nunca
para texto pequeño ni acciones).

**¿Por qué eucalipto y no terracota?** El verde profundo evoca madera
natural, plantas de interior y estabilidad; transmite confianza sin el frío
del azul corporativo. La terracota se confunde con los tonos de _peligro_ y
_advertencia_ y es el acento por defecto de mil plantillas "cálidas".

### Paleta (única: no hay modo oscuro)

Toda la aplicación —sitio, portal, panel, técnicos y cuenta— usa solo esta
paleta clara (`color-scheme: light`). El modo oscuro se retiró por decisión
del negocio: no hay clase `.dark`, variante `dark:` ni preferencia de
apariencia.

| Token                          | Hex       | Rol                                                                       |
| ------------------------------ | --------- | ------------------------------------------------------------------------- |
| `background`                   | `#F7F5F2` | Lienzo general (lino).                                                    |
| `surface` / `card` / `popover` | `#FFFFFF` | Superficies elevadas: tablas, tarjetas, diálogos.                         |
| `muted` / `secondary`          | `#EFEBE5` | Piedra: zonas hundidas, filas alternas, chips neutros.                    |
| `foreground`                   | `#231E1A` | Texto principal (espresso). 15.2:1 sobre lino.                            |
| `muted-foreground`             | `#6B635A` | Texto secundario, ayudas. 5.4:1 sobre lino.                               |
| `border`                       | `#E3DDD4` | Bordes y divisores.                                                       |
| `input`                        | `#D6CFC4` | Borde de campos.                                                          |
| `primary`                      | `#2F5A4C` | Eucalipto: botón primario, enlaces, selección, foco. Blanco encima 7.8:1. |
| `primary-hover`                | `#264A3F` | Hover/pressed del primario.                                               |
| `primary-soft`                 | `#E5EEE9` | Fondo de elemento activo (navegación, día elegido en rango).              |
| `oak`                          | `#B07D4F` | Roble: monograma, gráficas, reglas finas, marcadores. **Sin texto**.      |
| `oak-ink`                      | `#8A6A4A` | Roble legible (≥4.5:1) para cifras o detalles tipográficos.               |
| `espresso`                     | `#2B2420` | Bloques oscuros de marketing (footer, banda CTA).                         |
| `ring`                         | `#2F5A4C` | Anillo de foco.                                                           |

### Estados semánticos (`StatusBadge`, alertas, timeline)

Cada tono tiene tres tokens: `--{tono}` (sólido / iconos), `--{tono}-soft`
(fondo de chip) y `--{tono}-ink` (texto sobre el fondo suave, ≥6.6:1).

| Tono      | Sólido    | Soft      | Ink       | Uso                                       |
| --------- | --------- | --------- | --------- | ----------------------------------------- |
| `success` | `#2E7D4F` | `#E4F2E8` | `#1E5A37` | Pagado, instalado, aprobado.              |
| `warning` | `#9A5B08` | `#FBF0DC` | `#7A4A06` | Pendiente, por vencer, requiere atención. |
| `danger`  | `#B3372B` | `#FBE7E4` | `#8C2A20` | Vencido, rechazado, incidencia abierta.   |
| `info`    | `#2F6690` | `#E3EDF6` | `#234E70` | Programado, en proceso, informativo.      |
| `neutral` | `#6B635A` | `#EFEBE5` | `#4A433C` | Borrador, cerrado, sin estado.            |
| `brand`   | `#2F5A4C` | `#E5EEE9` | `#264A3F` | Destacado propio de ML Habitat.           |

**Regla:** el color nunca es el único portador de significado; los chips
llevan texto y, cuando aplica, icono.

## 3. Tipografía

| Familia              | Rol                                                                                                                                           | Pesos              |
| -------------------- | --------------------------------------------------------------------------------------------------------------------------------------------- | ------------------ |
| **Familjen Grotesk** | Display: titulares de marketing, títulos de página, cifras grandes. Grotesca con remates de tinta y carácter escandinavo; firme sin ser fría. | 500, 600, 700      |
| **Figtree**          | Texto e interfaz: cuerpo, formularios, tablas, navegación. Cálida, abierta, excelente en acentos del español.                                 | 400, 500, 600, 700 |

Se **autoalojan** (sin peticiones a terceros): WOFF2 variables en
`resources/fonts/web` (`@font-face` en `app.css`, `font-display: swap`,
precarga del subconjunto latin en `app.blade.php`). Los TTF estáticos de
`resources/fonts` se usan para generar las imágenes de vista previa (Open
Graph). Licencia SIL OFL 1.1 (`resources/fonts/OFL-*.txt`).

**Escala** (`rem` / interlineado):

| Token             | Tamaño                  | Interlineado | Uso                                       |
| ----------------- | ----------------------- | ------------ | ----------------------------------------- |
| `text-display-xl` | clamp(2.75 → 4.75rem)   | 1.02         | H1 de hero.                               |
| `text-display-lg` | clamp(2.125 → 3.25rem)  | 1.08         | H2 de sección.                            |
| `text-display-md` | clamp(1.625 → 2.125rem) | 1.15         | Títulos de auth, error, bloques.          |
| `text-2xl`        | 1.5rem                  | 1.25         | Título de página del panel (display 600). |
| `text-lg`         | 1.125rem                | 1.6          | Entradilla.                               |
| `text-base`       | 1rem                    | 1.6          | Cuerpo. Mínimo en móvil.                  |
| `text-sm`         | 0.875rem                | 1.45         | Interfaz densa: tablas, menús.            |
| `text-xs`         | 0.75rem                 | 1.4          | Metadatos, chips.                         |

- Titulares en display con `letter-spacing: -0.02em`; cuerpo sin tracking.
- Nada de mayúsculas sostenidas con tracking para etiquetas; las etiquetas de
  grupo van en _sentence case_, `text-xs font-medium text-muted-foreground`.
- Cifras (dinero, fechas, folios) con `tabular-nums`.
- Líneas de lectura ≤ 70 caracteres (`max-w-prose`/`max-w-2xl`).

## 4. Espacio y layout

- Escala base de 4 px (la de Tailwind). Ritmo habitual: 8 · 12 · 16 · 24 · 32 · 48 · 64 · 96.
- **Panel:** padding de página 16 px (móvil) / 24 px / 32 px (≥1024). Ancho de contenido máximo 1280 px (`max-w-7xl`).
- **Marketing:** contenedor `container-site` de 1200 px con gutter `clamp(16px, 4vw, 40px)`; separación entre secciones `clamp(64px, 10vw, 128px)`.
- Alineación a la izquierda por defecto; centrado solo en estados vacíos, error y CTA final.
- Objetivos táctiles de 44 px en móvil (botones `h-11`, filas de navegación `min-h-11`).

## 5. Forma y profundidad

| Token        | Valor | Uso                                |
| ------------ | ----- | ---------------------------------- |
| `radius-sm`  | 6 px  | Chips cuadrados, checkboxes, tags. |
| `radius-md`  | 8 px  | Botones, campos, items de menú.    |
| `radius-lg`  | 10 px | Tarjetas, tablas, popovers.        |
| `radius-xl`  | 14 px | Diálogos, hojas, `SectionCard`.    |
| `radius-2xl` | 20 px | Marcos de fotografía en marketing. |
| `full`       | —     | Avatares y `StatusBadge`.          |

Radio por jerarquía, no uno para todo: el contenedor siempre es igual o más
redondo que lo que contiene.

**Sombras** (tintadas en espresso, nunca gris puro):

- `shadow-xs` `0 1px 2px rgb(35 30 26 / .05)` — superficies en reposo (junto con borde).
- `shadow-sm` `0 1px 3px rgb(35 30 26 / .07), 0 1px 2px rgb(35 30 26 / .04)` — tarjeta interactiva en hover.
- `shadow-lg` `0 12px 32px -8px rgb(35 30 26 / .16), 0 4px 8px -4px rgb(35 30 26 / .06)` — popovers, menús, diálogos.

Bordes primero, sombra después: las superficies se separan con `border`
de 1 px; la sombra solo indica que algo _flota_.

## 6. Movimiento

| Token             | Valor                            | Uso                  |
| ----------------- | -------------------------------- | -------------------- |
| `--ease-out`      | `cubic-bezier(0.22, 1, 0.36, 1)` | Entradas, aperturas. |
| `--ease-in-out`   | `cubic-bezier(0.65, 0, 0.35, 1)` | Cambios de estado.   |
| `--duration-fast` | 120 ms                           | Hover, color.        |
| `--duration-base` | 200 ms                           | Popovers, toggles.   |
| `--duration-slow` | 320 ms                           | Hojas, drawers.      |

- El movimiento responde a una acción (abrir, confirmar, subir). Sin
  fade-in decorativo en cada sección.
- Un único momento orquestado por pantalla de marketing (la entrada del hero).
- `prefers-reduced-motion: reduce` reduce todas las transiciones a ~0.

## 7. Iconografía

- **Lucide** (`@lucide/vue`), trazo 1.75–2, tamaños 16 (interfaz), 20 (navegación inferior), 24 (vacíos).
- Íconos acompañan texto; si van solos, llevan `aria-label` o `sr-only`.
- Sin emoji como iconos.

## 8. Fotografía

- Protagonista en marketing: espacios reales terminados, luz natural lateral,
  encuadres a la altura del ojo, detalles de herrajes y cantos.
- Paleta fotográfica cálida y desaturada (madera, piedra, blanco roto); evitar
  filtros fríos y renders 3D brillantes.
- Proporciones: hero 16:9 o 4:5 en móvil; galerías 4:3; detalles 1:1.
- Marcos `radius-2xl`, sin bordes ni sombras sobre la foto.
- Nunca stock genérico de personas sonriendo a cámara. Si no hay foto, se usa
  el muestrario de acabados (`ds/FinishSwatches.vue`), no un placeholder gris.
- Todas las imágenes con `alt` descriptivo en español.

## 9. Componentes (reglas)

- **Botones:** primario (eucalipto, uno por vista), secundario (`outline`
  sobre superficie), quieto (`ghost`) y destructivo. Etiquetas con verbo:
  "Guardar cambios", "Agendar visita". Estados hover, foco (anillo de 3 px
  `ring/40` + borde), deshabilitado (50 % opacidad) y cargando (spinner + verbo
  en gerundio).
- **Campos:** etiqueta siempre visible, ayuda debajo, error en `danger-ink`
  con `aria-describedby`.
- **Tablas → tarjetas:** `DataView` muestra tabla en ≥768 px y tarjetas apiladas
  en móvil, desde las mismas columnas.
- **Confirmaciones:** siempre `ConfirmDialog` (AlertDialog); nunca
  `window.confirm`.
- **Formularios de alta/edición:** `FormSheet` (hoja lateral en escritorio,
  drawer inferior en móvil).
- **Estados vacíos:** explican qué falta y ofrecen la acción siguiente.
- **Toasts:** `vue-sonner`, mismo verbo que la acción ("Cotización enviada").
- **Navegación del panel:** agrupada, filtrada por permisos, colapsable a
  riel de iconos; activo = fondo `primary-soft` + texto `primary`.
- **Marketing:** cabecera transparente sobre hero (`overlay`) que se vuelve
  sólida con desenfoque al hacer scroll; CTA "Solicitar asesoría".

## 10. Voz

Español de México, tú, frases cortas y concretas. Explica qué pasó y qué
hacer. Los errores no se disculpan ni son vagos. Los nombres de las cosas son
los del cliente: "cita", "visita", "anticipo", "instalación", no
"evento", "registro" o "transacción".
