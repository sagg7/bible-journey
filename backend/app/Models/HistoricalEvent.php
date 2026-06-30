<?php

namespace App\Models;

use App\Enums\CertaintyLevel;
use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HistoricalEvent extends Model
{
    use HasTranslations;

    protected $guarded = [];

    protected $casts = [
        'certainty_level' => CertaintyLevel::class,
        'date_confidence' => CertaintyLevel::class,
        'is_premium' => 'boolean',
    ];

    public function translations(): HasMany
    {
        return $this->hasMany(EventTranslation::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function routes(): BelongsToMany
    {
        return $this->belongsToMany(Route::class, 'route_events')
            ->withPivot('sort_order')
            ->withTimestamps();
    }

    public function characters(): BelongsToMany
    {
        return $this->belongsToMany(Character::class, 'event_characters')
            ->withPivot('role_in_event', 'status_at_event', 'sort_order')
            ->withTimestamps()
            ->orderByPivot('sort_order');
    }

    public function eventCharacters(): HasMany
    {
        return $this->hasMany(EventCharacter::class)->orderBy('sort_order');
    }

    public function eventPassages(): HasMany
    {
        return $this->hasMany(EventPassage::class)->orderBy('sort_order');
    }

    public function contextNotes(): HasMany
    {
        return $this->hasMany(ContextNote::class)->orderBy('sort_order');
    }

    public function psalmConnections(): HasMany
    {
        return $this->hasMany(PsalmConnection::class)->orderBy('sort_order');
    }
}
