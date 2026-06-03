<?php

namespace App\Services;

use App\Models\Channel;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class OverlayService
{
    public function updateSettings(Channel $channel, array $data): void
    {
        $channel->overlaySetting()->updateOrCreate(
            ['channel_id' => $channel->id],
            [
                'logo_position'           => $data['logo_position'] ?? 'top-left',
                'logo_width'              => $data['logo_width'] ?? 150,
                'ticker_text'             => $data['ticker_text'] ?? null,
                'ticker_speed'            => $data['ticker_speed'] ?? 50,
                'ticker_direction'        => $data['ticker_direction'] ?? 'left',
                'ticker_background_color' => $data['ticker_background_color'] ?? '#00000080',
                'ticker_font_color'       => $data['ticker_font_color'] ?? '#FFFFFF',
                'ticker_font_size'        => $data['ticker_font_size'] ?? 24,
                'show_clock'              => $data['show_clock'] ?? false,
                'clock_position'          => $data['clock_position'] ?? 'top-right',
                'enabled'                 => $data['enabled'] ?? true,
                'lower_third_title'       => $data['lower_third_title'] ?? null,
                'lower_third_subtitle'    => $data['lower_third_subtitle'] ?? null,
                'lower_third_position'    => $data['lower_third_position'] ?? 'bottom-left',
                'lower_third_bg_color'    => $data['lower_third_bg_color'] ?? '#1a1a1aCC',
                'lower_third_text_color'  => $data['lower_third_text_color'] ?? '#FFFFFF',
                'lower_third_font_size'   => $data['lower_third_font_size'] ?? 32,
                'lower_third_duration'    => $data['lower_third_duration'] ?? 5,
                'show_lower_third'        => $data['show_lower_third'] ?? false,
                'crawl_text'              => $data['crawl_text'] ?? null,
                'crawl_speed'             => $data['crawl_speed'] ?? 80,
                'crawl_bg_color'          => $data['crawl_bg_color'] ?? '#000000CC',
                'crawl_text_color'        => $data['crawl_text_color'] ?? '#FFFF00',
                'crawl_font_size'         => $data['crawl_font_size'] ?? 28,
                'show_crawl'              => $data['show_crawl'] ?? false,
            ]
        );
    }

    public function uploadLogo(Channel $channel, UploadedFile $file): string
    {
        $path = $file->store("overlays/{$channel->id}", 'public');

        $overlay = $channel->overlaySetting()->updateOrCreate(
            ['channel_id' => $channel->id],
            ['logo_path' => $path]
        );

        return Storage::disk('public')->url($path);
    }

    public function getSettings(Channel $channel): ?array
    {
        $settings = $channel->overlaySetting;
        if (!$settings) {
            return null;
        }

        $data = $settings->toArray();
        if ($settings->logo_path) {
            $data['logo_url'] = Storage::disk('public')->url($settings->logo_path);
        }

        return $data;
    }

    public function buildOverlayPreviewFilters(Channel $channel): array
    {
        $settings = $channel->overlaySetting;
        if (!$settings || !$settings->enabled) {
            return [];
        }

        return [
            'logo' => $settings->logo_path ? [
                'url' => Storage::disk('public')->url($settings->logo_path),
                'position' => $settings->logo_position,
                'width' => $settings->logo_width,
            ] : null,
            'ticker' => $settings->ticker_text ? [
                'text' => $settings->ticker_text,
                'speed' => $settings->ticker_speed,
                'direction' => $settings->ticker_direction,
                'background_color' => $settings->ticker_background_color,
                'font_color' => $settings->ticker_font_color,
                'font_size' => $settings->ticker_font_size,
            ] : null,
            'clock' => [
                'enabled' => $settings->show_clock,
                'position' => $settings->clock_position,
            ],
        ];
    }
}
