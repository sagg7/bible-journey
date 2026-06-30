<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserEventProgress extends Model
{
    protected $table = 'user_event_progress';

    protected $fillable = [
        'user_id', 'node_id', 'plan_id', 'plan_version',
        'state', 'pending_block_count',
        'started_at', 'primary_completed_at', 'completed_at',
    ];

    protected $casts = [
        'started_at'           => 'datetime',
        'primary_completed_at' => 'datetime',
        'completed_at'         => 'datetime',
    ];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function node(): BelongsTo { return $this->belongsTo(StreamPlanNode::class, 'node_id'); }
    public function plan(): BelongsTo { return $this->belongsTo(StreamPlan::class, 'plan_id'); }
}
