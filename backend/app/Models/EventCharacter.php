<?php

namespace App\Models;

use App\Enums\CharacterStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventCharacter extends Model
{
    protected $guarded = [];

    protected $casts = [
        'status_at_event' => CharacterStatus::class,
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(HistoricalEvent::class, 'historical_event_id');
    }

    public function character(): BelongsTo
    {
        return $this->belongsTo(Character::class);
    }
}
