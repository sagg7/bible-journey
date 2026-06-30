<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserCanonicalProgress extends Model
{
    protected $table = 'user_canonical_progress';

    protected $fillable = [
        'user_id', 'block_id', 'plan_id', 'plan_version',
        'status', 'started_at', 'completed_at',
    ];

    protected $casts = [
        'started_at'   => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function user(): BelongsTo   { return $this->belongsTo(User::class); }
    public function block(): BelongsTo  { return $this->belongsTo(ReadingBlock::class, 'block_id'); }
    public function plan(): BelongsTo   { return $this->belongsTo(StreamPlan::class, 'plan_id'); }
}
