<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiInteraction extends Model
{
    protected $guarded = [];

    protected $casts = [
        'citations' => 'array',
        'cache_hit' => 'boolean',
        'token_cost' => 'decimal:6',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(HistoricalEvent::class, 'historical_event_id');
    }
}
