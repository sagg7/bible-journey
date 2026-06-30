<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Da a un modelo de contenido acceso a sus traducciones por idioma.
 *
 * El modelo debe:
 *   - definir la relación `translations(): HasMany` hacia su tabla `*_translations`
 *   - esa tabla debe tener una columna `locale`
 *
 * Uso: $event->t('title') / $event->t('title', 'en')
 */
trait HasTranslations
{
    /**
     * Devuelve el valor de un campo traducible para el locale dado,
     * con fallback al locale por defecto y luego a la primera traducción disponible.
     */
    public function t(string $field, ?string $locale = null): ?string
    {
        return $this->translation($locale)?->{$field};
    }

    /**
     * Devuelve la fila de traducción para el locale dado (con fallback).
     */
    public function translation(?string $locale = null): mixed
    {
        $locale ??= app()->getLocale();
        $fallback = config('app.fallback_locale');

        $translations = $this->relationLoaded('translations')
            ? $this->translations
            : $this->translations()->get();

        return $translations->firstWhere('locale', $locale)
            ?? $translations->firstWhere('locale', $fallback)
            ?? $translations->first();
    }

    abstract public function translations(): HasMany;
}
