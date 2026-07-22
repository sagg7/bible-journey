# Mapa de datos personales — 2026-07-16

## Datos que el sistema recolecta y dónde viven

| Dato | Tabla/Almacén | Finalidad | Compartido con terceros | Eliminación |
|---|---|---|---|---|
| Nombre, email, hash de contraseña | `users` | Cuenta | No | `DELETE /api/me` (borrado) |
| Idioma preferido, nivel de lectura | `users` | Personalización | Nivel de lectura se envía a Groq como parte del prompt de Ezra (sin identidad) | Con la cuenta |
| Estado de suscripción, expiración, `revenuecat_customer_id` | `users` | Entitlements | RevenueCat (id de usuario = id interno) / Stripe (institucional) | Con la cuenta |
| Progreso de lectura (bloques/nodos/planes) | `user_canonical_progress`, `user_event_progress`, `user_progress` | Funcionalidad | No | Cascada al borrar cuenta |
| Highlights de versículos + etiquetas de colores | `verse_highlights`, `highlight_colors` | Funcionalidad | No | Cascada al borrar cuenta |
| Preguntas y respuestas de Ezra | `ai_interactions` (user_id, question, answer, tokens, costo) | Operación/costos | La PREGUNTA se envía a Groq (proveedor LLM) para generar la respuesta | `SET NULL` (anonimización) al borrar cuenta. ⚠️ Sin política de retención — propuesta: purga a 90 días |
| Tokens de sesión | `personal_access_tokens` | Auth | No | Revocados al borrar cuenta/logout |
| Progreso local, bookmark, preferencias de fuente/traducción | SharedPreferences del dispositivo | Funcionalidad offline | No | Al desinstalar la app |
| Token de sesión (móvil) | SharedPreferences ⚠️ (P1: migrar a secure storage) | Auth | No | Logout/desinstalar |
| Cache de lecturas offline | SharedPreferences (`bj_cache:*`, LRU 200) | Lectura offline | No | Al desinstalar; contenido no personal (texto bíblico/editorial) |
| Firma de institución (nombre, email de contacto) | `institutions` + Stripe Customer | Facturación B2B | Stripe | Manual (soporte) |

## Flujos hacia terceros
1. **Groq (LLM)**: pregunta del usuario + contexto editorial del pasaje + nivel de lectura. NO se envían: nombre, email, notas/highlights, progreso, ids. ✓
2. **RevenueCat**: app_user_id (id interno numérico), eventos de compra. Configuración pendiente (R-06).
3. **Stripe**: datos de facturación institucional vía Cashier/Checkout (los datos de tarjeta nunca tocan el backend) ✓.
4. **Google Fonts (app móvil)**: descarga de fuentes en runtime → petición de red a Google con IP del usuario. Declararlo en Data Safety o empaquetar las fuentes en assets (recomendado).
5. Sin analytics, sin crash reporting, sin publicidad. ✓

## Derechos del usuario
- **Eliminación**: endpoint implementado (`DELETE /api/me`, contraseña requerida). Pendiente: superficie in-app (P1) y URL web de solicitud de borrado para la ficha de Play (puede ser página en el sitio, P1).
- **Exportación**: no existe (P2) — propuesta: `GET /api/me/export` JSON con progreso+highlights.
- **Notas privadas**: los highlights/etiquetas nunca se sirven a otros usuarios (verificado por test IDOR) y no se envían a ningún tercero. ✓

## Para el formulario Data Safety de Google Play (borrador)
- Recolecta: identificadores personales (nombre, email) — requerido para cuenta; datos de app activity NO (sin analytics); compras (estado de suscripción).
- Datos cifrados en tránsito: ✓ (HTTPS).
- Mecanismo de borrado: ✓ (declarar la URL/flujo).
- Sin venta de datos, sin publicidad.
