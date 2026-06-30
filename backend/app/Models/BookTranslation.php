<?php

namespace App\Models;

use App\Enums\ReviewStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookTranslation extends Model
{
    protected $guarded = [];

    protected $casts = ['review_status' => ReviewStatus::class];

    public function book(): BelongsTo
    {
        return $this->belongsTo(BiblicalBook::class, 'biblical_book_id');
    }
}
