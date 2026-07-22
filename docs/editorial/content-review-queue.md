# Cola de revisión editorial — 2026-07-16

> Cambios que requieren decisión/revisión humana. Nada aquí fue aplicado
> automáticamente. Ordenada por prioridad.

## 1. Correcciones del Plan 10 (P0/P1)
Ver documento dedicado: `docs/editorial/proposed-plan-10-corrections.md`
(179 versículos sin cubrir, duplicaciones sin documentar, remediación de la
corrupción local, ciclo en compiles 11/12, era con sort duplicado).

## 2. Contenido de estudio auto-generado (540 CRS) — P1
- `content_version='auto-v1'`, generado por matching heurístico de nombres.
- Riesgo: se presenta como "contexto histórico" sin serlo (violación del
  principio 4: inferencia presentada como hecho).
- Acción editorial: revisar y aprobar por lotes (por era), subiendo
  `content_version` a `reviewed-v1`; la API ya expone `generated: true/false`.
- Acción técnica propuesta: migración que añada `reviewed_by`/`reviewed_at`.

## 3. Espíritu de Profecía (1,022 excerpts) — P1 (bloqueado por licencia)
- Verificar: (a) términos de uso del API EGW Writings para apps comerciales
  con suscripción; (b) copyright de las traducciones al español (DTG, HAp, PR,
  CS); (c) requisitos de atribución exactos.
- Mientras: `FEATURE_SPIRIT_OF_PROPHECY=false` en producción (ya implementado).
- Revisar también que cada excerpt conserve referencia exacta (libro, capítulo,
  página) para citación verificable.

## 4. Datos del ledger con inconsistencias menores — P2
- 15 bloques `end_verse=99` (normalizar a NULL).
- Bloque 364 "Ezequiel 33" `end_verse=39` (typo probable).
- Bloque 628 "2 Cor 1-13" `end_verse=14` (versificación KJV).
- Serie `CRS-GEN-001..015` (ventanas de Cartas Generales) colisiona
  semánticamente con `CRS-GEN-019..026` (Génesis) — renombrar serie.

## 5. Piloto histórico incompleto — P2
- Solo 10 eventos / 8 personajes / 8 lugares / 1 ruta (piloto de David) frente
  a 540 CRS. `context_notes` vacío. Decidir si la v1 lanza con la función
  "personas vivas durante cada periodo" tan escasa o se oculta la sección
  cuando no hay datos (la app ya tolera listas vacías — verificado en Fase 8).
- `evidence_records` vacío: definir flujo editorial para poblarlo (quién, con
  qué fuentes académicas, y qué nivel de confianza).

## 6. Audio narraciones — P1 (bloqueado por licencia)
- Pipeline TTS (Gemini, voz Charon) documentado y probado sobre texto **NVI**
  (ver `audio-previews/` y `docs/audio-narration-charon-nvi.md`).
- Decisión editorial/legal: licenciar NVI para audio, o cambiar la fuente de
  narración a RVA1909 (dominio público). El gate técnico ya impide servir
  narraciones de traducciones sin licencia a usuarios normales.

## 7. Definición de "17 eras" vs "~47 ventanas" — P3
- 17 eras de usuario ✓ (verificado). Ventanas: 53 `literary_collection` + 9
  `associated_poetry`. Si la cifra editorial de referencia es 47, actualizar la
  documentación o etiquetar qué CRS cuentan como "ventana literaria" oficial.
