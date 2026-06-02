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
                'logo_path' => $data['logo_path'] ?? null,
                'logo_position' => $data['logo_position'] ?? 'top-left',
                'logo_width' => $data['logo_width'] ?? 150,
                'ticker_text' => $data['ticker_text'] ?? null,
                'ticker_speed' => $data['ticker_speed'] ?? 50,
                'ticker_direction' => $data['ticker_direction'] ?? 'left',
                'ticker_background_color' => $data['ticker_background_color'] ?? '#00000080',
                'ticker_font_color' => $data['ticker_font_color'] ?? '#FFFFFF',
                'ticker_font_size' => $data['ticker_font_size'] ?? 24,
                'show_clock' => $data['show_clock'] ?? false,
                'clock_position' => $data['clock_position'] ?? 'top-right',
                'enabled' => $data['enabled'] ?? true,
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
