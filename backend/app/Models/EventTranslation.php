<?php

namespace App\Models;

use App\Enums\ReviewStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventTranslation extends Model
{
    protected $guarded = [];

    protected $casts = ['review_status' => ReviewStatus::class];

    public function event(): BelongsTo
    {
        return $this->belongsTo(HistoricalEvent::class, 'historical_event_id');
    }
}
