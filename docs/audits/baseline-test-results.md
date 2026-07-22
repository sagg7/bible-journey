# Baseline de pruebas — 2026-07-16

## Estado al iniciar la auditoría (antes de correcciones)

| Suite | Resultado | Detalle |
|---|---|---|
| Backend `php artisan test` (todas) | ❌ 4/49 pasan, 35 errores, 10 skipped | Migración `2026_06_29_120000` (y `150000`) usa `ALTER TABLE … MODIFY COLUMN … ENUM` crudo de MySQL → revienta en SQLite (BD de tests) y tumba toda la cadena de migraciones |
| Flutter `flutter analyze` | ⚠️ 3 infos | `use_null_aware_elements` en `lib/core/api.dart:263-265` |
| Flutter `flutter test` | ❌ 0/1 | Único test (`widget_test.dart`) falla al bombear la app completa |

Nota: HANDOFF.md (2026-06-30) registraba "36/36 tests pasan". La suite creció a 49 tests y las migraciones nuevas rompieron la compatibilidad SQLite después de esa fecha.

## Estado tras correcciones técnicas de esta auditoría

| Suite | Resultado | Comando |
|---|---|---|
| Backend Feature+Unit (sqlite :memory:) | ✅ 13/13 (41 aserciones) | `php artisan test --testsuite=Feature,Unit` |
| Backend Stream (BD real MariaDB) | ⚠️ 30/36 — **6 fallos por datos corruptos del Plan 10 local** (ver R-01/R-02 en risk register) | `DB_CONNECTION=mysql DB_DATABASE=bible_journey DB_USERNAME=root php artisan test --testsuite=Stream` |
| `stream-plans:verify 10` (BD real) | ✅ PASS estructural: 1189/1189 capítulos, 0 duplicados, 0 no cubiertos, 0 ciclos, 30,959 versículos RVA1909, CCR/narrative/canonical pass | `php artisan stream-plans:verify 10` |

## Fallos restantes de la suite Stream (datos, no código)

Los 6 fallos señalan la **corrupción in-place de los nodos del Plan 10 en la BD local** (la fuente CRS está sana; producción está sana — verificado contra el endpoint público):

1. `test_era_order_is_monotonically_non_decreasing` — 'El período intertestamentario' (sort 150) aparece después de sort 230.
2. `test_main_stream_has_expected_era_counts` — falta la era 'El exilio y la esperanza del retorno'.
3. `test_isaiah_40_55_is_in_exile_hope_era` — consecuencia de (2).
4. `test_isaiah_56_66_is_in_return_era_not_exile` — Isaías 56+ ausente de 'El retorno y la reconstrucción'.
5. `test_isaiah_split_eras_appear_in_correct_order` — consecuencia de (2).
6. `test_required_windows_do_not_appear_as_main_stream_eras` — 50 capítulos de ventanas requeridas cubiertos por nodos main-stream no historical_bridge.

Causa raíz identificada: ejecución de `stream-plans:fix-psalm-chronology 10` (2026-07-05) sobre el plan **publicado**; los nodos sin posición resoluble se "append at end" (Génesis 1-11 quedó en ranks 533-536, intertestamentario en 532). Ver propuesta de remediación en `docs/editorial/proposed-plan-10-corrections.md` — requiere decisión humana (recompilar y publicar, o restaurar desde producción).

## Correcciones técnicas aplicadas en esta auditoría

1. `database/migrations/2026_06_28_020700_create_stream_plan_nodes_table.php` — enum `display_mode` ahora declara los 5 valores finales (instalaciones frescas completas en cualquier driver).
2. `database/migrations/2026_06_29_120000_...` y `2026_06_29_150000_...` — `DB::statement(MODIFY COLUMN)` protegido con guard `mysql/mariadb`.
3. `tests/TestCase.php` — guard que rechaza tests con `RefreshDatabase` si `DB_CONNECTION != sqlite` (evita `migrate:fresh` accidental sobre la BD editorial real).
4. `tests/Feature/AdminPanelTest.php` — recurso `routes` (eliminado de Filament) sustituido por el set real de recursos; cobertura ampliada a crs/stream-plans/institutions/institution-members/users.
