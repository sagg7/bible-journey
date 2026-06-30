<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StreamPlanEdge extends Model
{
    protected $fillable = [
        'plan_id', 'from_node_id', 'to_node_id',
        'edge_type', 'score', 'priority', 'evidence_note',
    ];

    protected $casts = [
        'score' => 'float',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(StreamPlan::class, 'plan_id');
    }

    public function fromNode(): BelongsTo
    {
        return $this->belongsTo(StreamPlanNode::class, 'from_node_id');
    }

    public function toNode(): BelongsTo
    {
        return $this->belongsTo(StreamPlanNode::class, 'to_node_id');
    }
}
