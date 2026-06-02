<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VodOverlaySetting extends Model
{
    protected $table = 'vod_overlay_settings';

    protected $fillable = [
        'channel_id',
        'logo_path',
        'logo_position',
        'logo_width',
        'ticker_text',
        'ticker_speed',
        'ticker_direction',
        'ticker_background_color',
        'ticker_font_color',
        'ticker_font_size',
        'show_clock',
        'clock_position',
        'enabled',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'show_clock' => 'boolean',
        ];
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }
}
