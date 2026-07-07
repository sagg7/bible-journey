<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'is_admin', 'has_test_access', 'institution_id', 'is_institution_admin', 'revenuecat_customer_id', 'subscription_status', 'subscription_expires_at', 'preferred_language', 'reading_level', 'reminder_settings'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'has_test_access' => 'boolean',
            'is_institution_admin' => 'boolean',
            'subscription_expires_at' => 'datetime',
            'reminder_settings' => 'array',
        ];
    }

    /**
     * Admins and institution admins may access the Filament admin panel;
     * institution admins only see their own scoped resources (see
     * shouldRegisterNavigation() overrides on individual resources).
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return (bool) $this->is_admin || (bool) $this->is_institution_admin;
    }

    public function progress(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(UserProgress::class);
    }

    public function institution(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    /**
     * True if the user should get premium content, either via their own
     * RevenueCat subscription or via an active institutional (Stripe) plan.
     */
    public function hasPremiumAccess(): bool
    {
        if ($this->has_test_access) {
            return true;
        }

        if ($this->institution_id && $this->institution?->subscribed('default')) {
            return true;
        }

        return in_array($this->subscription_status, ['premium', 'active'], true)
            && (! $this->subscription_expires_at || $this->subscription_expires_at->isFuture());
    }
}
