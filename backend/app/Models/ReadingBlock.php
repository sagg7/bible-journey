<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReadingBlock extends Model
{
    protected $fillable = [
        'crs_id', 'source_map', 'book',
        'passage_start', 'passage_end', 'display_reference',
        'role', 'display_order',
        'display_label_es', 'display_label_en',
        'required_in_complete_mode', 'shown_in_narrative_flow',
        'placement_confidence', 'source_keys', 'passage_id',
        'start_book_id', 'start_chapter', 'start_verse',
        'end_book_id',   'end_chapter',   'end_verse',
    ];

    protected $casts = [
        'source_keys'                => 'array',
        'required_in_complete_mode'  => 'boolean',
        'shown_in_narrative_flow'    => 'boolean',
        'start_chapter'              => 'integer',
        'start_verse'                => 'integer',
        'end_chapter'                => 'integer',
        'end_verse'                  => 'integer',
    ];

    public function crs(): BelongsTo
    {
        return $this->belongsTo(ChronologicalReadingSet::class, 'crs_id');
    }

    public function passage(): BelongsTo
    {
        return $this->belongsTo(Passage::class);
    }

    public function startBook(): BelongsTo
    {
        return $this->belongsTo(BiblicalBook::class, 'start_book_id');
    }

    public function endBook(): BelongsTo
    {
        return $this->belongsTo(BiblicalBook::class, 'end_book_id');
    }

    public function outboundLinks(): HasMany
    {
        return $this->hasMany(ParallelLink::class, 'source_block_id');
    }

    public function inboundLinks(): HasMany
    {
        return $this->hasMany(ParallelLink::class, 'target_block_id');
    }

    public function audioNarrations(): HasMany
    {
        return $this->hasMany(AudioNarration::class);
    }

    public function isNarrativeAnchor(): bool
    {
        return $this->role === 'narrative_anchor';
    }
}
