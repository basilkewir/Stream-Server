<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Jetstream\HasProfilePhoto;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens;
    use HasFactory;
    use HasProfilePhoto;
    use Notifiable;
    use TwoFactorAuthenticatable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'subscription_plan_id',
        'storage_used_mb',
        'subscription_expires_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_recovery_codes',
        'two_factor_secret',
    ];

    protected $appends = [
        'profile_photo_url',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'subscription_expires_at' => 'datetime',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function subscriptionPlan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class);
    }

    public function channels(): HasMany
    {
        return $this->hasMany(Channel::class);
    }

    public function getStorageLimitMbAttribute(): float
    {
        return $this->subscriptionPlan?->storage_mb_limit ?? 500;
    }

    public function getMaxChannelsAttribute(): int
    {
        return $this->subscriptionPlan?->max_channels ?? 5;
    }

    public function getStorageUsedPercentAttribute(): float
    {
        $limit = $this->storage_limit_mb;
        return $limit > 0 ? min(100, round(($this->storage_used_mb / $limit) * 100, 1)) : 0;
    }

    public function getChannelsUsedPercentAttribute(): float
    {
        $max = $this->max_channels;
        return $max > 0 ? min(100, round(($this->channels()->count() / $max) * 100, 1)) : 0;
    }

    public function hasActiveSubscription(): bool
    {
        if (!$this->subscription_expires_at) return true;
        return $this->subscription_expires_at->isFuture();
    }

    public function canCreateChannel(): bool
    {
        if (!$this->hasActiveSubscription()) return false;
        $max = $this->max_channels;
        if ($max <= 0) return true;
        return $this->channels()->count() < $max;
    }
}
