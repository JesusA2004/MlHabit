# Decisiones comerciales pendientes y supuestos

Nada de lo siguiente está fijo en código: todo es configurable. Los valores por defecto existen solo para desarrollo y deben confirmarse con Dirección.

| Tema                                                         | Dónde se configura                             | Default de desarrollo                                                            | Estado                                         |
| ------------------------------------------------------------ | ---------------------------------------------- | -------------------------------------------------------------------------------- | ---------------------------------------------- |
| Tarifa de visita técnica ($1,000 o $1,500 MXN)               | Admin → Visitas → Política (versionada)        | **Ninguna política activa**: el checkout público queda cerrado hasta activar una | Pendiente                                      |
| Visita acreditable (sí/no, % o monto, vigencia, condiciones) | Política de visita                             | —                                                                                | Pendiente                                      |
| Políticas de cancelación y reprogramación de visita          | Política de visita (texto)                     | —                                                                                | Pendiente (revisión legal)                     |
| % de anticipo                                                | Admin → Configuración → Comercial              | 10% (se congela por contrato)                                                    | Confirmar                                      |
| IVA y si los precios lo incluyen                             | Configuración → Comercial                      | 16%, incluido                                                                    | Confirmar con contabilidad                     |
| Vigencia de cotizaciones                                     | Configuración → Comercial                      | 15 días                                                                          | Confirmar                                      |
| Asesoría inicial (gratuita, duración, canales)               | Configuración → Asesoría                       | Gratuita, 30 min, video/teléfono/WhatsApp                                        | Confirmar                                      |
| Zonas de cobertura operativa / CP                            | Admin → Cobertura                              | Ninguna                                                                          | Pendiente (sin cobertura no se venden visitas) |
| Texto publicitario de alcance                                | Sitio web → Portada → Cobertura                | Neutro                                                                           | Pendiente                                      |
| Precios orientativos de catálogo                             | Catálogo (por producto, oculto por defecto)    | Ocultos                                                                          | Pendiente                                      |
| Opciones de pago a plazos (tasas, plazos, intereses)         | Contenido + precalificación manual             | Sin cifras; "sujeto a evaluación"                                                | Pendiente (no hay motor automático)            |
| Garantías (plazos y alcances)                                | Admin → Garantías por proyecto                 | Sin plantilla fija                                                               | Pendiente                                      |
| Tarifarios de instalación                                    | Admin → Tarifarios (versionados)               | Ninguno                                                                          | Pendiente                                      |
| Política de 3D Secure de Openpay                             | `OPENPAY_3DS_MODE`                             | Ver docs/pagos/OPENPAY-INTEGRACION.md                                            | Confirmar con Openpay                          |
| Textos legales (aviso de privacidad, términos)               | Sitio web → Páginas legales                    | Borradores NO publicados                                                         | Pendiente (revisión legal)                     |
| 2FA obligatorio para personal                                | Configuración → Seguridad                      | Opcional                                                                         | Confirmar                                      |
| Testimonios                                                  | Sitio web (requiere consentimiento registrado) | Ninguno; la sección se oculta                                                    | Pendiente                                      |

## Supuestos técnicos

- El proyecto (Project) nace cuando el prospecto pasa a medición/diseño, porque diseño y cotizaciones necesitan un contenedor estable; se considera "contratado" al aceptar cotización y firmar contrato (`contracted_at`).
- La aprobación del cliente de un diseño no sustituye la aprobación técnica: fabricación exige `technical_approved_at`.
- No se registra GPS de técnicos (no existe base operativa/privacidad para hacerlo).
