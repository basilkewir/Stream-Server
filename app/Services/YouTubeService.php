<?php

namespace App\Services;

class YouTubeService
{
    public function parseUrl(string $url): ?string
    {
        $patterns = [
            '/youtube\.com\/watch\?v=([a-zA-Z0-9_-]{11})/',
            '/youtu\.be\/([a-zA-Z0-9_-]{11})/',
            '/youtube\.com\/embed\/([a-zA-Z0-9_-]{11})/',
            '/youtube\.com\/shorts\/([a-zA-Z0-9_-]{11})/',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $url, $matches)) {
                return $matches[1];
            }
        }

        return null;
    }

    public function validateUrl(string $url): bool
    {
        return $this->parseUrl($url) !== null;
    }

    public function getStreamUrl(string $videoId): string
    {
        return "https://www.youtube.com/watch?v={$videoId}";
    }

    public function getYtDlpCommand(string $videoId, string $format = 'best'): string
    {
        return "yt-dlp -f \"{$format}\" -g \"https://www.youtube.com/watch?v={$videoId}\"";
    }

    public function resolveStreamUrl(string $videoId): ?string
    {
        try {
            $cmd = $this->getYtDlpCommand($videoId);
            $output = shell_exec($cmd . ' 2>/dev/null');
            if ($output) {
                return trim(explode("\n", trim($output))[0]);
            }
        } catch (\Exception $e) {
            return null;
        }

        return $this->getStreamUrl($videoId);
    }
}
