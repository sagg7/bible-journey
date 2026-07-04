<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Cashier\Billable;

class Institution extends Model
{
    use Billable;

    protected $fillable = ['name', 'seats'];

    public function members(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
