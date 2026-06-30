<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EditorialDecision extends Model
{
    protected $fillable = [
        'source_key', 'topic', 'status', 'impact_scope',
        'interim_policy', 'owner', 'affected_crs', 'notes',
    ];

    protected $casts = [
        'affected_crs' => 'array',
    ];
}
