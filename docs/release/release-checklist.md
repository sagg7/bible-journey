# Checklist de release — Bible Journey (2026-07-16)

## Gate 0 — Integridad de datos (antes de cualquier deploy de BD)
- [ ] Resolver Plan 10 local corrupto (recompilar+publicar o restaurar de prod) — `docs/editorial/proposed-plan-10-corrections.md` §A ⚠️ **P0: NO sincronizar la BD local a producción antes de esto**
- [ ] `php artisan scripture:manifest --check` en verde (corpus intacto)
- [ ] Suite Stream en verde contra la BD que se va a desplegar: `DB_CONNECTION=mysql DB_DATABASE=bible_journey DB_USERNAME=root php artisan test --testsuite=Stream`
- [ ] Cambiar el flujo de sync de BD a dump selectivo de tablas editoriales (nunca `users`/`user_*`/`personal_access_tokens` — R-05)
- [ ] Respaldo versionado de la BD editorial local (R-17)

## Gate 1 — Código y pruebas
- [x] Backend Feature+Unit: 34/34 en verde (`php artisan test --testsuite=Feature,Unit`)
- [x] Flutter: 17/17 en verde + `flutter analyze` sin issues
- [x] Build release Android verificado
- [ ] Correr suite Stream tras el fix del Plan 10 (hoy: 30/36 por datos)

## Gate 2 — Seguridad
- [ ] Rotar credenciales expuestas en HANDOFF.md (Groq, EGW; evaluar DB prod) — R-16
- [ ] Migrar token móvil a `flutter_secure_storage` — R-08
- [x] Rate limiting login/register/Ezra/delete
- [x] Webhook RevenueCat: hash_equals + semántica CANCELLATION correcta
- [x] Guard de inmutabilidad de planes publicados + TestCase guard anti-wipe

## Gate 3 — Monetización
- [ ] Configurar RevenueCat (apps, productos en Play Console, entitlement, `REVENUECAT_WEBHOOK_SECRET` real) — R-06
- [ ] Probar compra sandbox end-to-end (compra, restore, cancelación conserva acceso, expiración)
- [ ] Stripe: crear producto/precios en modo live cuando se acepten pagos institucionales reales
- [x] Biblia canónica gratis verificada por tests; premium con candado correcto

## Gate 4 — Licencias (docs/legal/)
- [ ] Archivar ToS del API de EGW + estatus de traducciones ES → decidir si se enciende `FEATURE_SPIRIT_OF_PROPHECY`
- [ ] Decidir audio: licencia NVI o regenerar narraciones con RVA1909
- [ ] Documentar origen/licencia de imágenes de eras e ícono
- [ ] Completar `translations.source_url` de RVA1909/KJV

## Gate 5 — Play Store
- [ ] Pantalla in-app "Eliminar cuenta" (endpoint listo) + página web de solicitud de borrado
- [ ] Data Safety + IARC + URL de política de privacidad en la ficha
- [ ] Ficha: screenshots, feature graphic, descripciones es/en
- [ ] Play App Signing + respaldo externo del keystore
- [ ] Closed testing (≥12 testers, 14 días) → producción por etapas

## Gate 6 — Contenido editorial (aprobación humana)
- [ ] Revisar/aplicar correcciones de cobertura (179 versículos) — proposed-plan-10-corrections §B
- [ ] Decidir duplicaciones ventana/fallback §C y datos sucios §D
- [ ] Resolver ciclo detectado y era con sort duplicado antes del próximo compile (§E, §F)
- [ ] Etiquetar en la app el contenido `generated: true` como "generado automáticamente"

## Comandos de verificación completa
```bash
# Backend (sqlite):
cd backend && php artisan test --testsuite=Feature,Unit
# Integridad de datos (BD real):
DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_DATABASE=bible_journey DB_USERNAME=root \
  php artisan test --testsuite=Stream
DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_DATABASE=bible_journey DB_USERNAME=root \
  php artisan scripture:manifest --check
DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_DATABASE=bible_journey DB_USERNAME=root \
  php artisan stream-plans:verify 10
# Flutter:
cd mobile && flutter analyze && flutter test
flutter build appbundle --release --dart-define=API_BASE_URL=https://biblejourney-api.codeshore.net/api \
  --dart-define=REVENUECAT_API_KEY_ANDROID=<key>
```
