<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChronologicalCoveragePath extends Model
{
    protected $fillable = [
        'plan_id',
        'bible_book_id',
        'chapter',
        'primary_stream_plan_node_id',
        'parent_era',
        'parent_era_sort',
        'entry_point_node_id',
        'display_mode',
        'complete_mode_required',
        'narrative_flow_behavior',
        'is_user_reachable',
        'rationale',
        'placement_confidence',
    ];

    protected $casts = [
        'complete_mode_required' => 'boolean',
        'is_user_reachable'      => 'boolean',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(StreamPlan::class, 'plan_id');
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(BiblicalBook::class, 'bible_book_id');
    }

    public function primaryNode(): BelongsTo
    {
        return $this->belongsTo(StreamPlanNode::class, 'primary_stream_plan_node_id');
    }

    public function entryPointNode(): BelongsTo
    {
        return $this->belongsTo(StreamPlanNode::class, 'entry_point_node_id');
    }
}
