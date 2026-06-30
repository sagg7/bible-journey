<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PassageText extends Model
{
    protected $guarded = [];

    protected $casts = [
        'verses' => 'array',
    ];

    public function passage(): BelongsTo
    {
        return $this->belongsTo(Passage::class);
    }

    public function translation(): BelongsTo
    {
        return $this->belongsTo(Translation::class);
    }
}
