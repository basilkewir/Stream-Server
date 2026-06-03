<?php

namespace App\Services;

use App\Models\Channel;
use App\Models\VodPlaylistItem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

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

        $duration = $this->probeDuration(Storage::disk('public')->path($path));

        $maxOrder = $channel->vodPlaylistItems()->max('order') ?? 0;

        $item = VodPlaylistItem::create([
            'channel_id' => $channel->id,
            'type' => 'upload',
            'title' => $title ?: $file->getClientOriginalName(),
            'file_path_or_url' => $path,
            'duration_sec' => $duration,
            'file_size_bytes' => $file->getSize(),
            'order' => $maxOrder + 1,
            'status' => 'active',
            'loop_count' => 1,
            'transition' => 'cut',
        ]);

        $user->increment('storage_used_mb', $fileSizeMb);

        return $item;
    }

    public function addYouTubeUrl(Channel $channel, string $url, ?string $title = null): VodPlaylistItem
    {
        $maxOrder = $channel->vodPlaylistItems()->max('order') ?? 0;

        // Try to get duration via yt-dlp
        $duration = $this->probeYoutubeDuration($url);

        // Try to get title via yt-dlp if not provided
        if (!$title) {
            $title = $this->probeYoutubeTitle($url) ?? 'YouTube Video';
        }

        return VodPlaylistItem::create([
            'channel_id'       => $channel->id,
            'type'             => 'youtube',
            'title'            => $title,
            'file_path_or_url' => $url,
            'duration_sec'     => $duration,
            'file_size_bytes'  => 0,
            'order'            => $maxOrder + 1,
            'status'           => 'active',
            'loop_count'       => 1,
            'transition'       => 'cut',
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
            $cmd = "ffprobe -v error -show_entries format=duration -of csv=p=0 \"{$filePath}\"";
            $output = shell_exec($cmd);
            return $output ? (float) trim($output) : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    private function probeYoutubeDuration(string $url): ?float
    {
        try {
            $cmd = 'yt-dlp --no-playlist --print "%(duration)s" ' . escapeshellarg($url) . ' 2>/dev/null';
            $output = trim((string) shell_exec($cmd));
            return is_numeric($output) ? (float) $output : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    private function probeYoutubeTitle(string $url): ?string
    {
        try {
            $cmd = 'yt-dlp --no-playlist --print "%(title)s" ' . escapeshellarg($url) . ' 2>/dev/null';
            $output = trim((string) shell_exec($cmd));
            return !empty($output) ? $output : null;
        } catch (\Exception $e) {
            return null;
        }
    }
}
