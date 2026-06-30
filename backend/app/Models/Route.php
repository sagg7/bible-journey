<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Route extends Model
{
    use HasTranslations;

    protected $guarded = [];

    protected $casts = [
        'is_premium' => 'boolean',
    ];

    public function translations(): HasMany
    {
        return $this->hasMany(RouteTranslation::class);
    }

    public function events(): BelongsToMany
    {
        return $this->belongsToMany(HistoricalEvent::class, 'route_events')
            ->withPivot('sort_order')
            ->withTimestamps()
            ->orderByPivot('sort_order');
    }
}
