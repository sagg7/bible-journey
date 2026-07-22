# Bible Journey — Mapa del sistema (auditoría 2026-07-16)

## 1. Componentes

| Componente | Stack | Ubicación | Estado |
|---|---|---|---|
| Backend API + Admin | Laravel 13 (PHP ^8.3, local Herd 8.4), Filament 5, Sanctum, Cashier (Stripe) | `backend/` | Desplegado en Site5 (`biblejourney-api.codeshore.net`) |
| App móvil | Flutter (Dart SDK ^3.11.5), Riverpod 2, go_router 14, dio 5, purchases_flutter 10 (RevenueCat), just_audio | `mobile/` | v1.0.14+14; TestFlight iOS en curso; Android sin publicar |
| Base de datos | MariaDB (local vía Herd; prod MariaDB en Site5 cPanel) | BD `bible_journey` local / `codeshor_biblejourney` prod | Local = copia editorial de trabajo |
| Especificaciones | docx/xlsx (Master Canon Ledger, CRS Spec, Harmonization Spec) | `docs/` | Referencia editorial |

## 2. Dominio y modelo de datos

### Corpus bíblico
- `translations` (9 filas): RVA1909 (es, dominio público, 30,959 versículos), KJV (en, dominio público, 31,102), WEB/BSB (en, PD, sin importar), NVI/RVR60/NIV/RVR1995/TLA (license_status=pending, can_display_full_text=0, **0 versículos** — correctamente bloqueadas).
- `biblical_books` (66) → `bible_chapters` (1,189; con `verse_count`) → `bible_verses` (62,061; FK `chapter_id` + `translation_id`).
- Importación vía comandos: `ImportRva1909`, `ImportBibleXml`, `ImportBibleYouversion`, `ImportBibleText` + página Filament ImportBibleXml. `translations.source_file_hash` guarda hash del archivo fuente.

### Motor editorial CRS (Chronological Reading Sets)
- `chronological_reading_sets` (540): unidad editorial con `stream_role`, `user_facing_era(+_sort)`, `is_main_stream_node`, `display_mode`, `is_premium`, `era_slug`, `sort_key`, `placement_confidence`, `review_status`.
- `reading_blocks`: bloques de lectura por CRS (`display_reference`, `display_order`), texto vía `PassageText`/`bible_verses`.
- `compare_groups` + `parallel_links` (con `approved`): paralelos (Evangelios, Reyes/Crónicas, Salmos↔eventos).
- `evidence_records`, `editorial_decisions`, `ledger_snapshots`: trazabilidad editorial.
- Eras de usuario: 17 etiquetas `user_facing_era` (sorts 10–230).

### Planes compilados (artefactos)
- `stream_plans` (12; **Plan 10 v9.1 = único published**, resto archived; 11/12 compilados 2026-07-05 y archivados, con warning "Cycle detected").
- `stream_plan_nodes` (540/plan) + `stream_plan_edges` (539/plan): grafo compilado con `rank`, `display_mode`, `required_state`.
- `chronological_coverage_paths`: 1 fila por libro/capítulo/plan (1,189/plan) — cobertura, `narrative_flow_behavior`, alcanzabilidad.
- Pipeline CLI: `harmonize:compile` (CompileStreamPlan) → `stream-plans:verify` → `stream-plans:publish` / `rollback` / `clone` / `normalize-sequence` / `fix-psalm-chronology`.

### Contenido histórico/editorial
- `historical_events` (+translations), `characters` (+translations), `locations`, `routes` (rutas narrativas, p.ej. David), `context_notes`, `psalm_connections` (+translations), `event_passages`.
- `crs_study_contents` y `crs_spirit_of_prophecy_contents`: contenido de estudio y EGW por CRS (generados por comandos Build*).
- Enums de gobernanza: `CertaintyLevel`, `ReviewStatus`, `LicenseStatus`, `CharacterStatus`.

### Usuarios, progreso y monetización
- `users`: `is_admin`, `has_test_access`, campos de suscripción; tokens Sanctum (`personal_access_tokens`).
- Progreso: `user_progress` (v1, por evento), `user_canonical_progress` (por capítulo canónico), `user_event_progress`, progreso v2 por bloques/nodos (ProgressV2Controller).
- `verse_highlights` + `highlight_colors` (por usuario).
- `institutions` + Cashier/Stripe (suscripción institucional por asientos); individual vía RevenueCat (`purchases_flutter`); webhook `POST /api/webhooks/revenuecat`; `ai_interactions` (log de Ezra).
- `audio_narrations`: narraciones TTS (Gemini TTS, `GenerateAudioNarrations`).

## 3. Superficie API (routes/api.php)

**Público:** register, login, webhook RevenueCat, translations, routes/{slug}, events/{slug}, characters/{slug}, readings (books, book/{osis}/chapter/{n}, {blockId}), v2 stream-plans/{id|active} (+/chronological, /nodes/{id}), compare-groups/{id}, explanations/{crsId}, passages/block/{blockId}.

**Autenticado (Sanctum):** logout, me, me/progress(+complete), events/{slug}/ask (Ezra v1), v2 progress (blocks/nodes/summary), v2 ezra/answer, v2 highlights CRUD, highlight-colors.

**Web:** landing `/`, `/instituciones` (signup con throttle), `/privacy`, `/admin` (Filament, gate is_admin), `/stripe/webhook` (Cashier).

## 4. Flujos de build/run/test

| Acción | Comando |
|---|---|
| Backend serve local | `php artisan serve --host=0.0.0.0 --port=8000` (Herd PHP 8.4: `C:\Users\garci\.config\herd\bin\php84\php.exe`) |
| Tests backend (sqlite in-memory) | `php artisan test --testsuite=Feature,Unit` |
| Tests de integridad del plan (BD real) | `DB_CONNECTION=mysql DB_DATABASE=bible_journey DB_USERNAME=root php artisan test --testsuite=Stream` |
| Verificar plan | `php artisan stream-plans:verify {id}` (reportes en `storage/app/reports/`) |
| App móvil | `flutter run --dart-define=API_BASE_URL=...` (default emulador `http://10.0.2.2:8000/api`) |
| APK release | `flutter build apk --release --dart-define=API_BASE_URL=https://biblejourney-api.codeshore.net/api` |
| Análisis Flutter | `flutter analyze` / `flutter test` |

## 5. Ambientes
- **Local:** Herd (PHP 8.4, MariaDB sin password root), BD `bible_journey` = copia editorial maestra.
- **Producción:** Site5 shared hosting (PHP máx 8.3), código en `~/biblejourney-api-app`, docroot symlink `public/`. Deploy por tar+scp+composer (HANDOFF.md §4). **La sincronización de BD documentada es un reemplazo completo local→prod (mysqldump)** — ver riesgo R-01.
- Sin CI/CD. Sin staging. Credenciales en HANDOFF.md (git-ignored) y `.env` local/prod.

## 6. Proveedores externos
- **Groq** (Ezra LLM, `EZRA_PROVIDER=openai_compatible`, llama-3.3-70b-versatile) — también cliente Anthropic disponible en código.
- **Stripe** (modo TEST configurado; institucional vía Cashier).
- **RevenueCat** (individual; sin configurar — placeholder webhook secret).
- **EGW Writings API** (cpanel.egwwritings.org, credenciales registradas; integración en exploración).
- **Gemini TTS** (narraciones de audio pre-generadas, caché de segmentos).
- Sin analytics ni crash reporting (no hay Sentry/Crashlytics/Firebase).

## 7. Autenticación y autorización
- API: Sanctum personal access tokens (Bearer). Registro/login públicos. Sin verificación de email. Sin refresh tokens (token de larga vida).
- Admin: Filament con gate `is_admin` (User::canAccessPanel).
- Token en app móvil: SharedPreferences **sin cifrar** (ver riesgo R-08).
- Gating premium: `ChronologicalReadingSet.is_premium` + `User::hasPremiumAccess()` (`has_test_access` lo bypasea para testers).
