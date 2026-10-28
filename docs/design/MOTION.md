# ML Habitat — Sistema de movimiento y recorrido guiado

> Complementa `DESIGN.md` (§6 Movimiento). Runtime en
> `resources/js/lib/motion/**`, estilos en `resources/css/motion.css`,
> piezas visuales en `resources/js/components/web/visual/**`.

## 1. Principios

- **Sin dependencias.** Todo es TypeScript + CSS propio (IntersectionObserver,
  `requestAnimationFrame`, View Transitions API). No se añadió GSAP ni otra
  librería: lo que necesitamos (revelados, parallax, imán, inclinación,
  conteo) cabe en pocos KB y así no pagamos su peso en el LCP.
- **Solo `transform`, `opacity`, `filter` y `clip-path`.** Nada anima `top`,
  `height` ni `width` del diseño: sin CLS.
- **Mejora progresiva.** El estado oculto solo existe bajo
  `html.motion-ready .motion-scope`. Sin JS, sin IntersectionObserver o con
  movimiento reducido, **todo se ve completo y quieto**.
- **Movimiento reducido.** Con `prefers-reduced-motion: reduce` no se añade
  `motion-ready`, no hay parallax, imán, inclinación, marquesina ni
  transiciones de página; el cambio de preferencia se aplica en vivo.
- **Un listener por tipo.** Un `scroll` pasivo + un rAF para scroll/parallax;
  un `pointermove` delegado + un rAF para efectos de puntero; un
  MutationObserver para descubrir contenido nuevo. Todo se limpia al
  desmontar el layout.

## 2. Inicio

`PublicLayout.vue` llama a `createMotion(root)` al montarse. El contenedor
recibe la clase `motion-scope`; el runtime descubre por sí mismo los
elementos que aparecen después (navegación de Inertia, bloques del editor,
filtros, paginación). No hay que llamar nada desde las páginas.

```ts
import { createMotion } from '@/lib/motion';
const motion = createMotion(el); // { refresh(), destroy() }
```

## 3. API declarativa (atributos)

| Atributo              | Valores                                                                                                                             | Efecto                                                                                                                                                                                                         |
| --------------------- | ----------------------------------------------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `data-motion`         | `fade-up` · `fade-in` · `zoom-in` · `slide-left` · `slide-right` · `blur-in` · `mask-reveal` · `draw` · `split` · `settle` · `none` | Revelado al entrar en pantalla (una sola vez).                                                                                                                                                                 |
| `data-motion-delay`   | ms (máx. 4000)                                                                                                                      | Retraso del revelado.                                                                                                                                                                                          |
| `data-motion-stagger` | ms entre hijos (p. ej. `80`)                                                                                                        | Escalona a los **hijos directos**. El preset de los hijos es el `data-motion` del contenedor (o `fade-up`). El contenedor no se oculta. Los hijos que llegan después también se escalonan. Tope total ≈ 0.9 s. |
| `data-parallax`       | factor (`0.15`, `-0.06`)                                                                                                            | Desplazamiento vertical ligado al scroll (máx. ±160 px). No combinar con `data-motion` en el **mismo** elemento.                                                                                               |
| `data-countup`        | —                                                                                                                                   | Cuenta desde 0 hasta la cifra que **ya muestra** el texto ("12 años", "$1,250", "98 %"). Nunca inventa números; los lectores de pantalla oyen el valor final.                                                  |
| `data-magnetic`       | fuerza (`0.2`–`0.35`)                                                                                                               | El botón sigue ligeramente al cursor.                                                                                                                                                                          |
| `data-tilt`           | grados máx. (`4`–`6`)                                                                                                               | Inclinación 3D con brillo que sigue al cursor. Usa `::after`.                                                                                                                                                  |
| `data-spotlight`      | —                                                                                                                                   | Foco de luz cálida que sigue al cursor (bandas oscuras). Usa `::before`.                                                                                                                                       |
| `data-hover-zoom`     | —                                                                                                                                   | Lupa: la `<img>` interna se amplía desde el punto del cursor.                                                                                                                                                  |

Presets en detalle:

- `fade-up` / `fade-in` / `zoom-in` / `slide-left` / `slide-right` / `blur-in`: opacidad + transformación.
- `mask-reveal`: la imagen se descubre con `clip-path` y se asienta (radio con `--mask-radius`).
- `settle`: para la **imagen LCP**; se pinta de inmediato (opacidad 1) y solo se asienta desde una escala de 1.06. Nunca ocultar la imagen principal.
- `draw`: trazos SVG con `pathLength="1"` y `data-draw` se dibujan; los grupos `data-fill` aparecen al final (lo usa `LineIllustration`).
- `split`: palabras que suben desde una máscara (lo usa `SplitText`).

Ejemplos:

```html
<section data-motion="fade-up">…</section>
<ul data-motion-stagger="90" data-motion="zoom-in">
    …
</ul>
<img data-parallax="0.12" … />
<a data-magnetic="0.3" class="btn-nudge btn-sweep">…<svg class="nudge" /></a>
<p><span data-countup>15</span> años</p>
```

### Efectos ligados al scroll (por elemento)

| Atributo / clase                    | Efecto                                                                                                                                                                              |
| ----------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `data-scroll-progress`              | Escribe `--progress` (0–1) mientras el elemento cruza la vista (`cover`). El CSS lo expone como `--p` (vale 1 sin runtime: todo completo y quieto).                                 |
| `data-scroll-progress="track"`      | 0 → 1 mientras el elemento cruza la _línea de lectura_ (60 % de la vista). Cada `[data-scroll-step]` interno recibe `data-passed` al cruzarla (línea de tiempo de "Cómo funciona"). |
| `.scroll-fill-y` / `.scroll-fill-x` | Línea que se rellena con `--p` (`scaleY`/`scaleX`).                                                                                                                                 |
| `.scroll-move`                      | Deriva/giro dentro de un `data-scroll-progress`: `--move-x`, `--move-y`, `--spin` son la amplitud total. Solo `transform`.                                                          |
| `.scroll-step-dot` / `-body`        | Punto y texto de un paso: encendido por defecto; atenuado (nunca oculto) mientras no se ha leído. Colores con `--dot-on-*` / `--dot-off-*`.                                         |

Red de seguridad: ~2 s después de cada escaneo (y al terminar un scroll) todo
`[data-motion]` que ya quedó por encima del borde inferior se revela aunque
el IntersectionObserver no haya disparado. Ningún contenido queda oculto.

### Portada (editor visual) en el sitio público

`site-builder/compose.ts` ajusta el lienzo SOLO al publicarse (el editor ve
los bloques sueltos): botones contiguos de una fila forman un grupo que se
acomoda con `flex-wrap` (no se recortan ni se enciman) y el texto que
acompaña a un bloque alto (línea de tiempo, preguntas, pagos) se vuelve una
columna fija (`position: sticky`) desde 768 px. `SectionDecor.vue` añade
ornamento por tipo de sección (dibujos de línea, aros, cotas, plano) que se
mueve con el scroll; el hero suma una marquesina con las categorías.

### Directiva `v-reveal` (páginas propias)

Se importa localmente (no hay registro global):

```vue
<script setup lang="ts">
import { vReveal } from '@/lib/motion';
</script>
<template>
    <section v-reveal>…</section>
    <!-- fade-up -->
    <h2 v-reveal="'mask-reveal'">…</h2>
    <ul v-reveal="{ stagger: 70 }">
        …
    </ul>
    <!-- hijos escalonados -->
    <div v-reveal="{ preset: 'zoom-in', delay: 120 }">…</div>
</template>
```

### Clases utilitarias (`motion.css`)

| Clase                                  | Uso                                                                                                                                       |
| -------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------- |
| `link-underline`                       | Subrayado que se dibuja (también en `aria-current="page"`).                                                                               |
| `btn-nudge` + `.nudge` en el icono     | La flecha se adelanta en hover/foco. `.nudge-up` para ↗.                                                                                  |
| `btn-sweep` (+ `--sweep: <color>`)     | Relleno que barre el botón desde abajo.                                                                                                   |
| `eyebrow-rule`                         | Regla del antetítulo que crece al revelarse.                                                                                              |
| `tex-blueprint` / `tex-blueprint-dark` | Cuadrícula de plano (CSS puro).                                                                                                           |
| `tex-grain`                            | Veta de roble (SVG en línea, ~600 B).                                                                                                     |
| `dimension-line`                       | Línea de cota con topes.                                                                                                                  |
| `step-panel`                           | Entrada de un paso al mostrarse (`v-show`/`v-if`); con `data-direction="back"` en un ancestro entra desde la izquierda.                   |
| `motion-calm`                          | Envoltorio de páginas transaccionales: "pop" al elegir tarjetas/horarios (`aria-pressed`, `role=radio`) y entrada escalonada de horarios. |
| `check-in`                             | Aparición de una palomita de selección.                                                                                                   |

## 4. Transiciones de página

- Con View Transitions API (Chromium/Edge/Safari reciente): `navigation.ts`
  compone `config.visitOptions` de Inertia y pide `viewTransition: true` solo
  en visitas GET completas del sitio público (no en recargas parciales,
  filtros con `preserveState` ni envíos de formularios). La página saliente
  se desvanece hacia arriba (240 ms) y la nueva entra desde abajo (560 ms).
  La cabecera (`view-transition-name: site-header`) y el riel del recorrido
  se quedan quietos.
- Sin la API: `<main>` recibe `data-page-enter` y entra con CSS.
- Anclas de la misma página: desplazamiento suave con compensación de la
  cabecera, foco en el destino y actualización del hash. Si `#visita` no
  existe como id, se busca `[data-journey="visita"]`.

## 5. Recorrido guiado ("que te vaya guiando")

Historia de seis pasos (`lib/motion/journey.ts`):

| Clave       | Etiqueta          | Destino si no está en la página | Botón flotante                     |
| ----------- | ----------------- | ------------------------------- | ---------------------------------- |
| `inspirate` | Inspírate         | `/inspiracion`                  | Ver catálogo → `/catalogo`         |
| `espacio`   | Elige tu espacio  | `/catalogo`                     | Agenda tu asesoría → `/solicitar`  |
| `proceso`   | Así trabajamos    | `/#como-funciona`               | Agenda tu asesoría                 |
| `visita`    | Visita y medición | `/#visita`                      | Revisa tu cobertura → `/solicitar` |
| `pagos`     | Formas de pago    | `/#pagos`                       | Solicita tu propuesta              |
| `agenda`    | Agenda            | `/solicitar`                    | Solicitar asesoría                 |

**Cómo marcar secciones:** `data-journey="<clave>"` en el contenedor de la
sección (en orden de la historia). El paso actual es la última ancla cuyo
borde superior cruzó el 42 % de la ventana. Sin anclas, el paso sale de la
ruta (`/inspiracion*` → Inspírate, `/catalogo*` → Elige tu espacio,
`/preguntas*` → Así trabajamos, `/` → Inspírate).

**Para la portada (agente del editor):** añadir `data-journey` a los bloques
correspondientes: hero/proyectos → `inspirate`, categorías → `espacio`,
proceso → `proceso`, visita a domicilio/cobertura → `visita`, formas de
pago → `pagos`, CTA final → `agenda`. Si el bloque de visita tiene id
`visita` y el de pagos id `pagos`, los enlaces del riel desde otras páginas
llegan exactos.

**Afinar el botón por página:** `data-journey-cta-href` (y opcional
`data-journey-cta-label`) en cualquier elemento de `<main>` reemplaza el
destino cuando el paso apunta a `/solicitar` (p. ej. la categoría pasa
`/solicitar?categoria=closets`).

**Ocultar el botón:** se oculta solo cerca del pie, mientras un `<form>`
está en pantalla, con el menú móvil abierto, antes de recorrer ~35 % de la
primera pantalla, si la persona lo cierra (se recuerda en `sessionStorage`,
con try/catch) o si hay un elemento visible con `data-journey-hide` (p. ej.
la barra fija de "Solicita una asesoría" del producto en móvil).

**Piezas:**

- Escritorio (≥ 1280 px): riel vertical a la derecha. `<nav aria-label="Tu
recorrido por ML Habitat">`, `<ol>` de enlaces, el actual con
  `aria-current="step"` y "(estás aquí)" para lectores. Las etiquetas se ven
  en hover/foco y se asoman 2 s al cambiar de paso. Los pasos que no están
  en la página llevan ↗ y navegan.
- Móvil: barra segmentada de 3 px arriba (decorativa, `aria-hidden`); el
  texto "2/6" vive en el botón flotante, que es descartable (✕).
- Páginas transaccionales (`/solicitar`, `/visita`, `/asesoria`,
  `/precalificacion`, `/pagos`): sin riel ni botón (calma total).
- "Siguiente paso" (`visual/journey/NextStepTeaser.vue`) al final de cada
  página: catálogo → primera categoría → primer modelo → solicitud;
  inspiración → catálogo; preguntas → catálogo.

## 6. Piezas visuales (`components/web/visual`)

- `illustrations/LineIllustration.vue` + `furniture.ts` / `tools.ts`:
  ocho alzados de línea propios en lienzo 160×120 (cocina con isla, clóset,
  recámara, centro de entretenimiento, zapatera, flexómetro, plano,
  caja de herramientas). `illustrationFor(slug, nombre)` elige la de una
  categoría. Se dibujan al revelarse; decorativas salvo `labelled`.
- `icons.ts`: Lucide con trazo **1.5** y tokens de tamaño (`xs 14 · sm 16 ·
md 20 · lg 24 · xl 32`), `categoryIcon()` y `featureIcon()`.
- `IconBadge`, `SplitText`, `Marquee`, `SwatchFan`, `MaterialChips`,
  `GradientBlobs`, `BackToTop` (anillo con `--scroll-progress`),
  `BeforeAfter` (solo si la galería trae pies "Antes…" y "Después…"),
  `SuccessMark` (cierre del flujo de solicitud).

## 7. Accesibilidad (reglas)

1. Todo lo que se descubre con hover se descubre también con
   `:focus-visible` (y en táctil está visible de inicio).
2. Nada esencial depende del movimiento: los textos están en el DOM desde el
   primer pintado; `SplitText` incluye el texto completo en `sr-only`.
3. Efectos de puntero solo con `(hover: hover) and (pointer: fine)`.
4. Gestos con alternativa visible: el visor de galería se desliza en táctil
   y además tiene botones, flechas del teclado, Inicio/Fin y miniaturas.
5. Contraste AA en etiquetas sobre fotografía (fondo `black/45` mínimo).
6. Objetivos táctiles ≥ 44 px en controles nuevos.
7. Nunca `alert`, `confirm` ni `<select>` nativos.
8. Sin scroll horizontal: `.motion-scope { overflow-x: clip }` absorbe los
   desplazamientos de entrada (y no rompe `position: sticky`).
