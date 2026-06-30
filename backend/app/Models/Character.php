<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Character extends Model
{
    use HasTranslations;

    protected $guarded = [];

    public function translations(): HasMany
    {
        return $this->hasMany(CharacterTranslation::class);
    }

    public function firstAppearanceEvent(): BelongsTo
    {
        return $this->belongsTo(HistoricalEvent::class, 'first_appearance_event_id');
    }

    public function deathEvent(): BelongsTo
    {
        return $this->belongsTo(HistoricalEvent::class, 'death_event_id');
    }

    public function events(): BelongsToMany
    {
        return $this->belongsToMany(HistoricalEvent::class, 'event_characters')
            ->withPivot('role_in_event', 'status_at_event', 'sort_order')
            ->withTimestamps();
    }
}
