# Auditoría de seguridad — 2026-07-16

## Resumen
Sin vulnerabilidades críticas de acceso a datos entre usuarios. Los riesgos
principales estaban en: falta de rate limiting (corregido), ausencia de
eliminación de cuenta (corregido), token móvil en almacenamiento no cifrado
(pendiente, P1), y credenciales reales en HANDOFF.md que deben rotarse (P1).

## Autenticación y sesiones
| Punto | Estado |
|---|---|
| Login/registro (Sanctum personal access tokens) | ✓ Hash bcrypt; mensaje de error uniforme (sin enumeración por login) |
| Rate limiting login/register | **Corregido**: `throttle:10,1` / `throttle:5,1` (antes: sin límite) |
| Logout revoca token server-side | ✓ `currentAccessToken()->delete()`; la app llama /logout best-effort |
| Expiración de tokens | ⚠️ P2: tokens Sanctum sin expiración (`sanctum.expiration` null). Mitigado por revocación; recomendar 90 días |
| Refresh tokens | No existen (aceptable con expiración + re-login) |
| Verificación de email | ⚠️ P2: no hay; `unique:users,email` en registro permite enumeración pasiva (tradeoff estándar) |
| Almacenamiento del token en Flutter | ⚠️ **P1 abierto**: SharedPreferences sin cifrar (`lib/core/auth.dart`). Migrar a `flutter_secure_storage` con migración one-shot del token existente |
| Eliminación de cuenta | **Corregido**: `DELETE /api/me` (contraseña requerida, throttle 3/min); cascada verificada por test (progreso/highlights borrados, ai_interactions anonimizadas SET NULL, tokens revocados). **Pendiente**: pantalla en la app (Play exige acceso in-app) |

## Autorización por endpoint
| Recurso | Verificación |
|---|---|
| Highlights (index/store/destroy/colors) | ✓ Scoped por `user_id` en todos los caminos; IDOR probado (404 cruzado) — test `SecurityTest` |
| Progreso v1/v2 (mark/summary) | ✓ Scoped por usuario autenticado; test de aislamiento agregado |
| Nodos premium | ✓ `is_premium` + `hasPremiumAccess()` con gate ANTES del cache de Ezra; tests en `MonetizationTest` |
| Panel Filament | ✓ Gate `canAccessPanel` (is_admin ∥ is_institution_admin); test 403 para no-admin. ⚠️ P2: verificar el scoping por institución de InstitutionMemberResource con test dedicado |
| Publicación de contenido | ✓ Solo vía CLI con gates (verify + confirm + auditoría); Filament no expone publicar. Guard de inmutabilidad agregado a comandos mutadores |
| Webhook RevenueCat | **Corregido**: `hash_equals` (timing-safe), lookup por id solo si es numérico estricto (evita acreditar suscripción al usuario equivocado por cast de MySQL) |
| Webhook Stripe | ✓ Cashier verifica firma con `STRIPE_WEBHOOK_SECRET` |

## Inyección y validación
- SQL: Eloquent/query builder con bindings en todo el código de API ✓. Los
  `DB::statement` crudos solo existen en migraciones (sin input de usuario).
- Mass assignment: `#[Fillable]` explícito en modelos; los controladores usan
  campos validados explícitos (no `$request->all()`) ✓.
- Validación de entrada: presente en todos los POST/PATCH revisados ✓.
- XSS: API JSON puro; el panel Filament escapa por defecto ✓.

## Secretos y configuración
- ⚠️ **P1**: HANDOFF.md contiene credenciales reales (DB prod, Groq key, Stripe
  test, EGW secret). Está git-ignored, pero cualquier proceso/IA con acceso al
  directorio las lee → **rotar Groq y EGW antes del release**; mover a un
  gestor de secretos.
- `.env` fuera de git ✓; docroot en prod via symlink a `public/` ✓ (app/vendor
  no accesibles por web).
- `APP_DEBUG=false` en prod ✓.
- Repo git: sin secretos en archivos trackeados (verificado IOS_HANDOFF.md, PRD.txt).

## Logs y privacidad operacional
- Sin llamadas `Log::`/`logger()` en `app/` → no hay fuga de tokens/notas en logs ✓.
- `report()` solo con excepciones ✓.
- Sin analytics ni crash reporting (nada que fugar; decisión de observabilidad pendiente R-13).
- ⚠️ P2: `ai_interactions` guarda pregunta+respuesta con user_id sin política de
  retención (las preguntas a Ezra pueden contener contenido personal/pastoral).
  Propuesta: retención 90 días con job de purga, o anonimizar tras 30.

## Rate limiting (estado tras la auditoría)
| Endpoint | Límite |
|---|---|
| POST /login | 10/min por IP (**nuevo**) |
| POST /register | 5/min por IP (**nuevo**) |
| DELETE /me | 3/min (**nuevo**) |
| POST /v2/ezra/answer y /events/{slug}/ask | 20/min por usuario (**nuevo** — protege costo LLM) |
| POST /instituciones | ya existía (`throttle:institution-signup`) |
| Lectura pública | ⚠️ P3: sin límite; riesgo de scraping del corpus (dominio público, bajo impacto) |

## Pruebas agregadas
`tests/Feature/SecurityTest.php`: IDOR de highlights, aislamiento de progreso,
eliminación de cuenta (contraseña incorrecta + cascadas), rate limit de login.
`tests/Feature/MonetizationTest.php`: gating premium/free/expirado + webhook.

## Pendientes priorizados
1. **P1** Rotar credenciales expuestas en HANDOFF.md (Groq, EGW; revisar DB prod).
2. **P1** Token en `flutter_secure_storage` (móvil).
3. **P1** Pantalla de eliminación de cuenta en la app (el endpoint ya existe).
4. **P2** Expiración de tokens Sanctum + retención de `ai_interactions`.
5. **P2** Test de scoping para institution admins en Filament.
