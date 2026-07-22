# Preparación para Google Play — 2026-07-16

Basado en documentación oficial: requisitos de target API (developer.android.com/google/play/requirements/target-sdk, consultado 2026-07-16) y política de eliminación de cuentas (support.google.com answer/13327111).

## Técnico

| Punto | Estado | Detalle |
|---|---|---|
| Target/compile SDK | ✅ | Flutter 3.41.9 produce targetSdk=36 / compileSdk=36. Cumple el requisito para apps nuevas incluso tras el corte del 31-ago-2026 (API 36) |
| Permisos | ✅ | Solo `INTERNET` y `com.android.vending.BILLING` — mínimos, nada que eliminar |
| Package name | ✅ | `com.codeshore.biblejourney` (quitar el comentario TODO del build.gradle.kts) |
| Versionado | ✅ | 1.0.14+14 (pubspec); versionCode desde Flutter |
| Firma | ✅ | `key.properties` + keystore presentes y git-ignored. ⚠️ **Respaldar el keystore fuera de la máquina** (pérdida = no poder actualizar la app). Recomendado: inscribirse en Play App Signing al crear la ficha |
| Build release | ✅ verificado en esta auditoría (`flutter build apk --release`) — para subir a Play usar `flutter build appbundle --release` (mismo pipeline, formato AAB requerido) |
| Deep links | N/A | La app no declara ni usa deep links (solo constante de share, vacía). Sin verificación de App Links pendiente |
| Comportamiento en segundo plano | ✅ | Sin servicios en background, sin ubicación, sin alarmas |
| Crash reporting | ⚠️ | No hay. Recomendado para operar el lanzamiento (decisión pendiente R-13 — si se agrega, actualizar Data Safety) |

## Políticas

| Punto | Estado | Detalle |
|---|---|---|
| Política de privacidad | ✅ | Publicada en `https://biblejourney-api.codeshore.net/privacy` (es/en). Declararla en la ficha |
| Eliminación de cuenta — in-app | ⚠️ **P1 pendiente** | Backend listo (`DELETE /api/me`, implementado en esta auditoría). Falta la pantalla en la app (Ajustes → Eliminar cuenta) |
| Eliminación de cuenta — recurso web | ⚠️ **P1 pendiente** | Falta página web pública de solicitud (puede vivir junto a /privacy). Requerida por política aunque exista el flujo in-app |
| Data Safety | ⚠️ Pendiente de llenar | Borrador en `docs/audits/privacy-data-map.md`: recolecta nombre/email (cuenta), estado de suscripción; sin analytics ni ads; HTTPS; con mecanismo de borrado. Nota: google_fonts descarga fuentes en runtime (o empaquetarlas como assets) |
| Compras/suscripciones | ⚠️ Bloqueado por R-06 | RevenueCat sin configurar (productos en Play Console, entitlement, webhook secret real). El paywall existe; sin esto la compra individual no funciona |
| Contenido — clasificación | Pendiente | Cuestionario IARC: app de lectura/religión, sin UGC público, apta para todos. Ezra (IA generativa) — revisar cuestionario de funciones de IA vigente en Play Console al llenar |
| Contenido sin licencia | ✅ bloqueado | SoP tras feature flag; audio NVI tras gate de traducción; ver docs/legal/release-license-blockers.md |
| Closed testing | Recomendado | Play exige para cuentas personales nuevas un periodo de closed testing (12 testers/14 días); planificar con los testers `has_test_access` |

## Ficha (store listing) — pendientes de crear
- Screenshots (teléfono + tablet 7"/10"), feature graphic 1024×500, ícono 512×512.
- Descripción corta/larga es/en. Categoría: Libros y obras de consulta (o Estilo de vida).
- Declarar la URL de eliminación de cuenta y la política de privacidad.

## Orden recomendado para el release Android
1. Cerrar P0/P1 del risk register (Plan 10 local, RevenueCat, pantalla de borrado de cuenta, página web de borrado, rotación de credenciales, token seguro).
2. Crear ficha en Play Console + Play App Signing + subir AAB a closed testing.
3. Data Safety + IARC + política de privacidad.
4. 14 días de closed testing con los testers actuales.
5. Producción por etapas (10% → 50% → 100%).
