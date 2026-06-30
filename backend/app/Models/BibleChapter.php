<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BibleChapter extends Model
{
    protected $fillable = ['biblical_book_id', 'chapter_number', 'verse_count'];

    protected $casts = [
        'chapter_number' => 'integer',
        'verse_count'    => 'integer',
    ];

    public function book(): BelongsTo
    {
        return $this->belongsTo(BiblicalBook::class, 'biblical_book_id');
    }

    public function verses(): HasMany
    {
        return $this->hasMany(BibleVerse::class, 'chapter_id');
    }

    public function versesForTranslation(int $translationId)
    {
        return $this->verses()
            ->where('translation_id', $translationId)
            ->orderBy('verse_number');
    }
}
