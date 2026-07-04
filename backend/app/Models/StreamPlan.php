<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StreamPlan extends Model
{
    protected $fillable = [
        'profile_id', 'ledger_snapshot_id', 'locale',
        'publication_status', 'is_test_only', 'validation_hash',
        'node_count', 'edge_count',
        'compilation_warnings', 'compilation_errors',
        'published_at',
    ];

    protected $casts = [
        'is_test_only'         => 'boolean',
        'compilation_warnings' => 'array',
        'compilation_errors'   => 'array',
        'published_at'         => 'datetime',
    ];

    public function nodes(): HasMany
    {
        return $this->hasMany(StreamPlanNode::class, 'plan_id')->orderBy('rank');
    }

    public function edges(): HasMany
    {
        return $this->hasMany(StreamPlanEdge::class, 'plan_id');
    }

    public function isPublished(): bool
    {
        return $this->publication_status === 'published';
    }

    public static function latestPublished(string $profile = 'cautious_default', string $locale = 'es', bool $includeTestOnly = false): ?self
    {
        return static::where('profile_id', $profile)
            ->where('locale', $locale)
            ->where('publication_status', 'published')
            ->when(! $includeTestOnly, fn ($q) => $q->where('is_test_only', false))
            ->latest('published_at')
            ->first();
    }
}
