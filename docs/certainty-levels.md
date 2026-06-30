# Niveles de certeza (`certainty_level`)

Campo de primera clase del producto. Toda afirmación contextual, conexión de Salmo, fecha y
ubicación debe llevar uno de estos cinco niveles. Se renderiza como **badge** tanto en el panel
admin como en la app.

| Valor (enum)        | Etiqueta ES        | Etiqueta EN        | Significado |
|---------------------|--------------------|--------------------|-------------|
| `alta`              | Alta confianza     | High confidence    | Basada directamente en el texto bíblico o en fuerte consenso. |
| `probable`          | Probable           | Probable           | Apoyada por evidencia interna o académica, pero no absoluta. |
| `debatida`          | Debatida           | Debated            | Existen varias posturas razonables; no afirmar una sola. |
| `tradicion_popular` | Tradición popular  | Popular tradition  | Explicación extendida, pero con evidencia débil. |
| `especulativa`      | Especulativa       | Speculative        | Útil como posibilidad, nunca como afirmación. |

## Reglas de uso

1. **Nunca presentar especulación como hecho.** Si dudas entre `probable` y `debatida`, usa el más bajo.
2. **Las conexiones de Salmos** casi siempre son `probable` o `debatida`, salvo cuando el encabezado
   del Salmo nombra el evento explícitamente (entonces `alta`). Ejemplo: Salmo 34 → "David finge
   locura ante Abimelec" tiene encabezado, por eso `alta`.
3. **Las fechas** (`date_confidence`) usan la misma escala; la cronología bíblica rara vez es `alta`.
4. **Toda nota `debatida` o inferior** debe incluir una `warning_note`/`sources` explicando las posturas.
5. La IA (Ezra) **debe comunicar el nivel** en su respuesta y evitar dogmatismo en lo `debatido`.
