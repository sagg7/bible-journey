<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EvidenceRecord extends Model
{
    protected $fillable = [
        'source_key', 'claim', 'source_reference', 'source_book',
        'evidence_type', 'confidence', 'review_status', 'reviewer', 'notes',
    ];
}
