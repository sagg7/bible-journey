# Registro de riesgos de publicación — Bible Journey (actualizado 2026-07-16 tarde — tras aprobación del propietario)

## ⚡ Actualización post-aprobación (mismo día)

Con aprobación explícita del propietario se aplicaron las correcciones editoriales y de release:

| ID | Cambio de estado |
|---|---|
| R-01 | ✅ **RESUELTO** — Plan 13 recompilado desde la fuente corregida, 5 gates PASS, publicado localmente; Plan 10 archivado. La BD local vuelve a ser segura como fuente para deploy (usar dump selectivo, ver R-05) |
| R-18 | ✅ **RESUELTO** — 179 versículos cubiertos (0 sin cubrir); 12/18 fronteras restauraban la intención original del ledger |
| R-10 | ✅ **RESUELTO** — era_slug 'exile'→'babylonian-exile'; resolver advierte slugs desconocidos; nuevo Gate 5 (Ordering) en verify: 0 violaciones de eras, 26/26 links bien ubicados. Suite Stream 36/36 |
| R-17 | ✅ **RESUELTO** — respaldo `data/backups/bible_journey-pre-editorial-fixes-20260716.sql.gz` |
| R-21 | ✅ **RESUELTO** — flujo in-app "Eliminar cuenta" (menú de cuenta, confirmación con contraseña) + página web `/eliminar-cuenta` (+ `/delete-account`) + test |
| R-20 | ⚠️ **EVIDENCIA NUEVA** — el aviso legal del White Estate (archivado en `docs/legal/egw-tos-evidence.md`) permite solo "citas breves" y prohíbe republicar. El uso actual (1,022 extractos re-servidos en app de pago) **no está autorizado**; se requiere permiso escrito. Flag permanece OFF en prod |
| R-06 | Plan de pruebas listo (`docs/release/revenuecat-test-plan.md`) — requiere Play Console + RevenueCat + dispositivo (manos del propietario) |
| NUEVO R-31 (P2) | `harmonize:compile` publica el plan directamente (saltó verify/migración/auditoría de `stream-plans:publish`). Mitigado hoy corriendo esos pasos a posteriori; recomendar que compile cree drafts |

Estado final de suites: backend Feature+Unit **35/35**, Stream **36/36**, Flutter **17/17**, analyze limpio, corpus intacto (manifest ✓).


Clasificación: **P0** bloquea publicación · **P1** debe resolverse antes del lanzamiento · **P2** puede corregirse después · **P3** mejora futura.

## P0 — Bloquean publicación

| ID | Área | Riesgo | Estado |
|---|---|---|---|
| R-01 | Datos/Deploy | Plan 10 (publicado) con nodos corruptos en la BD **local** (Génesis 1-11 al final, era 'El exilio y la esperanza del retorno' desaparecida, intertestamentario tras Apocalipsis). Producción está sana (verificado vía API pública), pero el flujo de deploy documentado (mysqldump completo local→prod) **publicaría la corrupción**. | **PROPUESTA** (decisión humana): recompilar+publicar o restaurar de prod — `docs/editorial/proposed-plan-10-corrections.md` §A. Hasta entonces: NO sincronizar BD |
| R-18 | Cobertura | 179 versículos RVA1909 fuera de todos los bloques del Plan 10 (incluye Lucas 2:3-20 — nacimiento de Jesús; Ezequiel 1:1-14; Apocalipsis 8:1). | **PROPUESTA editorial** §B (fronteras exactas documentadas) |
| R-03 | Tests | Suite backend rota en SQLite (35 errores). | ✅ **CORREGIDO** — 34/34 en verde |
| R-04 | Datos | `RefreshDatabase` con `DB_CONNECTION=mysql` haría migrate:fresh de la BD editorial. | ✅ **CORREGIDO** (guard en TestCase) |
| R-19 | Lector | 2 Juan, 3 Juan y Judas sin texto en el lector CRS (bloques con referencias NULL por bug del parser). | ✅ **CORREGIDO** (datos + causa raíz en importador; verificado end-to-end) |
| R-02 | Publicación | Plan publicado mutable in-place sin auditoría (causa de R-01). | ✅ **CORREGIDO** (guard de inmutabilidad + snapshot con --force-published + tests) |

## P1 — Antes del lanzamiento

| ID | Área | Riesgo | Estado |
|---|---|---|---|
| R-05 | Deploy | Sync de BD por reemplazo completo borra usuarios/progreso de prod. | Abierto — usar dump selectivo (tablas editoriales); pauta en release-checklist Gate 0 |
| R-06 | Monetización | RevenueCat sin configurar (webhook secret placeholder). | Abierto (requiere cuentas/consolas) |
| R-07 | Licencias | Audio TTS generado desde NVI (sin licencia). | ✅ **MITIGADO** (gate técnico: solo traducciones licenciadas para usuarios normales) + decisión pendiente (licenciar NVI o regenerar con RVA1909) |
| R-20 | Licencias | Espíritu de Profecía (trad. ES + ToS del API EGW sin comprobar). | ✅ **BLOQUEADO en prod** (`FEATURE_SPIRIT_OF_PROPHECY`, default off fuera de local) + verificación legal pendiente |
| R-08 | Seguridad móvil | Token Sanctum en SharedPreferences sin cifrar. | Abierto — migrar a flutter_secure_storage (pasos en security-review) |
| R-16 | Secretos | Credenciales reales en HANDOFF.md (Groq, EGW, Stripe test, DB prod). | Abierto — **rotar Groq y EGW** |
| R-21 | Play policy | Eliminación de cuenta: falta pantalla in-app y página web. | Parcial — ✅ endpoint `DELETE /api/me` implementado y probado; UI/web pendientes |
| R-22 | Progreso | No existía migración real de progreso entre planes (usuarios perderían progreso visible al publicar plan nuevo). | ✅ **CORREGIDO** (`stream-plans:migrate-progress` + integrado a publish + tests de idempotencia) |
| R-09 | Tests móvil | 1 solo test Flutter y roto. | ✅ **CORREGIDO** — 17 tests (smoke, progreso local, verse locator, cache offline) en verde |
| R-23 | Offline | Sin lectura offline (criterio de terminación). | ✅ **CORREGIDO** (interceptor de cache LRU con fallback en fallo de red + tests) |
| R-10 | Planes | Compiles 11/12 con warning "Cycle detected"; era 'Misiones de Pablo' con sort duplicado en 12. | **PROPUESTA editorial** §E/§F — resolver antes del próximo compile |
| R-24 | Webhook RC | CANCELLATION revocaba acceso pagado de inmediato; comparación de secreto no timing-safe; cast numérico podía acreditar al usuario equivocado. | ✅ **CORREGIDO** + 7 tests |
| R-25 | Seguridad | Sin rate limiting en login/register/Ezra. | ✅ **CORREGIDO** (throttle + test 429) |
| R-17 | Datos | Sin respaldo versionado de la BD editorial local. | Abierto |

## P2 — Puede corregirse después

| ID | Área | Riesgo | Estado |
|---|---|---|---|
| R-11 | Auth | Tokens sin expiración; sin verificación de email; enumeración pasiva en registro. | Abierto (recomendaciones en security-review) |
| R-13 | Observabilidad | Sin crash reporting/analytics. | Abierto (decisión) |
| R-14 | Corpus | `verse_count` = versificación máxima entre traducciones (documentado); GEN 1 corrupto (2→31). | ✅ GEN 1 corregido; semántica documentada |
| R-26 | Editorial | Contenido de estudio auto-generado sin etiqueta en la app. | Parcial — ✅ API expone `generated: true`; UI pendiente |
| R-27 | Privacidad | `ai_interactions` sin política de retención. | Abierto (propuesta: purga 90 días) |
| R-28 | Ezra | Citas bíblicas del LLM no verificadas contra el corpus. | Abierto (diseño en ezra-eval-set.md) |
| R-29 | Datos | 15 bloques `end_verse=99`; EZK 33 typo; 2 Cor versificación KJV. | Propuesta editorial §D |
| R-30 | Filament | Falta test del scoping de institution admins. | Abierto |

## P3 — Mejoras futuras
- R-15 lints (✅ corregidos — analyze limpio) · exportación de datos (`GET /api/me/export`) · rate limit de lectura pública · empaquetar fuentes de google_fonts como assets · cap de `certainty_level` de Ezra al nivel del CRS.

## Correcciones técnicas aplicadas en esta auditoría (resumen)
Backend: migraciones multi-driver, guard TestCase, AdminPanelTest actualizado, `scripture:manifest` (nuevo comando + manifiesto), reparación bloques 615-617 + parser, GEN 1 verse_count, guard de inmutabilidad de planes, `stream-plans:migrate-progress` + publish integrado, feature flag SoP, gate de licencia de audio, marcador `generated`, webhook RevenueCat (3 fixes), `DELETE /api/me`, throttling, fix N+1 books(), fix `primary_completed_at`. Tests: 13→34 backend.
Mobile: cache offline (nuevo), fix lints, suite de tests 1(rota)→17 en verde.
