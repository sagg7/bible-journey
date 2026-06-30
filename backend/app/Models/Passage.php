<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Passage extends Model
{
    protected $guarded = [];

    public function book(): BelongsTo
    {
        return $this->belongsTo(BiblicalBook::class, 'biblical_book_id');
    }

    public function texts(): HasMany
    {
        return $this->hasMany(PassageText::class);
    }

    /**
     * Texto de este pasaje en una traducción dada, solo si la traducción
     * permite mostrar texto completo. En otro caso devuelve null (solo referencia).
     */
    public function textFor(Translation $translation): ?PassageText
    {
        if (! $translation->can_display_full_text) {
            return null;
        }

        return $this->texts->firstWhere('translation_id', $translation->id)
            ?? $this->texts()->where('translation_id', $translation->id)->first();
    }
}
