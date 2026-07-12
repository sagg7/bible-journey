<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class AudioNarration extends Model
{
    protected $fillable = [
        'reading_block_id',
        'translation_id',
        'provider',
        'voice',
        'model',
        'locale',
        'prompt_version',
        'source_hash',
        'prompt_hash',
        'status',
        'disk',
        'path',
        'mime_type',
        'byte_size',
        'duration_seconds',
        'segment_count',
        'attempt_count',
        'error_message',
        'generated_at',
    ];

    protected $casts = [
        'byte_size' => 'integer',
        'duration_seconds' => 'float',
        'segment_count' => 'integer',
        'attempt_count' => 'integer',
        'generated_at' => 'datetime',
    ];

    public function readingBlock(): BelongsTo
    {
        return $this->belongsTo(ReadingBlock::class);
    }

    public function translation(): BelongsTo
    {
        return $this->belongsTo(Translation::class);
    }

    public function publicUrl(): ?string
    {
        if (! $this->path) {
            return null;
        }

        return Storage::disk($this->disk ?: 'public')->url($this->path);
    }
}
