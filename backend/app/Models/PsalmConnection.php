<?php

namespace App\Models;

use App\Enums\CertaintyLevel;
use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PsalmConnection extends Model
{
    use HasTranslations;

    protected $guarded = [];

    protected $casts = [
        'certainty_level' => CertaintyLevel::class,
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(HistoricalEvent::class, 'historical_event_id');
    }

    public function passage(): BelongsTo
    {
        return $this->belongsTo(Passage::class);
    }

    public function translations(): HasMany
    {
        return $this->hasMany(PsalmConnectionTranslation::class);
    }
}
