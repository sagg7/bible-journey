# Resumen ejecutivo — Auditoría de release Bible Journey (2026-07-16)

## Veredicto
La app **no está lista para publicar hoy**, pero está cerca. Los cimientos son
sólidos: el corpus bíblico está íntegro y ahora criptográficamente verificable,
la privacidad entre usuarios es correcta, la Biblia canónica es gratuita por
diseño, y las suites de pruebas quedaron en verde (backend 34/34, Flutter
17/17, release build OK). Bloquean el lanzamiento: **(1)** el Plan 10 de la BD
local está corrupto y el flujo de deploy lo publicaría tal cual; **(2)** 179
versículos —incluido el nacimiento de Jesús (Lucas 2:3-20)— quedan fuera del
plan cronológico por errores de frontera editoriales; **(3)** RevenueCat sin
configurar; **(4)** dos bloqueos de licencia (EGW español, audio NVI) ya
contenidos por feature flags; **(5)** pendientes de política de Play
(pantalla de borrado de cuenta + página web).

## Números verificados contra la BD (no asumidos)
| Métrica | Esperado | Real | |
|---|---|---|---|
| Libros | 66 | 66 | ✓ |
| Capítulos | 1,189 | 1,189 | ✓ |
| Versículos RVA1909 | ~30,959 | 30,959 | ✓ (además KJV: 31,102) |
| Plan publicado | Plan 10 | Plan 10 (v9.1), único published | ✓ |
| Plan 9 | archivado | archivado | ✓ |
| Nodos cronológicos | ~540 | 540 CRS / 540 nodos | ✓ |
| Ventanas literarias | ~47 | 53 literary_collection (+9 poesía) | ≈ (definición por confirmar) |
| Eras | 17 | 17 a nivel CRS; **16 en el Plan 10 local** (corrupción) | ⚠️ |

## Estado por área
- **Texto bíblico**: íntegro (0 duplicados/vacíos/huecos/encoding). Manifiesto SHA-256 por corpus/libro/capítulo + comando `scripture:manifest --check`. Diferencias de versificación RVA↔KJV documentadas (Salmos, Job 38-41, 2Co 13) — no son errores.
- **Plan 10**: cobertura por capítulo 1189/1189 ✓; por versículo 30,780/30,959 (179 sin cubrir → propuesta editorial). 2,233 versículos repetidos: mayoría con diseño intencional (ventanas Génesis↔epístolas, fallbacks) pero sin justificación registrada en datos → cola editorial.
- **Publicación de planes**: flujo publish/rollback atómico y auditado ✓; se agregó migración real de progreso (no existía — los usuarios habrían perdido su avance) e inmutabilidad de planes publicados (la falta de ese guard causó la corrupción local).
- **Monetización**: gating correcto y probado (Biblia gratis, premium con candado, expiración respetada, cancelación conserva el periodo pagado — este último era un bug del webhook, corregido). RevenueCat pendiente de configuración real.
- **Seguridad**: sin IDOR (probado); rate limiting agregado; borrado de cuenta implementado (endpoint); token móvil sin cifrar y credenciales en HANDOFF.md pendientes (rotar Groq/EGW).
- **Ezra**: diseño honesto (niveles de certeza, sin notas privadas al LLM, gate premium antes del cache); falta verificación de citas contra el corpus (diseño propuesto) y evaluaciones de 12 casos listas para ejecutar.
- **Offline**: implementado en esta auditoría (cache LRU con fallback) — lo leído queda disponible sin conexión.
- **Google Play**: targetSdk 36 ✓, permisos mínimos ✓, firma ✓, privacy policy ✓; faltan Data Safety, IARC, borrado in-app/web y closed testing.

## Los 17 entregables
1. Resumen ejecutivo — este documento. 2. Hallazgos P0-P3 — `release-risk-register.md`. 3. Mapa del sistema — `current-system-map.md`. 4. Integridad bíblica — `scripture-integrity-report.md` + `data/manifests/scripture-manifest.json`. 5. Cobertura cronológica — en `proposed-plan-10-corrections.md` + vistas SQL. 6. Propuestas editoriales — `docs/editorial/*`. 7. Licencias — `docs/legal/*`. 8-9. Seguridad y privacidad — `security-review.md`, `privacy-data-map.md`. 10. UX/accesibilidad — hallazgos en risk register (R-08/R-23/R-26) + baseline. 11. Suite de pruebas — 34 backend + 17 Flutter. 12. Correcciones — resumen al final del risk register. 13. Google Play — `docs/release/*`. 14. Riesgos restantes — risk register. 15. Archivos modificados — `git status` (28 modificados, 15 nuevos). 16. Comandos — `release-checklist.md` §Comandos. 17. Decisiones humanas pendientes — abajo.

## Decisiones que necesitan aprobación humana
1. Remediación del Plan 10 local: ¿recompilar+publicar o restaurar desde producción? (§A de proposed-plan-10-corrections)
2. Aprobar los 18 ajustes de frontera para los 179 versículos sin cubrir (§B).
3. Documentar/decidir las repeticiones intencionales (ventanas Génesis, fallbacks de epístolas) (§C).
4. Licencias: términos EGW + traducciones ES; audio NVI vs regenerar con RVA1909; origen de imágenes de eras.
5. Estrategia de sync BD local↔prod (prod tiene 526 CRS vs 540 local y usuarios reales — el reemplazo completo ya no es viable).
6. Observabilidad: ¿agregar crash reporting? (afecta Data Safety).
7. Encender o no la sección de personas/eventos históricos con solo el piloto de David poblado.
