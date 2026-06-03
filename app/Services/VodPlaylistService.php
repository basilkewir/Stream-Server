<?php

namespace App\Services;

use App\Models\Channel;
use App\Models\VodPlaylistItem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class VodPlaylistService
{
    public function uploadFile(Channel $channel, UploadedFile $file, ?string $title = null): VodPlaylistItem
    {
        $user = $channel->user;
        $fileSizeMb = $file->getSize() / (1024 * 1024);

        $newTotal = $user->storage_used_mb + $fileSizeMb;
        if ($newTotal > $user->storage_limit_mb) {
            throw new \RuntimeException(
                "Storage limit exceeded. Used: {$user->storage_used_mb}MB, Limit: {$user->storage_limit_mb}MB, File: " . round($fileSizeMb, 2) . "MB"
            );
        }

        $path = $file->store("vod/{$channel->id}", 'public');
        $fullPath = Storage::disk('public')->path($path);

        $duration = $this->probeDuration($fullPath);
        
        // Generate thumbnail
        $thumbnailPath = $this->generateThumbnail($fullPath, $channel->id);

        $maxOrder = $channel->vodPlaylistItems()->max('order') ?? 0;

        $item = VodPlaylistItem::create([
            'channel_id' => $channel->id,
            'type' => 'upload',
            'title' => $title ?: pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
            'file_path_or_url' => $path,
            'duration_sec' => $duration,
            'file_size_bytes' => $file->getSize(),
            'order' => $maxOrder + 1,
            'status' => 'active',
            'loop_count' => 1,
            'transition' => 'cut',
            'metadata_json' => [
                'original_filename' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'thumbnail' => $thumbnailPath,
                'upload_date' => now()->toDateString(),
            ],
        ]);

        $user->increment('storage_used_mb', $fileSizeMb);

        return $item;
    }

    public function addYouTubeUrl(Channel $channel, string $url, ?string $title = null): VodPlaylistItem
    {
        $maxOrder = $channel->vodPlaylistItems()->max('order') ?? 0;

        $youtubeService = app(YouTubeService::class);
        $meta = $youtubeService->fetchMetadata($url);

        return VodPlaylistItem::create([
            'channel_id'       => $channel->id,
            'type'             => 'youtube',
            'title'            => $title ?: ($meta['title'] ?? 'YouTube Video'),
            'file_path_or_url' => $url,
            'duration_sec'     => $meta['duration_sec'] ?? null,
            'file_size_bytes'  => 0,
            'order'            => $maxOrder + 1,
            'status'           => 'active',
            'loop_count'       => 1,
            'transition'       => 'cut',
            'metadata_json'    => $meta ?: null,
        ]);
    }

    public function deleteItem(VodPlaylistItem $item): void
    {
        if ($item->type === 'upload') {
            $channel = $item->channel;
            $fileSizeMb = ($item->file_size_bytes ?? 0) / (1024 * 1024);
            $channel->user->decrement('storage_used_mb', $fileSizeMb);

            if (Storage::disk('public')->exists($item->file_path_or_url)) {
                Storage::disk('public')->delete($item->file_path_or_url);
            }
        }

        $item->delete();
    }

    public function reorder(Channel $channel, array $orderedIds): void
    {
        foreach ($orderedIds as $index => $id) {
            VodPlaylistItem::where('id', $id)
                ->where('channel_id', $channel->id)
                ->update(['order' => $index + 1]);
        }
    }

    public function bulkUpdate(Channel $channel, array $items): void
    {
        foreach ($items as $itemData) {
            if (empty($itemData['id'])) continue;

            $item = VodPlaylistItem::where('id', $itemData['id'])
                ->where('channel_id', $channel->id)
                ->first();

            if (!$item) continue;

            $item->update([
                'title' => $itemData['title'] ?? $item->title,
                'order' => $itemData['order'] ?? $item->order,
                'scheduled_at' => $itemData['scheduled_at'] ?? $item->scheduled_at,
                'duration_override' => $itemData['duration_override'] ?? $item->duration_override,
                'loop_count' => $itemData['loop_count'] ?? $item->loop_count,
                'transition' => $itemData['transition'] ?? $item->transition,
                'status' => $itemData['status'] ?? $item->status,
            ]);
        }
    }

    public function updateItem(Channel $channel, int $itemId, array $data): VodPlaylistItem
    {
        $item = VodPlaylistItem::where('id', $itemId)
            ->where('channel_id', $channel->id)
            ->firstOrFail();

        $item->update([
            'title' => $data['title'] ?? $item->title,
            'scheduled_at' => $data['scheduled_at'] ?? $item->scheduled_at,
            'duration_override' => $data['duration_override'] ?? $item->duration_override,
            'loop_count' => $data['loop_count'] ?? $item->loop_count,
            'transition' => $data['transition'] ?? $item->transition,
            'status' => $data['status'] ?? $item->status,
            'order' => $data['order'] ?? $item->order,
        ]);

        return $item;
    }

    public function updateChannelPlaylistSettings(Channel $channel, array $data): Channel
    {
        $channel->update([
            'playlist_mode' => $data['playlist_mode'] ?? $channel->playlist_mode,
            'playlist_loop' => $data['playlist_loop'] ?? $channel->playlist_loop,
            'playlist_fill_action' => $data['playlist_fill_action'] ?? $channel->playlist_fill_action,
        ]);

        return $channel;
    }

    public function getScheduledPlaylist(Channel $channel): array
    {
        $now = now();
        $items = $channel->vodPlaylistItems()
            ->where('status', 'active')
            ->orderBy('scheduled_at')
            ->orderBy('order')
            ->get();

        $timeline = [];
        $currentTime = null;

        foreach ($items as $item) {
            $duration = $item->effective_duration * $item->loop_count;

            if ($item->scheduled_at) {
                $currentTime = $item->scheduled_at;
            }

            $timeline[] = [
                'item' => $item,
                'start' => $currentTime,
                'end' => $currentTime ? (clone $currentTime)->addSeconds((int)$duration) : null,
                'duration' => $duration,
            ];

            if ($currentTime) {
                $currentTime = (clone $currentTime)->addSeconds((int)$duration);
            }
        }

        return $timeline;
    }

    public function getPlaylistStats(Channel $channel): array
    {
        $items = $channel->vodPlaylistItems;

        $totalDuration = 0;
        $scheduledCount = 0;
        $activeCount = 0;
        $pausedCount = 0;

        foreach ($items as $item) {
            $totalDuration += $item->effective_duration * $item->loop_count;
            if ($item->isScheduled()) $scheduledCount++;
            if ($item->status === 'active') $activeCount++;
            if ($item->status === 'paused') $pausedCount++;
        }

        return [
            'total_items' => $items->count(),
            'total_duration_sec' => $totalDuration,
            'total_duration_formatted' => $this->formatDuration($totalDuration),
            'scheduled_count' => $scheduledCount,
            'active_count' => $activeCount,
            'paused_count' => $pausedCount,
            'mode' => $channel->playlist_mode,
            'loop' => $channel->playlist_loop,
            'fill_action' => $channel->playlist_fill_action,
        ];
    }

    private function formatDuration(float $seconds): string
    {
        $h = floor($seconds / 3600);
        $m = floor(($seconds % 3600) / 60);
        $s = floor($seconds % 60);

        if ($h > 0) return "{$h}h {$m}m {$s}s";
        if ($m > 0) return "{$m}m {$s}s";
        return "{$s}s";
    }

    private function probeDuration(string $filePath): ?float
    {
        try {
            $cmd = "ffprobe -v error -show_entries format=duration -of csv=p=0 " . escapeshellarg($filePath) . " 2>&1";
            $output = shell_exec($cmd);
            return $output ? (float) trim($output) : null;
        } catch (\Exception $e) {
            return null;
        }
    }
    
    private function generateThumbnail(string $videoPath, int $channelId): ?string
    {
        try {
            $thumbnailDir = "vod/{$channelId}/thumbnails";
            $thumbnailFilename = pathinfo($videoPath, PATHINFO_FILENAME) . '_thumb.jpg';
            $thumbnailPath = $thumbnailDir . '/' . $thumbnailFilename;
            $fullThumbnailPath = Storage::disk('public')->path($thumbnailPath);

            if (!Storage::disk('public')->exists($thumbnailDir)) {
                Storage::disk('public')->makeDirectory($thumbnailDir);
            }

            $escapedVideo  = escapeshellarg($videoPath);
            $escapedOutput = escapeshellarg($fullThumbnailPath);
            $cmd = "ffmpeg -y -i {$escapedVideo} -ss 00:00:03 -vframes 1 -vf scale=320:180 {$escapedOutput} 2>&1";
            shell_exec($cmd);

            return file_exists($fullThumbnailPath) ? $thumbnailPath : null;
        } catch (\Exception $e) {
            Log::warning('Thumbnail generation failed: ' . $e->getMessage());
            return null;
        }
    }
    
    public function addDirectUrl(Channel $channel, string $url, ?string $title = null): VodPlaylistItem
    {
        $maxOrder = $channel->vodPlaylistItems()->max('order') ?? 0;
        
        // Try to get metadata from direct URL
        $metadata = $this->probeDirectUrl($url);
        
        return VodPlaylistItem::create([
            'channel_id' => $channel->id,
            'type' => 'direct_url',
            'title' => $title ?: ($metadata['title'] ?? 'Direct Stream'),
            'file_path_or_url' => $url,
            'duration_sec' => $metadata['duration'] ?? null,
            'file_size_bytes' => 0,
            'order' => $maxOrder + 1,
            'status' => 'active',
            'loop_count' => 1,
            'transition' => 'cut',
            'metadata_json' => $metadata,
        ]);
    }
    
    private function probeDirectUrl(string $url): array
    {
        try {
            $cmd = "ffprobe -v error -show_entries format=duration,format_name -show_entries stream=codec_name,width,height -of json " . escapeshellarg($url) . " 2>&1";
            $output = shell_exec($cmd);
            
            if ($output) {
                $data = json_decode($output, true);
                $format = $data['format'] ?? [];
                $streams = $data['streams'] ?? [];
                
                $videoStream = collect($streams)->firstWhere('codec_name', 'h264') 
                    ?? collect($streams)->firstWhere('codec_type', 'video')
                    ?? null;
                
                return [
                    'duration' => isset($format['duration']) ? (float) $format['duration'] : null,
                    'format' => $format['format_name'] ?? null,
                    'resolution' => $videoStream ? "{$videoStream['width']}x{$videoStream['height']}" : null,
                    'codec' => $videoStream['codec_name'] ?? null,
                    'probe_date' => now()->toDateString(),
                ];
            }
        } catch (\Exception $e) {
            Log::warning('URL probe failed: ' . $e->getMessage());
        }
        
        return ['probe_date' => now()->toDateString()];
    }
    
    public function uploadToCdn(Channel $channel, UploadedFile $file, ?string $title = null): VodPlaylistItem
    {
        // This would integrate with a CDN service like Bunny.net or Cloudflare R2
        // For now, we'll implement local storage with CDN-like URLs
        
        $user = $channel->user;
        $fileSizeMb = $file->getSize() / (1024 * 1024);

        $newTotal = $user->storage_used_mb + $fileSizeMb;
        if ($newTotal > $user->storage_limit_mb) {
            throw new \RuntimeException(
                "Storage limit exceeded. Used: {$user->storage_used_mb}MB, Limit: {$user->storage_limit_mb}MB, File: " . round($fileSizeMb, 2) . "MB"
            );
        }

        // Store with CDN-friendly structure
        $cdnPath = "cdn/vod/{$channel->id}/" . time() . '_' . $file->getClientOriginalName();
        $path = $file->storeAs('public/' . dirname($cdnPath), basename($cdnPath));
        $fullPath = Storage::disk('public')->path($path);
        
        // Generate CDN-style URL
        $cdnUrl = config('app.url') . '/storage/' . str_replace('public/', '', $path);
        
        $duration = $this->probeDuration($fullPath);
        $thumbnailPath = $this->generateThumbnail($fullPath, $channel->id);
        $maxOrder = $channel->vodPlaylistItems()->max('order') ?? 0;

        $item = VodPlaylistItem::create([
            'channel_id' => $channel->id,
            'type' => 'cdn_upload',
            'title' => $title ?: pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
            'file_path_or_url' => $cdnUrl, // Store CDN URL instead of local path
            'duration_sec' => $duration,
            'file_size_bytes' => $file->getSize(),
            'order' => $maxOrder + 1,
            'status' => 'active',
            'loop_count' => 1,
            'transition' => 'cut',
            'metadata_json' => [
                'original_filename' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'thumbnail' => $thumbnailPath,
                'cdn_url' => $cdnUrl,
                'local_path' => $path,
                'upload_date' => now()->toDateString(),
            ],
        ]);

        $user->increment('storage_used_mb', $fileSizeMb);
        return $item;
    }
}
