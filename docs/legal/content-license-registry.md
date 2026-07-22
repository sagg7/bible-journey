# Registro de licencias de contenido — 2026-07-16

Estados: **autorizado** · **pendiente** (sin comprobar → bloqueado en prod) · **rechazado** · **desconocido**.
Regla operativa: "antiguo" NO implica dominio público; las traducciones son obras derivadas con copyright propio.

| # | Recurso | Propietario / Fuente | Licencia | Territorios | Uso comercial | Evidencia | Restricciones | Estado |
|---|---|---|---|---|---|---|---|---|
| 1 | RVA1909 (texto es, 30,959 versículos) | Dominio público (Reina-Valera 1909) | PD | Global | Sí | Publicación 1909 (>95 años); fuente: ebible.org (spaRV1909), `source_url` + `source_file_hash` registrados en BD ✓ | Ninguna | **autorizado** |
| 2 | KJV (texto en, 31,102 versículos) | Dominio público* | PD (excepto UK) | Global excepto UK | Sí | Publicación 1611/1769 | *En Reino Unido: privilegio perpetuo de la Corona (Cambridge/Oxford letters patent). Si se distribuye en UK, requiere permiso o exclusión territorial | **autorizado** fuera de UK; **pendiente** para UK |
| 3 | WEB / BSB (declaradas, sin importar) | PD / CC-equivalente (BSB: dominio público declarado por Berean) | PD | Global | Sí | — | BSB pide cortesía de atribución | autorizado (sin contenido aún) |
| 4 | NVI, RVR60, NIV, RVR1995, TLA (declaradas, 0 versículos) | Biblica / Sociedades Bíblicas Unidas | Copyright | — | Requiere licencia | Ninguna | `can_display_full_text=0`, `license_status=pending` ✓ | **pendiente** (correctamente bloqueadas) |
| 5 | Espíritu de Profecía — originales EN (PP 1890, DA 1898, AA 1911, GC 1911, PK 1917) | Dominio público EE.UU. (pre-1929) | PD (US) | US seguro; otros territorios verificar | Sí (US) | Fechas de publicación | El EGW Estate reclama derechos sobre compilaciones/ediciones nuevas | autorizado (US) |
| 6 | Espíritu de Profecía — traducciones ES (DTG, HAp, PR, CS; 1,022 excerpts en BD) | EGW Estate / casas editoras adventistas | **Copyright vigente** (obra derivada; DTG ed. 1955) | — | **No sin permiso escrito** | ToS archivados 2026-07-16: `docs/legal/egw-tos-evidence.md` — el aviso legal del Estate permite solo "citas breves" y prohíbe republicar/transmitir | Redistribución en app de pago excede fair use | **BLOQUEADO en prod** (`FEATURE_SPIRIT_OF_PROPHECY`) — pedir permiso escrito al Estate |
| 7 | EGW Writings API (servicio) | Ellen G. White Estate | Legal notice + EULA (archivados) | — | Acceso técnico ≠ licencia de redistribución comercial | `docs/legal/egw-tos-evidence.md` | Ver ítem 6 | **verificado 2026-07-16 — no autoriza el uso actual** |
| 8 | Narraciones de audio TTS (Gemini TTS, voz Charon) — generadas desde texto NVI | Texto: Biblica (NVI). Audio generado: Google Gemini TTS (output usable comercialmente según términos de Gemini API) | Texto fuente sin licencia | — | No hasta licenciar NVI | `audio-previews/nvi-gemini-*`, `docs/audio-narration-charon-nvi.md` | Gate técnico implementado: narraciones solo de traducciones `can_display_full_text=1` para usuarios normales | **rechazado para prod con NVI** — alternativa: regenerar con RVA1909 (PD) |
| 9 | Contenido de estudio auto-generado (540) | Propio (generado internamente) | Propio | Global | Sí | `content_version='auto-v1'` | Etiquetar como generado (API ya lo marca) | autorizado |
| 10 | Contenido editorial propio (CRS, eras, explicaciones, decisiones) | Codeshore / Bible Journey | Propio | Global | Sí | Master Canon Ledger docs/*.xlsx | — | autorizado |
| 11 | Imágenes de eras (`mobile/assets/images/eras/`) e ícono de la app | Codeshore (declaración del propietario 2026-07-16: imágenes generadas con ChatGPT — el output pertenece al usuario según los términos de OpenAI; ícono de autoría propia) | Propio | Global | Sí | Declaración del propietario registrada en esta auditoría | Nota: las imágenes IA no llevan copyright de terceros, pero tampoco son registrables como obra propia en algunas jurisdicciones | **autorizado** |
| 12 | Tipografías (paquete `google_fonts` en Flutter) | Google Fonts | OFL/Apache | Global | Sí | pub.dev google_fonts | Descarga en runtime (primera vez) — considerar bundling para offline | autorizado |
| 13 | Groq / LLM (Ezra respuestas) | Salida generada; términos de Groq | Términos API | — | Verificar | — | No presentar como contenido con autoridad; ver Fase 12 | autorizado con condiciones |

## Acciones requeridas antes del release
1. Archivar en `docs/legal/` los términos del API de EGW y la decisión sobre traducciones ES (ítems 6-7).
2. Decidir fuente de audio: licencia NVI **o** regenerar narraciones con RVA1909 (ítem 8).
3. Documentar origen/licencia de las imágenes de eras y el ícono (ítem 11).
4. Completar `translations.source_url` para RVA1909 y KJV (evidencia de fuente).
5. Si se distribuye en Reino Unido, resolver el estatus KJV (ítem 2) o excluir la KJV para ese territorio.
