<?php

namespace App\Models;

use App\Enums\ReviewStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LocationTranslation extends Model
{
    protected $guarded = [];

    protected $casts = ['review_status' => ReviewStatus::class];

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }
}
