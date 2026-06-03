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
        'lower_third_title',
        'lower_third_subtitle',
        'lower_third_position',
        'lower_third_bg_color',
        'lower_third_text_color',
        'lower_third_font_size',
        'lower_third_duration',
        'show_lower_third',
        'crawl_text',
        'crawl_speed',
        'crawl_bg_color',
        'crawl_text_color',
        'crawl_font_size',
        'show_crawl',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'show_clock' => 'boolean',
            'show_lower_third' => 'boolean',
            'show_crawl' => 'boolean',
        ];
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }
}
