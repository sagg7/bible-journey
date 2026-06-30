<?php

namespace App\Models;

use App\Enums\CertaintyLevel;
use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Location extends Model
{
    use HasTranslations;

    protected $guarded = [];

    protected $casts = [
        'certainty_level' => CertaintyLevel::class,
        'latitude' => 'float',
        'longitude' => 'float',
    ];

    public function translations(): HasMany
    {
        return $this->hasMany(LocationTranslation::class);
    }
}
