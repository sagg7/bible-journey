# Gate 3 — Plan de pruebas RevenueCat (sandbox → producción)

> Aprobado 2026-07-16: "primero pruebas con el usuario de pruebas de Google
> Play; si todo se ve bien, agregar el real para lanzar". El backend ya está
> listo y probado (webhook con semántica correcta, 10 tests). Estos pasos
> requieren Play Console + dashboard de RevenueCat + un dispositivo físico —
> no se pueden automatizar desde esta máquina.

## A. Configuración (una vez)
1. **Play Console** → Productos → Suscripciones → crear `bj_premium_monthly`
   ($6.99/mes según lo decidido). La sección Suscripciones ya debería estar
   desbloqueada (el release en revisión de julio ya existe).
2. **Play Console** → Configuración → Prueba de licencias → agregar la cuenta
   Gmail del tester (compras sandbox sin cobro real).
3. **RevenueCat** → crear proyecto → app Android (package
   `com.codeshore.biblejourney`) → conectar credenciales de Play (service
   account JSON que pide RevenueCat).
4. RevenueCat → Entitlements → crear `premium` → adjuntar `bj_premium_monthly`.
   → Offerings → offering `default` con ese paquete.
5. RevenueCat → Project settings → Webhooks → URL
   `https://biblejourney-api.codeshore.net/api/webhooks/revenuecat`,
   Authorization header = un secreto fuerte nuevo. Copiar ese valor a
   `REVENUECAT_WEBHOOK_SECRET` en el `.env` de producción (¡ya no el
   placeholder `test-secret-local`!) y `php artisan config:cache`.
6. Build de prueba con la llave pública:
   `flutter build apk --release --dart-define=API_BASE_URL=https://biblejourney-api.codeshore.net/api --dart-define=REVENUECAT_API_KEY_ANDROID=<public key>`

## B. Pruebas sandbox (cuenta de license testing, sin cobro)
| # | Prueba | Resultado esperado |
|---|---|---|
| 1 | Usuario anónimo abre lectura canónica | Texto RVA1909 gratis, sin paywall |
| 2 | Usuario registrado free abre nodo premium | `locked: true` + paywall |
| 3 | Comprar suscripción desde el paywall | Compra sandbox OK; webhook llega (revisar RevenueCat → Events y `users.subscription_status='premium'`); nodo premium se desbloquea sin reiniciar |
| 4 | Cerrar sesión / iniciar en OTRO dispositivo | Premium se refleja (server-side por usuario) |
| 5 | Restore purchases tras reinstalar | Acceso restaurado (evento TRANSFER/RESTORE) |
| 6 | Cancelar la suscripción en Play | **Sigue premium** hasta la fecha de expiración (sandbox: minutos) — verificar que NO se degrada al instante |
| 7 | Dejar expirar (sandbox expira en ~5 min con renovación desactivada) | Evento EXPIRATION → `subscription_status='free'`; canónico sigue gratis |
| 8 | Apagar el backend y abrir la app | Lectura ya visitada funciona offline; al volver la red, el estado premium se refresca |
| 9 | Webhook con secreto inválido (curl) | 401 |

## C. Paso a producción (solo si B pasa completo)
1. Verificar precios/países del producto en Play Console.
2. Quitar la cuenta sandbox de testers de licencia (o dejarla — no afecta a usuarios reales).
3. Confirmar `REVENUECAT_WEBHOOK_SECRET` real en prod y monitorear RevenueCat → Events las primeras 48h.
4. Los testers actuales con `has_test_access=1` nunca ven paywall (independiente de RevenueCat) — así se puede lanzar sin bloquear al equipo.

## Notas del backend (ya implementado y probado)
- CANCELLATION conserva el acceso hasta `expiration_at_ms` (fix de auditoría).
- El lookup de usuario solo usa `id` numérico estricto; `$RCAnonymousID:*` no puede acreditarse a nadie por accidente.
- Comparación de secreto timing-safe (`hash_equals`).
