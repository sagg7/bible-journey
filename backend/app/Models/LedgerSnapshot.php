<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LedgerSnapshot extends Model
{
    protected $fillable = [
        'snapshot_id', 'source_file', 'ledger_version',
        'crs_count', 'block_count', 'link_count', 'decision_count',
        'imported_pilots', 'status', 'import_log', 'imported_at',
    ];

    protected $casts = [
        'imported_pilots' => 'array',
        'imported_at'     => 'datetime',
    ];
}
