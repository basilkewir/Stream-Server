<?php

namespace App\Services;

use App\Models\VodPlaylistItem;

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

        // Try yt-dlp first (preferred for server environments)
        try {
            // Use full path and better error handling for server environment
            $ytdlpPath = $this->findYtDlpPath();
            if ($ytdlpPath) {
                $cmd = "timeout 30 {$ytdlpPath} --no-playlist --dump-json --no-warnings " . escapeshellarg($url) . ' 2>/dev/null';
                $output = shell_exec($cmd);
                
                if (!empty($output)) {
                    $data = json_decode(trim($output), true);
                    if ($data && isset($data['title'])) {
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
                    }
                }
            }
        } catch (\Exception $e) {
            \Log::warning('yt-dlp metadata extraction failed: ' . $e->getMessage());
        }

        // Try youtube-dl as fallback
        try {
            $youtubeDlPath = $this->findYoutubeDlPath();
            if ($youtubeDlPath) {
                $cmd = "timeout 30 {$youtubeDlPath} --no-playlist --dump-json --no-warnings " . escapeshellarg($url) . ' 2>/dev/null';
                $output = shell_exec($cmd);
                
                if (!empty($output)) {
                    $data = json_decode(trim($output), true);
                    if ($data && isset($data['title'])) {
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
                    }
                }
            }
        } catch (\Exception $e) {
            \Log::warning('youtube-dl metadata extraction failed: ' . $e->getMessage());
        }

        // Enhanced fallback with YouTube oEmbed API
        return $this->enhancedFallbackMetadata($videoId, $url);
    }

    public function resolveStreamUrl(string $url): ?string
    {
        // Try yt-dlp first (preferred for Flussonic integration)
        $ytdlpPath = $this->findYtDlpPath();
        if ($ytdlpPath) {
            try {
                $cmd = "timeout 30 {$ytdlpPath} -f 'bestvideo[height<=1080][ext=mp4]+bestaudio[ext=m4a]/best[height<=1080][ext=mp4]/best[height<=1080]' -g " . escapeshellarg($url) . ' 2>/dev/null';
                $output = trim((string) shell_exec($cmd));

                if (!empty($output)) {
                    $lines = array_filter(array_map('trim', explode("\n", $output)));
                    return count($lines) === 2
                        ? implode('|', $lines)
                        : ($lines[0] ?? null);
                }
            } catch (\Exception $e) {
                \Log::warning('yt-dlp stream URL resolution failed: ' . $e->getMessage());
            }
        }

        // Try youtube-dl as fallback
        $youtubeDlPath = $this->findYoutubeDlPath();
        if ($youtubeDlPath) {
            try {
                $cmd = "timeout 30 {$youtubeDlPath} -f 'bestvideo[height<=1080][ext=mp4]+bestaudio[ext=m4a]/best[height<=1080][ext=mp4]/best[height<=1080]' -g " . escapeshellarg($url) . ' 2>/dev/null';
                $output = trim((string) shell_exec($cmd));

                if (!empty($output)) {
                    $lines = array_filter(array_map('trim', explode("\n", $output)));
                    return count($lines) === 2
                        ? implode('|', $lines)
                        : ($lines[0] ?? null);
                }
            } catch (\Exception $e) {
                \Log::warning('youtube-dl stream URL resolution failed: ' . $e->getMessage());
            }
        }

        // For Flussonic integration, return original URL as last resort
        // Flussonic can sometimes handle YouTube URLs directly
        return $url;
    }

    public function refreshMetadata(VodPlaylistItem $item): bool
    {
        if ($item->type !== 'youtube') {
            return false;
        }

        $metadata = $this->fetchMetadata($item->file_path_or_url);
        
        if (!empty($metadata)) {
            $item->update([
                'title' => $metadata['title'] ?? $item->title,
                'duration_sec' => $metadata['duration_sec'] ?? $item->duration_sec,
                'metadata_json' => array_merge($item->metadata_json ?? [], $metadata),
            ]);
            return true;
        }

        return false;
    }

    private function findYtDlpPath(): ?string
    {
        $paths = [
            '/usr/local/bin/yt-dlp',
            '/usr/bin/yt-dlp',
            '/snap/bin/yt-dlp',
            '/opt/yt-dlp/yt-dlp',
            '/home/www-data/.local/bin/yt-dlp',
            trim(shell_exec('which yt-dlp 2>/dev/null') ?: ''),
            trim(shell_exec('whereis yt-dlp 2>/dev/null | cut -d: -f2 | awk "{print \$1}"') ?: ''),
        ];

        foreach ($paths as $path) {
            if (!empty($path) && is_executable($path)) {
                return $path;
            }
        }

        $plainVersion = trim(shell_exec('yt-dlp --version 2>&1') ?: '');
        if (!empty($plainVersion) && is_numeric(explode('.', $plainVersion)[0] ?? null)) {
            return 'yt-dlp';
        }

        return null;
    }

    private function findYoutubeDlPath(): ?string
    {
        $paths = [
            '/usr/local/bin/youtube-dl',
            '/usr/bin/youtube-dl',
            '/home/www-data/.local/bin/youtube-dl',
            trim(shell_exec('which youtube-dl 2>/dev/null') ?: ''),
        ];

        foreach ($paths as $path) {
            if (!empty($path) && is_executable($path)) {
                return $path;
            }
        }

        $plainVersion = trim(shell_exec('youtube-dl --version 2>&1') ?: '');
        if (!empty($plainVersion) && is_numeric(explode('.', $plainVersion)[0] ?? null)) {
            return 'youtube-dl';
        }

        return null;
    }

    public function getSystemStatus(): array
    {
        $status = [
            'yt_dlp' => [
                'available' => false,
                'path' => null,
                'version' => null,
            ],
            'youtube_dl' => [
                'available' => false,
                'path' => null,
                'version' => null,
            ],
            'flussonic' => [
                'running' => false,
                'accessible' => false,
            ],
            'permissions' => [
                'web_user_can_execute' => false,
            ],
        ];

        // Check yt-dlp
        $ytdlpPath = $this->findYtDlpPath();
        if ($ytdlpPath) {
            $status['yt_dlp']['available'] = true;
            $status['yt_dlp']['path'] = $ytdlpPath;
            $version = trim(shell_exec($ytdlpPath . ' --version 2>/dev/null') ?: '');
            $status['yt_dlp']['version'] = $version ?: 'unknown';
        }

        // Check youtube-dl
        $youtubeDlPath = $this->findYoutubeDlPath();
        if ($youtubeDlPath) {
            $status['youtube_dl']['available'] = true;
            $status['youtube_dl']['path'] = $youtubeDlPath;
            $version = trim(shell_exec($youtubeDlPath . ' --version 2>/dev/null') ?: '');
            $status['youtube_dl']['version'] = $version ?: 'unknown';
        }

        // Check Flussonic
        $flussonicRunning = shell_exec('systemctl is-active flussonic 2>/dev/null');
        $status['flussonic']['running'] = trim($flussonicRunning) === 'active';
        
        // Check if Flussonic is accessible on port 8090
        $flussonicAccessible = @fsockopen('localhost', 8090, $errno, $errstr, 1);
        $status['flussonic']['accessible'] = $flussonicAccessible !== false;
        if ($flussonicAccessible) {
            fclose($flussonicAccessible);
        }

        // Check web user permissions
        $testCmd = ($ytdlpPath ?: ($youtubeDlPath ?: 'yt-dlp')) . ' --version 2>/dev/null';
        $canExecute = !empty(shell_exec($testCmd));
        $status['permissions']['web_user_can_execute'] = $canExecute;

        return $status;
    }

    private function enhancedFallbackMetadata(string $videoId, string $url): array
    {
        // Try YouTube oEmbed API for basic metadata
        try {
            $oembedUrl = 'https://www.youtube.com/oembed?url=' . urlencode($url) . '&format=json';
            $oembedData = @file_get_contents($oembedUrl);
            
            if ($oembedData) {
                $oembed = json_decode($oembedData, true);
                if ($oembed && isset($oembed['title'])) {
                    return [
                        'video_id'      => $videoId,
                        'title'         => $oembed['title'],
                        'channel'       => $oembed['author_name'] ?? null,
                        'duration_sec'  => 180, // Default 3 minutes when unknown
                        'thumbnail'     => $oembed['thumbnail_url'] ?? "https://img.youtube.com/vi/{$videoId}/mqdefault.jpg",
                        'webpage_url'   => $url,
                        'description'   => 'YouTube video metadata could not be fully retrieved. Duration estimated at 3 minutes.',
                    ];
                }
            }
        } catch (\Exception $e) {
            // Continue to basic fallback
        }

        // Basic fallback when all else fails
        return [
            'video_id'      => $videoId,
            'title'         => 'YouTube Video',
            'duration_sec'  => 180, // Default 3 minutes
            'thumbnail'     => "https://img.youtube.com/vi/{$videoId}/mqdefault.jpg",
            'webpage_url'   => $url,
            'description'   => 'YouTube video metadata unavailable. Please install yt-dlp for full metadata extraction.',
        ];
    }
}
