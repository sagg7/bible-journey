<?php

namespace App\Support;

use App\Models\Translation;
use Illuminate\Http\Request;

/**
 * Decide qué traducción bíblica usar para una petición y resuelve, por pasaje,
 * si se entrega texto completo (dominio público / licenciada) o solo la referencia.
 */
class BibleTranslationResolver
{
    /**
     * Traducción seleccionada para la petición:
     *   ?translation=CODE  →  esa; si no, la primera de dominio público del idioma actual.
     */
    public static function forRequest(Request $request): ?Translation
    {
        if ($code = $request->query('translation')) {
            $t = Translation::where('code', $code)->first();
            if ($t) {
                return $t;
            }
        }

        $locale = app()->getLocale();

        return Translation::where('language', $locale)
            ->where('can_display_full_text', true)
            ->orderBy('sort_order')
            ->first()
            ?? Translation::where('can_display_full_text', true)->orderBy('sort_order')->first();
    }

    /**
     * Carga útil de un pasaje respetando las reglas de licencia.
     *
     * @return array<string, mixed>
     */
    public static function passagePayload(\App\Models\Passage $passage, ?Translation $translation): array
    {
        $text = $translation ? $passage->textFor($translation) : null;

        return [
            'reference' => $passage->reference_label,
            'translation' => $translation?->code,
            'translation_name' => $translation?->name,
            'text_available' => $text !== null,
            'text' => $text?->content,
            // Cuando no hay texto (traducción protegida sin licencia) la app muestra solo la referencia.
            'reference_only_reason' => $text === null && $translation && ! $translation->can_display_full_text
                ? 'license_required'
                : ($text === null ? 'not_imported' : null),
            'attribution' => $translation?->attribution,
        ];
    }
}
