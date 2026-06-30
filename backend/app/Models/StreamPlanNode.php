<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StreamPlanNode extends Model
{
    protected $fillable = [
        'plan_id', 'crs_id', 'rank',
        'display_mode', 'required_state',
        'explanation_es', 'explanation_en',
        'compare_group_id',
        'stream_role', 'user_facing_era', 'user_facing_era_sort', 'is_main_stream_node',
    ];

    protected $casts = [
        'is_main_stream_node' => 'boolean',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(StreamPlan::class, 'plan_id');
    }

    public function crs(): BelongsTo
    {
        return $this->belongsTo(ChronologicalReadingSet::class, 'crs_id');
    }

    public function compareGroup(): BelongsTo
    {
        return $this->belongsTo(CompareGroup::class);
    }

    public function outboundEdges(): HasMany
    {
        return $this->hasMany(StreamPlanEdge::class, 'from_node_id');
    }

    public function inboundEdges(): HasMany
    {
        return $this->hasMany(StreamPlanEdge::class, 'to_node_id');
    }
}
