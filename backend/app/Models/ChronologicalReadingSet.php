<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ChronologicalReadingSet extends Model
{
    protected $fillable = [
        'source_map', 'era', 'era_slug', 'sort_key',
        'title_es', 'title_en',
        'placement_confidence', 'event_confidence', 'relation_confidence',
        'review_status', 'editorial_version',
        'narrative_flow_message_es', 'transition_copy_es', 'editorial_note',
        'canon_profile', 'historical_event_id',
        'stream_role', 'user_facing_era', 'user_facing_era_sort', 'is_main_stream_node', 'display_mode',
    ];

    protected $casts = [
        'is_main_stream_node' => 'boolean',
    ];

    public function blocks(): HasMany
    {
        return $this->hasMany(ReadingBlock::class, 'crs_id')->orderBy('display_order');
    }

    public function compareGroups(): HasMany
    {
        return $this->hasMany(CompareGroup::class, 'crs_id');
    }

    public function historicalEvent(): BelongsTo
    {
        return $this->belongsTo(HistoricalEvent::class);
    }

    public function studyContent(): HasOne
    {
        return $this->hasOne(CrsStudyContent::class, 'crs_id');
    }

    public function spiritOfProphecyContents(): HasMany
    {
        return $this->hasMany(CrsSpiritOfProphecyContent::class, 'crs_id');
    }

    public function narrativeAnchor(): ?ReadingBlock
    {
        return $this->blocks()->where('role', 'narrative_anchor')->first();
    }
}
