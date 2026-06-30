<?php

namespace App\Models;

use App\Enums\LicenseStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Traducción bíblica (RVA1909, WEB, KJV, NVI, NIV, RVR60...).
 */
class Translation extends Model
{
    protected $guarded = [];

    protected $casts = [
        'is_public_domain' => 'boolean',
        'can_display_full_text' => 'boolean',
        'license_status' => LicenseStatus::class,
    ];

    public function texts(): HasMany
    {
        return $this->hasMany(PassageText::class);
    }
}
