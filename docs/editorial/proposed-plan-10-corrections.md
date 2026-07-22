# Propuestas de corrección — Plan 10 (v9.1) y ledger editorial

> **✅ APLICADO 2026-07-16 con aprobación del propietario** (respaldo previo:
> `data/backups/bible_journey-pre-editorial-fixes-20260716.sql.gz`).
> Resultado: **Plan 13 compilado, verificado (5 gates PASS) y publicado
> localmente**; Plan 10 archivado. Suite Stream **36/36**; cobertura por
> versículo **30,959/30,959 (0 sin cubrir)**; corpus bíblico intacto
> (manifiesto ✓ — ninguna de estas ediciones tocó texto bíblico).
>
> Resumen de aplicación por sección:
> - **A**: opción 1 ejecutada (recompilar+publicar). Nota: `harmonize:compile`
>   publica directamente (saltó los gates de `stream-plans:publish`); se corrió
>   verify + migración de progreso a posteriori. Anotado como P2 en el risk register.
> - **B**: 18 fronteras corregidas — 12 de ellas simplemente restauran la
>   intención que el ledger ya tenía en `passage_start` (p.ej. "Luke 1:57-2:20",
>   "Ezekiel 1-3:15") y que el parser numérico truncó por no soportar rangos
>   entre capítulos.
> - **C**: bloques de Génesis en ventanas GLW → rol `poetic_literary_mirror` +
>   no requeridos; epístolas en ventanas → no requeridas (cobertura canónica en
>   fallbacks GLET); solapes recortados (Esdras 377→5:2, Marcos 508→9:1,
>   GAP-MAT-018→18:19); marcadores NT no requeridos; serie renombrada
>   `CRS-GEN-001..015` → `CRS-GLW-001..015`.
> - **D**: 15 centinelas `end_verse=99` → NULL; EZK 364 y 2CO 628 → NULL.
> - **E**: causa raíz real encontrada: `era_slug='exile'` (CRS-EZK-GRP-001/002)
>   fuera de `ERA_CANONICAL_ORDER` → nodos al final del plan. Corregido a
>   `babylonian-exile`; el resolver ahora ADVIERTE era_slugs desconocidos; nuevo
>   **Gate 5 (Ordering)** en `stream-plans:verify` valida orden de eras por
>   primera aparición + los 26 parallel_links (0 mal ubicados). Los warnings
>   "Cycle detected" restantes son la resolución esperada del conflicto
>   cadena-secuencial vs. links (documentado en el propio resolver).
> - **F**: ya no existía en datos ni código (los 6 CRS fueron re-etiquetados
>   antes); el Gate 5 detectaría una regresión.
> - Extra: `BuildCoveragePaths.complete_mode_required` ahora es verdadero si
>   CUALQUIER bloque que cubre el capítulo lo requiere (antes solo el primario);
>   test #34 de la suite Stream actualizado a la semántica real del producto
>   (prophetic/fallback/apocalyptic son main de primera clase — como en
>   producción; poesía/colecciones/genealogías siguen protegidas).
>
> El texto original de la propuesta se conserva abajo como referencia histórica.

> **Estado original: PROPUESTA — requería aprobación humana.**
> Nada de este documento fue aplicado automáticamente. Las correcciones técnicas
> ya aplicadas (bloques 615-617, verse_count GEN 1) están documentadas aparte en
> `docs/audits/scripture-integrity-report.md`.
> Auditoría 2026-07-16. Reproducible con las vistas `v_plan10_verse_coverage` y
> `v_plan10_verse_blocks` (creadas en la BD local durante la auditoría).

## A. [P0] Nodos del Plan 10 corruptos en la BD local (orden de eras roto)

**Evidencia.** 6 tests de la suite Stream fallan contra la BD local; contra
producción el endpoint público devuelve las 17 eras en orden correcto. En local:
'Los primeros tiempos' (Génesis 1-11, sort=10) está en ranks 533-536 (al final);
'El período intertestamentario' (sort=150) en rank 532; la era 'El exilio y la
esperanza del retorno' (sort=105) desapareció de los nodos main-stream. La fuente
CRS está íntegra (17 eras correctas) y el Plan 12 (archivado, compilado
2026-07-05) tiene la estructura casi correcta.

**Causa.** Ejecución de `stream-plans:fix-psalm-chronology 10` (~2026-07-05)
sobre el plan publicado: los nodos cuya posición destino no se pudo resolver se
"append at end" (comportamiento documentado en el propio comando, línea 72).

**Impacto.** La BD local es la copia editorial maestra. El flujo de deploy
documentado (mysqldump completo local→prod) publicaría Génesis al final del
plan cronológico para todos los usuarios.

**Opciones (elegir una):**
1. **Recompilar y publicar (recomendada).** `php artisan harmonize:compile` →
   revisar el reporte → `stream-plans:verify <nuevo>` → suite Stream contra el
   nuevo plan → `stream-plans:publish <nuevo> --confirm`. El resolver actual ya
   reposiciona los Salmos automáticamente (la razón por la que se corrió el
   fix manual ya no existe). Atención: los compiles 11/12 emitieron el warning
   "Cycle detected" (ver punto E) y el 12 introdujo la era 'Misiones de Pablo y
   sus cartas' con sort duplicado 220 (ver punto F) — resolver ambos antes de publicar.
2. **Restaurar el artefacto desde producción.** Dump selectivo de
   `stream_plan_nodes`/`stream_plan_edges`/`chronological_coverage_paths` del
   plan 10 en prod → import local. Restaura exactamente lo que ven los usuarios.
   Requiere acceso manual del equipo a producción (esta auditoría no usó
   credenciales de producción).

**Guard técnico ya implementado** (Fase 15): los comandos que mutan nodos
rechazan planes `published` sin `--force-published`, y `--force-published` exige
respaldo previo. Ver `docs/audits/release-risk-register.md` R-02.

## B. [P0-editorial] 179 versículos RVA1909 sin cubrir por ningún bloque del Plan 10

La cobertura por capítulo es 1189/1189, pero al nivel de versículo 18 rangos
quedan fuera de todos los bloques (deslices de frontera al dividir capítulos
entre CRS). Incluyen pasajes centrales:

| Libro | Rango sin cubrir | Versos | Contenido | Frontera observada |
|---|---|---|---|---|
| Lucas | 2:3-20 | 18 | **Nacimiento de Jesús y los pastores** | CRS-07-006 termina en 2:2; CRS-07-008 empieza en 2:21 |
| Ezequiel | 1:1-14 | 14 | Visión inaugural (los seres vivientes) | CRS-05-004 empieza en 1:15 |
| Ezequiel | 3:1-15 | 15 | Comer el rollo; comisión del profeta | CRS-05-005 empieza en 3:16 |
| Mateo | 21:24-46 | 23 | Autoridad de Jesús; parábolas de los labradores | CRS-07-077 termina en 21:23; ningún bloque sigue |
| Lucas | 20:22-47 | 26 | El tributo al César; la resurrección | (misma clase) |
| Lucas | 11:37-54 | 18 | Ayes contra fariseos | |
| Lucas | 10:11-24 | 14 | Regreso de los setenta | |
| Marcos | 7:8-23 | 16 | Lo que contamina al hombre | |
| Mateo | 26:14-19 | 6 | Judas pacta; preparación de la Pascua | |
| Marcos | 14:10-16 | 7 | (paralelo del anterior) | |
| Mateo | 15:16-20 | 5 | | |
| Lucas | 21:1-4 | 4 | La ofrenda de la viuda | |
| Lucas | 4:42-44 | 3 | | |
| Marcos | 3:4-6 | 3 | | |
| Juan | 8:9-11 | 3 | Final de la perícopa de la adúltera | CRS-07-056: bloques 7:53-8:8 y 8:12-8:59 |
| 2 Crónicas | 32:32-33 | 2 | Muerte de Ezequías | |
| Mateo | 11:1 | 1 | | |
| Apocalipsis | 8:1 | 1 | **El séptimo sello** | CRS-REV-005 empieza en 8:2 |

**Propuesta.** Ajustar las fronteras de los bloques correspondientes en el
ledger (extender el bloque previo o el siguiente hasta cerrar el hueco). Cada
ajuste es un cambio de 1 campo (`end_verse`/`start_verse`). Tras aplicar,
recompilar y re-verificar la matriz (la vista SQL da 0 filas cuando quede cerrado).

## C. [P1-editorial] Repeticiones sin justificación documentada (1,611 versículos, 38 pares de bloques)

1. **Génesis doble (≈1,150 versos):** las ventanas literarias de las Cartas
   Generales (CRS ids 1-15, source_map `CRS-GEN-001..015`, títulos "Hebreos…",
   "Santiago…", rol `literary_collection`/`required_window`) contienen bloques de
   Génesis con rol `narrative_anchor`, además de los bloques main de Génesis
   (CRS 500-507). El emparejamiento Génesis↔epístola parece deliberado, pero:
   - no existe `parallel_link` ni `compare_group` que lo documente;
   - el bloque de Génesis dentro de la ventana usa rol `narrative_anchor`
     (debería ser un rol de espejo literario/suplementario);
   - en modo completo el lector debe leer esos capítulos de Génesis dos veces.
   **Propuesta:** documentar la relación con `parallel_links` aprobados y cambiar
   el rol de los bloques de Génesis dentro de las ventanas a
   `poetic_literary_mirror` o `supplementary_reading` (decisión editorial).
2. **Epístolas dobles (ventana + fallback canónico):** p.ej. Judas completo está
   en la ventana `CRS-GEN-015` y en el fallback `CRS-GLET-JDS`; ambos requeridos
   en modo completo. **Propuesta:** decidir si el fallback se exime cuando la
   ventana cubre el libro, o si la doble lectura es intencional; documentarlo.
3. **Solapes de frontera menores:** Marcos 8-9 vs Marcos 9 (8 versos), Esdras
   4-5 vs Esdras 5 (3), Hechos 19-20/20 y 21-23/21 (1 verso c/u), Mateo 17-18 vs
   CRS-GAP-MAT-018 (18). **Propuesta:** ajustar fronteras igual que en B.
4. **Nomenclatura confusa:** `CRS-GEN-*` significa a la vez "Génesis" (500-507
   usan CRS-GEN-019..026) y "General letters" (1-15 usan CRS-GEN-001..015).
   **Propuesta:** renombrar la serie de ventanas a `CRS-GLW-*` (o similar) para
   que el identificador estable no induzca a error. (Cambio de datos editoriales:
   verificar que nada externo referencie los source_map viejos.)

## D. [P2-editorial] Datos sucios del ledger sin impacto funcional
- 15 bloques con `end_verse=99` (centinela "hasta el fin del capítulo" sin
  resolver) en Mateo/Marcos/Hechos. Propuesta: normalizar a `NULL`.
- Bloque 364 "Ezequiel 33" con `end_verse=39` (el capítulo tiene 33): probable
  typo 39↔33. Propuesta: `end_verse=NULL`.
- Bloque 628 "2 Cor 1-13" `end_verse=14` (versificación KJV; RVA termina en 13).
  Propuesta: `end_verse=NULL`.

## E. [P1] Warning "Cycle detected" en compiles 11/12
`CRS-1KG-005 → … → CRS-PSA-006 → … → CRS-BR-2CH-001 → …` (cadena completa en
`stream_plans.compilation_warnings` de los planes 11 y 12). El plan publicado
reporta 0 ciclos, pero el pipeline permite publicar con warnings. Revisar los
`parallel_links` de Salmos↔Reyes/Crónicas que introducen el ciclo antes del
próximo compile. (El guard técnico nuevo exige revisar warnings antes de publicar.)

## F. [P1-editorial] Era 'Misiones de Pablo y sus cartas' con sort duplicado (Plan 12)
El compile 12 introdujo esa era con `user_facing_era_sort=220`, duplicando el
sort de 'Las cartas y la expansión de la iglesia'. Dos eras con el mismo sort
rompen el orden estable del stream. Asignar un sort propio (p.ej. 215 o 221)
antes del próximo compile+publish.

## G. Verificación tras aplicar cualquiera de estas propuestas
```
php artisan harmonize:compile            # nuevo plan draft
php artisan stream-plans:verify <id>
DB_CONNECTION=mysql DB_DATABASE=bible_journey DB_USERNAME=root \
  php artisan test --testsuite=Stream    # (tras publicar; o apuntando al draft)
php artisan scripture:manifest --check   # el corpus NO debe cambiar nunca por estas ediciones
```
