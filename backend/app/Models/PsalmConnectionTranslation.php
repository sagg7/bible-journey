<?php

namespace App\Models;

use App\Enums\ReviewStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PsalmConnectionTranslation extends Model
{
    protected $guarded = [];

    protected $casts = ['review_status' => ReviewStatus::class];

    public function psalmConnection(): BelongsTo
    {
        return $this->belongsTo(PsalmConnection::class);
    }
}
