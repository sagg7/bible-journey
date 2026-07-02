<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrsStudyContent extends Model
{
    protected $fillable = [
        'crs_id',
        'summary_es',
        'context_es',
        'people',
        'places',
        'connections',
        'sources',
        'content_version',
    ];

    protected $casts = [
        'people' => 'array',
        'places' => 'array',
        'connections' => 'array',
        'sources' => 'array',
    ];

    public function crs(): BelongsTo
    {
        return $this->belongsTo(ChronologicalReadingSet::class, 'crs_id');
    }
}
