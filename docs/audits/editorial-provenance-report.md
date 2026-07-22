# Reporte de procedencia editorial — 2026-07-16

## Inventario de contenido editorial (BD local)

| Contenido | Filas | Origen | Procedencia registrada |
|---|---|---|---|
| CRS (unidades editoriales) | 540 | Master Canon Ledger (docs/*.xlsx) vía `ImportCanonLedger` | `source_map`, `placement/event/relation_confidence`, `review_status`, `editorial_version`, `editorial_note` ✓ |
| Contenido de estudio (resumen, contexto, personas, lugares, conexiones) | 540 | **Generado por heurística** (`crs:build-study-content`, matching de nombres contra listas fijas), `content_version='auto-v1'` | ⚠️ Sin fuente externa; `sources` solo contiene las referencias bíblicas del propio CRS |
| Espíritu de Profecía (excerpts) | 1,022 (es+en) | EGW Writings API (serie Conflicto de los Siglos: PP, PK/PR, DA/DTG, AA/HAp, GC/CS), `content_version='egw-v1'` | Libro fuente + título registrados ✓; referencia exacta (capítulo/página) ⚠️ solo si está dentro del JSON `excerpts` |
| Eventos históricos | 10 | Piloto editorial (ruta de David) | Traducciones es/en; `certainty` en enums ✓ |
| Personajes | 8 / Lugares: 8 / Rutas: 1 | Piloto editorial | ✓ |
| Notas de contexto | 0 | — | — |
| Conexiones de Salmos | 6 | Editorial | `certainty_level` ✓ (ver `docs/certainty-levels.md`) |
| Registros de evidencia (`evidence_records`) | **0** | — | ⚠️ **El mecanismo de evidencia existe y está vacío** — `ExplanationController` lo consulta pero nunca devuelve nada |
| Decisiones editoriales (`editorial_decisions`) | 92 | Ledger | ✓ |

## Cumplimiento del modelo de afirmaciones (requisito de la auditoría)

El sistema **sí puede** almacenar por afirmación: tipo (`evidence_type`), fuente
(`source_reference`), confianza (`confidence`), y el CRS registra
placement/event/relation confidence con la escala documentada
(`alta/probable/debatida/tradicion_popular/especulativa` — ver
`docs/certainty-levels.md`). Lo que falta no es esquema, es **contenido**:

1. `evidence_records` está vacío → ninguna afirmación histórica tiene hoy una
   fuente citada verificable.
2. El contenido de estudio auto-generado no distingue en la UI que es
   preliminar/generado. **Corregido a nivel API en esta auditoría**: el payload
   ahora incluye `generated: true` cuando `content_version` empieza con
   `auto-` — la app debe renderizar la etiqueta (pendiente móvil, ver risk register).
3. No hay campo de "autor/responsable editorial" ni "fecha de revisión" en
   `crs_study_contents` — propuesta de esquema en la cola de revisión.

## Distinciones exigidas (texto bíblico / dato histórico / inferencia / tradición / interpretación / IA)

- **Texto bíblico**: aislado en `bible_verses` + `passage_texts`, protegido por
  manifiesto de hashes (`data/manifests/scripture-manifest.json`). ✓
- **Inferencia cronológica**: expresada vía `placement_confidence` y
  `ExplanationController::buildPlacementRationale`, que antepone el nivel de
  certeza ("La ubicación de este pasaje… es de certeza debatida…"). ✓ Diseño correcto.
- **Interpretación adventista / Espíritu de Profecía**: separada en tabla propia,
  servida en campo propio (`spirit_of_prophecy`) con copyright del EGW Estate.
  ✓ Separación correcta; ⚠️ licencia sin comprobar → bloqueada por flag (Fase 6).
- **Contenido generado**: ahora marcado `generated: true` en la API. ⚠️ La app
  móvil aún no muestra etiqueta.
- **Fechas**: los eventos usan rangos aproximados en las traducciones del piloto;
  no se detectó presentación de fechas exactas como hecho. Mantener el patrón.

## Recomendaciones (no aplicadas automáticamente)
1. Poblar `evidence_records` para las afirmaciones de los 540 study contents
   antes de presentarlos como "contexto histórico" sin etiqueta.
2. Añadir `reviewed_by` / `reviewed_at` a `crs_study_contents` (migración
   propuesta en la cola de revisión).
3. Mientras tanto, la app debe mostrar el contenido `auto-v1` bajo un rótulo del
   tipo "Resumen preliminar generado automáticamente — pendiente de revisión
   editorial".
