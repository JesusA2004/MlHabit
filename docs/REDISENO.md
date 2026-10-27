# Rediseño MR. INSIGHT — Informe (2026-10-15)

## Incrementos
| # | Contenido | Estado | Verificación |
|---|---|---|---|
| 1 | Auditoría (`docs/design/AUDITORIA-UI.md`), 6 skills (`.claude/skills/mrinsight-*`), dirección visual, design system (`resources/css/app.css`, `blocks.css`), arquitectura de Studio | Hecho | Revisión manual |
| 2 | Sidebar agrupado y colapsable, cabecera, dashboard con datos reales, calendario editorial, componentes admin | Hecho | Pest + capturas 390/1440 |
| 3 | Sitio público: header, footer, inicio/historia/proceso/redes (Studio), tips, artículo, aviso, 404 | Hecho | Capturas 390/768/1024/1440 sin desbordes |
| 4 | Studio: árbol, canvas en iframe, selección, inspector, bloques, drag & drop + teclado | Hecho | E2E `studio-acceptance.mjs` |
| 5 | Biblioteca multimedia, títulos, imágenes (encuadre), videos, composiciones, responsive, animaciones | Hecho | E2E |
| 6 | Autoguardado, borrador con control de concurrencia, vista previa (y enlace firmado), publicación, versiones, restauración | Hecho | `StudioTest.php` (21 tests) + E2E |
| 7 | Accesibilidad, pruebas, seguridad, Pint/PHPStan, documentación | Hecho | 127 tests Pest, PHPStan 0 errores, vue-tsc 0 errores |

## Criterios de aceptación (sección 23)
1–28: verificados automáticamente en `tests/e2e/studio-acceptance.mjs` (PASS).
29 (publicaciones desde el CMS): `tests/Feature/Admin/PostManagementTest.php` y pantallas rediseñadas.
30 (iPad): `tests/e2e/studio-ipad.mjs` en 1024×768 y 768×1024 táctil (PASS; emulación en Chromium, no Safari real).

## Decisiones relevantes
- Inicio, Mi historia, Mi proceso y Redes se renderizan desde la versión publicada en Studio. Su primera versión se genera a partir del contenido existente del CMS, por lo que el sitio no pierde nada.
- Los textos y entradas de páginas siguen en «Páginas»; Studio los muestra por referencia (no duplica).
- `/admin/inicio` conserva destacados por categoría y frase del pie; los antiguos campos del hero se conservan en la base pero la portada ya se edita en Studio.
- Comentarios de visitantes: **no implementados** (requieren moderación, antispam y privacidad). Se ofrecen bloques editoriales: texto, cita destacada y pregunta al lector.
- Se permiten subir subtítulos `.vtt` (y `.txt`) a la biblioteca para la pista de subtítulos de video.

## Pendientes y limitaciones conocidas
- Referencias de Uizard: solo accesibles como texto; la vista previa de app.uizard.io no se renderizó. `Mr_Insight_Diseno_Premium.pdf` no estaba disponible.
- No hay capturas «antes»: el rediseño se aplicó antes de montar el navegador de pruebas.
- Edición de «Relacionadas» en publicaciones: el controlador no envía datos para elegirlas (el guardado ya no las borra).
- Tarjetas de 2FA/verificación de correo: Fortify no tiene esas funciones activas.
- Las fuentes extra (Playfair, Inter, etc.) se cargan desde Bunny Fonts bajo demanda.
- `.claude/` está en `.gitignore`: las skills existen localmente pero no se versionan salvo que se ajuste esa regla.

## Continuación (2026-10-17)
- **Skills recreadas.** Las seis skills `.claude/skills/mrinsight-*` (visual-design, ui-components, public-experience, admin-ux, visual-builder, motion-accessibility) se habían perdido (la carpeta `.claude/` no está versionada). Se regeneraron fundamentadas en el código real, cada una con frontmatter válido y las diez secciones: Objetivo, Alcance, Convenciones, Procedimientos, Reglas de diseño, Restricciones, Criterios de aceptación, Errores a evitar, Referencias técnicas y Cómo verificar.
- **Plantilla de publicación (tip) en Studio — terminada.** La página de un Tip (`/tips/{slug}`) se renderiza ahora con el motor de bloques: la plantilla de sistema `tip` enlaza sus bloques a la publicación actual (portada, título e intro por *bind*; cuerpo Tiptap por `page-body`). Bloques nuevos `post-meta` (categoría, fecha, tiempo de lectura) y `share` (compartir/copiar enlace); `SectionGallery` admite origen `post` (galería de la publicación); `SectionPosts` admite origen `related`. El editor y la vista previa resuelven la plantilla contra una publicación de muestra. Verificado de extremo a extremo (validación de esquema, render con post real, `vue-tsc` y build en verde) y en `PublicSiteTest`/`StudioTest` actualizados.
- **Módulo de estadísticas (nuevo).** Registro anónimo de visitas sin cookies ni IP (hash de visitante rotativo diario) vía middleware `TrackPageView`; servicio `SiteAnalytics` (totales, únicos, serie diaria, publicaciones y páginas más vistas, referidores, variación vs. periodo anterior); pantalla `/admin/estadisticas` con KPIs, gráfica `TrendChart`, rankings y selector 7/30/90 días; widget "Audiencia" en el Dashboard; entrada en el sidebar (General). Datos demo en `DemoContentSeeder`. Pruebas: `tests/Feature/Public/PageViewTrackingTest.php` y `tests/Feature/Admin/AnalyticsTest.php`. Suite completa: 133 pasan, 3 omitidas.
