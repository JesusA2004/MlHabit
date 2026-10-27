# Dirección visual — MR. INSIGHT

## Skills operativas (`.claude/skills/mrinsight-*`)
Esta dirección se aplica mediante seis skills reutilizables que Claude Code invoca según la tarea:
`mrinsight-visual-design` (dirección de arte y rúbrica de calidad), `mrinsight-ui-components` (componentes Vue/shadcn sin duplicados), `mrinsight-public-experience` (sitio público por bloques), `mrinsight-admin-ux` (panel SaaS), `mrinsight-visual-builder` (Studio) y `mrinsight-motion-accessibility` (movimiento + accesibilidad). Cada skill está fundamentada en el código real y define objetivo, reglas, restricciones, criterios de aceptación y cómo verificar.

## Concepto: "Editorial nocturno"
Revista digital premium leída de noche: fondo carbón profundo, papel marfil para el texto, oro viejo como subrayado de ideas. La fotografía es protagonista; la tipografía display (Fraunces) da voz; Instrument Sans mantiene la lectura limpia. El panel administrativo es la "sala de redacción": clara, ordenada, con la misma tinta y el mismo oro como acentos.

## Principios
1. **Voz antes que decoración** — cada sección dice una cosa, con un titular fuerte.
2. **Ritmo editorial** — alternar densidad: hero amplio → bloque denso → respiro → mosaico.
3. **Imagen con encuadre** — relaciones fijas (4:5, 3:2, 16:9), punto focal controlado.
4. **Oro con moderación** — acento ≤ 10 % de la superficie: eyebrows, subrayados, CTA primario.
5. **Movimiento que acompaña** — entradas suaves de 600–800 ms, nunca bloqueantes.

## Paleta (tema inicial, personalizable en Apariencia)
| Token | Hex | Uso |
|---|---|---|
| ink | #0D0E10 | fondo público, texto admin |
| ink-soft | #16181B | superficies públicas |
| ink-raised | #1D2024 | superficies elevadas públicas |
| paper | #F4F1EB | texto público, fondo admin (variante) |
| gold | #C7AA7D | acento |
| gold-soft | #D9C4A1 | hover de acento |
| mist | #ABA9A3 | texto secundario |
| line | paper 10 % | divisores |

Admin claro: fondo `#F7F5F0`, tarjetas `#FFFFFF`, borde `#E7E2D8`, texto `#0D0E10`, secundario `#6B6862`, primario tinta, acento oro. Admin oscuro: fondo `#0D0E10`, tarjetas `#16181B`, borde `#26292E`, texto `#F4F1EB`, primario marfil.

## Tipografía
| Estilo | Familia | Tamaño (móvil → desktop) | Interlineado | Tracking |
|---|---|---|---|---|
| display-xl | display 600 | 44 → 96 px | 0.98 | -0.02em |
| display-lg | display 600 | 38 → 72 px | 1.02 | -0.015em |
| display-md | display 500 | 30 → 48 px | 1.08 | -0.01em |
| title | display 500 | 22 → 28 px | 1.2 | 0 |
| lead | sans 400 | 18 → 22 px | 1.5 | 0 |
| body | sans 400 | 16 → 18 px | 1.65 | 0 |
| eyebrow | sans 600 | 11 → 12 px | 1.2 | 0.24em, mayúsculas |
| caption | sans 400 | 12 → 13 px | 1.4 | 0.02em |

## Sitio público — sistema de composiciones
- **Hero**: editorial (7/5), split, cinematográfico (fondo foto/video + overlay), centrado minimal, asimétrico, enmarcado (arco), video protagonista.
- **Artículos**: tarjetas editoriales, revista asimétrica (1 grande + 2), destacado + lista, carrusel, lista numerada.
- **Galerías**: grid, bento, masonry, carrusel, fotografía protagonista.
- **Historia**: imagen + texto, timeline vertical, timeline alterno, capítulos visuales.
- **Llamadas a la acción**: banda dorada, tarjeta, split con foto.
- **Bloques editoriales**: cita destacada, pregunta al lector, texto editorial, divisor con ornamento.

## Administración
- Sidebar agrupado, colapsable, con marca.
- Encabezados de pantalla con título, descripción y acciones.
- KPIs con cifra en display, tendencia y contexto.
- Formularios en secciones de dos columnas (descripción izquierda, campos derecha) en ≥ 1024 px.

## Studio
- Interfaz oscura neutra (para no competir con el canvas), tres paneles: Estructura (280 px), Canvas, Propiedades (320 px).
- Barra superior: página, dispositivo (Escritorio 1440 / Laptop 1024 / Tablet 768 / Móvil 390), deshacer/rehacer, estado de guardado, Vista previa, Publicar.
- Selección: contorno oro 2 px + etiqueta del tipo; hover contorno 1 px punteado.
