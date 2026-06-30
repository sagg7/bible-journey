<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ParallelLink extends Model
{
    protected $fillable = [
        'source_block_id', 'target_block_id',
        'relation_type', 'confidence',
        'evidence_note', 'compare_group_id', 'approved',
    ];

    protected $casts = [
        'approved' => 'boolean',
    ];

    public function sourceBlock(): BelongsTo
    {
        return $this->belongsTo(ReadingBlock::class, 'source_block_id');
    }

    public function targetBlock(): BelongsTo
    {
        return $this->belongsTo(ReadingBlock::class, 'target_block_id');
    }

    public function compareGroup(): BelongsTo
    {
        return $this->belongsTo(CompareGroup::class);
    }
}
