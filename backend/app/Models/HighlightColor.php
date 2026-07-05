<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HighlightColor extends Model
{
    protected $fillable = ['user_id', 'color_hex', 'label'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function verseHighlights(): HasMany
    {
        return $this->hasMany(VerseHighlight::class);
    }
}
