# Bloqueos de licencia para el release — 2026-07-16

| Bloqueo | Contenido afectado | Mecanismo de bloqueo | Cómo desbloquear |
|---|---|---|---|
| B-1 | Espíritu de Profecía (1,022 excerpts es/en) | `FEATURE_SPIRIT_OF_PROPHECY` (config/features.php) — **apagado por defecto fuera de `APP_ENV=local`**; la API devuelve `blocked: license_unverified` | Archivar ToS del API EGW + confirmación escrita del estatus de las traducciones ES; luego `FEATURE_SPIRIT_OF_PROPHECY=true` en el .env de producción |
| B-2 | Narraciones de audio basadas en NVI | Gate en `ReadingController::audioNarrationPayload` y `StreamPlanController::audioNarrationsForBlocks`: solo traducciones con `can_display_full_text=1` para usuarios sin `has_test_access` | Licenciar NVI para audio (Biblica) **o** regenerar narraciones con RVA1909 y marcar esa traducción como fuente |
| B-3 | Traducciones NVI/RVR60/NIV/RVR1995/TLA | Ya bloqueadas por diseño: `can_display_full_text=0`, 0 versículos importados | Contrato de licencia por traducción; importar solo tras `license_status=licensed` |
| B-4 | Imágenes de eras + ícono (origen sin documentar) | Sin bloqueo técnico (assets embebidos en la app) | Documentar origen/licencia en el registro antes de subir a Play; sustituir cualquier asset sin procedencia |

Verificación rápida del estado de bloqueos en un entorno:
```
php artisan tinker --execute="dump(config('features.spirit_of_prophecy'));"
# false en producción = bloqueado ✓
```

La Biblia canónica (RVA1909) permanece **gratuita y sin bloqueo**: `GET /api/readings/books`, `GET /api/readings/book/{osis}/chapter/{n}` son públicos y sirven solo traducciones `can_display_full_text=1`.
