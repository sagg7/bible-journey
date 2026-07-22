# Reporte de integridad del texto bíblico — 2026-07-16

## Fuente e importación
- **Traducción principal:** RVA1909 (Reina-Valera Antigua 1909), dominio público, importada vía `php artisan bible:import-rva1909` (`ImportRva1909`); `translations.source_file_hash` conserva el hash del archivo fuente.
- **Secundaria:** KJV (King James Version), dominio público*, 31,102 versículos. *Nota territorial: en el Reino Unido la KJV mantiene privilegio perpetuo de la Corona (letters patent); irrelevante para distribución fuera de UK pero anotado en el registro de licencias.
- Traducciones con copyright (NVI, RVR60, NIV, RVR1995, TLA): `license_status=pending`, `can_display_full_text=0`, **0 versículos importados** — correctamente bloqueadas. WEB y BSB (dominio público): declaradas, sin importar.

## Resultados de validación (BD local, 2026-07-16)

| Comprobación | RVA1909 | KJV |
|---|---|---|
| Libros | 66 ✓ | 66 ✓ |
| Capítulos | 1,189 ✓ | 1,189 ✓ |
| Versículos | **30,959** ✓ (línea base esperada) | 31,102 |
| Versículos duplicados | 0 | 0 |
| Versículos vacíos | 0 | 0 |
| Saltos de numeración | 0 | 0 |
| Capítulos sin texto | 0 | 0 |
| HTML/etiquetas inesperadas | 0 | 0 |
| Caracteres de control / replacement / mojibake | 0 | 0 |
| Libros duplicados (osis_code) | 0 | — |
| Saltos de numeración de capítulos | 0 | — |

## Diferencias de versificación documentadas (NO son errores; NO tocar sin aprobación editorial)
1. **Salmos con título (135 capítulos):** RVA1909 no numera las inscripciones; KJV las integra al v.1 → RVA tiene 1 versículo menos por salmo afectado. También 1 Sam 23 y 2 Sam 20 (28 vs 29 / 25 vs 26).
2. **Job 38–41:** la edición RVA1909 divide los capítulos distinto (Job 38: 38 vs 41; Job 40: 19 vs 24). Verificado que el texto está completo y contiguo ("¿CAZARÁS tú la presa para el león?" abre Job 39 RVA = Job 38:39 KJV). Total Job: RVA 1,061 vs KJV 1,070 — redistribución + versos fusionados de la edición fuente, sin pérdida de contenido.
3. **2 Cor 13:** RVA 13 versículos vs KJV 14 (bendición final fusionada).

## Problemas encontrados y su disposición

| # | Problema | Sev | Disposición |
|---|---|---|---|
| 1 | `bible_chapters.verse_count` de GEN 1 = 2 (real: 31 en ambas traducciones) — metadato corrupto | P1 | **Corregido** (UPDATE a 31). Semántica de la columna documentada: "máximo entre traducciones importadas" (así la puebla `ImportBibleYouversion` con `GREATEST`). |
| 2 | Bloques 615/616/617 (2 Juan, 3 Juan, Judas) con book_id/capítulos NULL → **el lector no devolvía texto para esos 3 libros** en el plan cronológico | **P0** | **Corregido** (datos reparados a capítulo 1; causa raíz corregida en `ImportRva1909::resolveReadingBlockRanges` — referencia de libro completo ahora resuelve 1..chapter_count). Producción usa otros IDs de bloque; verificar allí tras el próximo deploy con `scripture:manifest --check` + suite Stream. |
| 3 | 15 bloques con `end_verse=99` (centinela sin resolver, Mateo/Marcos/Hechos) | P2 | Sin impacto funcional (la consulta usa `<=`), pero es dato sucio que confunde auditorías. Propuesta: normalizar a NULL (= capítulo completo) en próxima corrida editorial. **No modificado automáticamente** por tocar filas del ledger editorial. |
| 4 | Bloque 364 "Ezequiel 33" con `end_verse=39` (el capítulo tiene 33) — probable typo editorial (¿39↔33?) | P2 | **Propuesta editorial** (no corregido): ver `docs/editorial/proposed-plan-10-corrections.md`. |
| 5 | Bloque 628 "2 Cor 1-13" `end_verse=14` en versificación KJV (RVA termina en 13) | P3 | Sin impacto (`<=`). Anotado como inconsistencia de versificación del ledger. |
| 6 | `stream-plans:verify` reporta `reading_blocks_without_text = 0` aunque los 3 bloques del punto 2 no entregaban texto | P1 | Punto ciego del verificador anotado; cubierto ahora por el manifiesto + validación de bloques (ver abajo). |

## Manifiesto criptográfico
- **Archivo:** `data/manifests/scripture-manifest.json` (esquema `bible-journey/scripture-manifest@1`).
- **Contenido:** hash SHA-256 por corpus completo, por libro (66×2) y por capítulo (1,189×2), + conteo de versículos por capítulo, por traducción importada.
- **Generar:** `php artisan scripture:manifest` (con `DB_CONNECTION=mysql DB_DATABASE=bible_journey DB_USERNAME=root` para la BD real).
- **Verificar (CI / pre-deploy):** `php artisan scripture:manifest --check` — sale con código ≠0 y lista capítulo por capítulo cualquier modificación, aparición o desaparición. **Nunca modifica texto.**
- Hash corpus RVA1909 al 2026-07-16: `8290a2f15c8a35e8…` (ver archivo para el valor completo).

## Política ante diferencias futuras
Si `--check` falla: NO reimportar ni "reparar" automáticamente. Generar reporte de diferencias, identificar el origen (import accidental, edición manual, migración) y decidir con evidencia documental. El texto bíblico solo cambia con aprobación humana explícita.
