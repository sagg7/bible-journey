<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CompareGroup extends Model
{
    protected $fillable = [
        'crs_id', 'source_map',
        'title_es', 'title_en',
        'editorial_summary_es', 'editorial_summary_en',
        'disclaimer_es', 'disclaimer_en',
        'relation_level',
        'key_differences_es', 'key_differences_en',
        'review_status',
    ];

    protected $casts = [
        'key_differences_es' => 'array',
        'key_differences_en' => 'array',
    ];

    public function crs(): BelongsTo
    {
        return $this->belongsTo(ChronologicalReadingSet::class, 'crs_id');
    }

    public function links(): HasMany
    {
        return $this->hasMany(ParallelLink::class);
    }
}
