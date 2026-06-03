<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VodPlaylistItem extends Model
{
    protected $fillable = [
        'channel_id',
        'type',
        'title',
        'file_path_or_url',
        'duration_sec',
        'file_size_bytes',
        'order',
        'scheduled_at',
        'duration_override',
        'loop_count',
        'transition',
        'status',
        'metadata_json',
    ];

    protected function casts(): array
    {
        return [
            'duration_sec' => 'float',
            'duration_override' => 'float',
            'file_size_bytes' => 'integer',
            'loop_count' => 'integer',
            'order' => 'integer',
            'scheduled_at' => 'datetime',
            'metadata_json' => 'array',
        ];
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    public function getEffectiveDurationAttribute(): float
    {
        return $this->duration_override ?? $this->duration_sec ?? 30;
    }

    public function isScheduled(): bool
    {
        return $this->scheduled_at !== null;
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
