<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BibleVerse extends Model
{
    protected $fillable = ['chapter_id', 'verse_number', 'translation_id', 'text'];

    protected $casts = [
        'verse_number'   => 'integer',
        'translation_id' => 'integer',
    ];

    public function chapter(): BelongsTo
    {
        return $this->belongsTo(BibleChapter::class, 'chapter_id');
    }

    public function translation(): BelongsTo
    {
        return $this->belongsTo(Translation::class);
    }
}
