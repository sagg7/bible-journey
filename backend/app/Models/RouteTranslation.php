<?php

namespace App\Models;

use App\Enums\ReviewStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RouteTranslation extends Model
{
    protected $guarded = [];

    protected $casts = ['review_status' => ReviewStatus::class];

    public function route(): BelongsTo
    {
        return $this->belongsTo(Route::class);
    }
}
