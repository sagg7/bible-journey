<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrsSpiritOfProphecyContent extends Model
{
    protected $fillable = [
        'crs_id',
        'locale',
        'source_book_code',
        'source_book_title',
        'excerpts',
        'content_version',
    ];

    protected $casts = [
        'excerpts' => 'array',
    ];

    public function crs(): BelongsTo
    {
        return $this->belongsTo(ChronologicalReadingSet::class, 'crs_id');
    }
}
