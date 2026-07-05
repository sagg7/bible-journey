<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VerseHighlight extends Model
{
    protected $fillable = [
        'user_id', 'highlight_color_id', 'book_id',
        'chapter_number', 'verse_start', 'verse_end',
    ];

    protected $casts = [
        'chapter_number' => 'integer',
        'verse_start'    => 'integer',
        'verse_end'      => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function color(): BelongsTo
    {
        return $this->belongsTo(HighlightColor::class, 'highlight_color_id');
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(BiblicalBook::class, 'book_id');
    }
}
