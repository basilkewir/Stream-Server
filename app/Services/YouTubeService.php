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

    public function fetchMetadata(string $url): array
    {
        $videoId = $this->parseUrl($url);
        if (!$videoId) return [];

        try {
            $cmd    = 'yt-dlp --no-playlist --dump-json ' . escapeshellarg($url) . ' 2>/dev/null';
            $output = trim((string) shell_exec($cmd));

            if (empty($output)) {
                return $this->fallbackMetadata($videoId);
            }

            $data = json_decode($output, true);
            if (!$data) {
                return $this->fallbackMetadata($videoId);
            }

            return [
                'video_id'      => $videoId,
                'title'         => $data['title'] ?? null,
                'channel'       => $data['uploader'] ?? $data['channel'] ?? null,
                'duration_sec'  => isset($data['duration']) ? (float) $data['duration'] : null,
                'view_count'    => $data['view_count'] ?? null,
                'like_count'    => $data['like_count'] ?? null,
                'upload_date'   => isset($data['upload_date'])
                    ? \Carbon\Carbon::createFromFormat('Ymd', $data['upload_date'])->toDateString()
                    : null,
                'description'   => isset($data['description'])
                    ? mb_substr($data['description'], 0, 300)
                    : null,
                'thumbnail'     => $data['thumbnail'] ?? "https://img.youtube.com/vi/{$videoId}/mqdefault.jpg",
                'webpage_url'   => $data['webpage_url'] ?? $url,
                'resolution'    => isset($data['width'], $data['height'])
                    ? "{$data['width']}x{$data['height']}"
                    : null,
                'fps'           => $data['fps'] ?? null,
            ];
        } catch (\Exception $e) {
            return $this->fallbackMetadata($videoId);
        }
    }

    public function resolveStreamUrl(string $url): ?string
    {
        try {
            $cmd    = "yt-dlp -f 'bestvideo[height<=1080][ext=mp4]+bestaudio[ext=m4a]/best[height<=1080][ext=mp4]/best[height<=1080]' -g " . escapeshellarg($url) . ' 2>/dev/null';
            $output = trim((string) shell_exec($cmd));

            if (empty($output)) return null;

            $lines = array_filter(array_map('trim', explode("\n", $output)));
            return count($lines) === 2
                ? implode('|', $lines)
                : ($lines[0] ?? null);
        } catch (\Exception $e) {
            return null;
        }
    }

    private function fallbackMetadata(string $videoId): array
    {
        return [
            'video_id'  => $videoId,
            'thumbnail' => "https://img.youtube.com/vi/{$videoId}/mqdefault.jpg",
            'webpage_url' => "https://www.youtube.com/watch?v={$videoId}",
        ];
    }
}
