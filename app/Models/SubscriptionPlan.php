<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionPlan extends Model
{
    protected $fillable = [
        'name',
        'price',
        'storage_mb_limit',
        'max_channels',
        'features_json',
    ];

    protected function casts(): array
    {
        return [
            'features_json' => 'array',
            'price' => 'decimal:2',
            'max_channels' => 'integer',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
