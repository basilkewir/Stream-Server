<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StreamHealthLog extends Model
{
    protected $fillable = [
        'channel_id',
        'is_live',
        'switched_to_vod_at',
        'switched_back_at',
        'event_type',
        'message',
    ];

    protected function casts(): array
    {
        return [
            'is_live' => 'boolean',
            'switched_to_vod_at' => 'datetime',
            'switched_back_at' => 'datetime',
        ];
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }
}
