<?php

namespace App\Models;

use App\Enums\CertaintyLevel;
use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContextNote extends Model
{
    use HasTranslations;

    protected $guarded = [];

    protected $casts = [
        'certainty_level' => CertaintyLevel::class,
        'sources' => 'array',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(HistoricalEvent::class, 'historical_event_id');
    }

    public function translations(): HasMany
    {
        return $this->hasMany(ContextNoteTranslation::class);
    }
}
