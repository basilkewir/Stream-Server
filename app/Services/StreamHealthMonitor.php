<?php

namespace App\Services;

use App\Models\Channel;
use App\Models\StreamHealthLog;
use App\Events\StreamStatusChanged;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class StreamHealthMonitor
{
    public function __construct(
        private FFmpegCommandBuilder $ffmpeg,
        private FlussonicService $flussonic,
        private YouTubeService $youtubeService,
    ) {}

    public function checkChannel(Channel $channel): array
    {
        $channel->refresh();
        $wasLive    = $channel->is_live_streaming;
        $isAlive    = $this->flussonic->isStreamAlive($channel->stream_key);

        // Live stream came back online
        if ($isAlive && !$wasLive) {
            $this->switchToLive($channel);
            return ['status' => 'live', 'action' => 'switched_to_live'];
        }

        // Still live
        if ($isAlive && $wasLive) {
            $channel->update(['last_live_timestamp' => now()]);
            return ['status' => 'live', 'action' => 'already_live'];
        }

        // Live stream just dropped
        if (!$isAlive && $wasLive) {
            $this->switchToVod($channel);
            return ['status' => 'vod', 'action' => 'switched_to_vod'];
        }

        // Still offline — ensure FFmpeg is still running (VOD or black screen)
        if (!$isAlive && !$wasLive) {
            if (!$this->isFfmpegRunning($channel)) {
                $this->startVodPlayback($channel);
                return ['status' => 'vod', 'action' => 'restarted_vod'];
            }
        }

        return ['status' => $wasLive ? 'live' : 'vod', 'action' => 'no_change'];
    }

    public function checkAllChannels(): array
    {
        $results  = [];
        $channels = Channel::with(['overlaySetting', 'vodPlaylistItems'])
            ->whereNotNull('ingest_port')
            ->get();

        foreach ($channels as $channel) {
            try {
                $results[$channel->id] = $this->checkChannel($channel);
            } catch (\Exception $e) {
                Log::error("Health check failed for channel {$channel->id}: {$e->getMessage()}");
                $results[$channel->id] = ['status' => 'error', 'action' => 'error'];
            }
        }

        return $results;
    }

    public function switchToLive(Channel $channel): void
    {
        $this->stopVodProcess($channel);

        // Reset Flussonic stream src if it was set to a YouTube URL during failover
        $this->flussonic->restartStream($channel->stream_key);

        $channel->update([
            'is_live_streaming'   => true,
            'failover_active'     => false,
            'last_live_timestamp' => now(),
            'failover_ffmpeg_pid' => null,
        ]);

        StreamHealthLog::create([
            'channel_id'      => $channel->id,
            'is_live'         => true,
            'switched_back_at' => now(),
            'event_type'      => 'live_restored',
            'message'         => "Live stream restored for channel {$channel->name}",
        ]);

        event(new StreamStatusChanged($channel, true, false, 'live_restored'));
        Log::info("Channel {$channel->id} switched back to live");
    }

    public function switchToVod(Channel $channel): void
    {
        $channel->update([
            'is_live_streaming' => false,
            'failover_active'   => true,
        ]);

        StreamHealthLog::create([
            'channel_id'        => $channel->id,
            'is_live'           => false,
            'switched_to_vod_at' => now(),
            'event_type'        => 'vod_failover',
            'message'           => "Live stream lost for channel {$channel->name}, switching to VOD failover",
        ]);

        // Always start playback - VOD if items exist, black screen if not
        $this->startVodPlayback($channel);

        event(new StreamStatusChanged($channel, false, true, 'vod_failover'));
        Log::info("Channel {$channel->id} switched to VOD failover");
    }

    private function startVodPlayback(Channel $channel): void
    {
        // Reload with relations needed for FFmpeg command
        $channel->load(['overlaySetting', 'vodPlaylistItems']);
        
        // Try Flussonic direct VOD playlist first if we have YouTube items
        if ($this->tryFlussonicVodPlaylist($channel)) {
            Log::info("Channel {$channel->id} using Flussonic direct VOD playlist.");
            return;
        }

        $cmd = $this->ffmpeg->buildVodWithOverlayCommand($channel);

        if (empty($cmd)) {
            Log::error("Empty FFmpeg command for channel {$channel->id}");
            return;
        }

        // If FFmpeg fell back to black screen but we have YouTube items,
        // try Flussonic pulling the YouTube URL directly as last resort.
        if (str_contains($cmd, 'color=c=black') && $this->tryFlussonicYoutubeFallback($channel)) {
            Log::info("Channel {$channel->id} using Flussonic YouTube fallback instead of black screen.");
            return;
        }

        // Write command to a temp script so nohup can run it cleanly
        $ffmpegDir = storage_path('ffmpeg');
        if (!is_dir($ffmpegDir)) {
            mkdir($ffmpegDir, 0755, true);
        }
        
        $scriptPath = storage_path("ffmpeg/channel_{$channel->id}.sh");
        $logPath    = storage_path("ffmpeg/channel_{$channel->id}.log");

        file_put_contents($scriptPath, "#!/bin/bash\n{$cmd}\n");
        chmod($scriptPath, 0755);

        // Launch detached from PHP process entirely
        $fullCmd = "nohup bash {$scriptPath} > {$logPath} 2>&1 < /dev/null & echo \$!";
        $pid     = trim((string) shell_exec($fullCmd));

        if ($pid && is_numeric($pid)) {
            $channel->update(['failover_ffmpeg_pid' => $pid]);
            Log::info("Started VOD FFmpeg for channel {$channel->id}, PID: {$pid}");
        } else {
            Log::error("Failed to start VOD FFmpeg for channel {$channel->id}");
        }
    }

    private function tryFlussonicVodPlaylist(Channel $channel): bool
    {
        $activeItems = $channel->vodPlaylistItems
            ->where('status', 'active')
            ->sortBy('order');

        if ($activeItems->isEmpty()) {
            return false;
        }

        // Try to create a Flussonic playlist with multiple sources
        $sources = [];
        
        foreach ($activeItems as $item) {
            if ($item->type === 'youtube') {
                $streamUrl = $this->youtubeService->resolveStreamUrl($item->file_path_or_url);
                if ($streamUrl && $streamUrl !== $item->file_path_or_url) {
                    // Successfully resolved to direct stream URL
                    $sources[] = $streamUrl;
                } else {
                    // Use original YouTube URL as fallback
                    $sources[] = $item->file_path_or_url;
                }
            } elseif ($item->type === 'upload') {
                $path = $item->file_path_or_url;
                if (Storage::disk('public')->exists($path)) {
                    $fullPath = Storage::disk('public')->url($path);
                    $sources[] = $fullPath;
                }
            }
        }

        if (empty($sources)) {
            return false;
        }

        // For now, use the first source. In the future, we could create a proper Flussonic playlist
        $primarySource = $sources[0];
        
        $success = $this->flussonic->pushVodToStream($channel->stream_key, $primarySource);
        
        if ($success) {
            $channel->update([
                'failover_active' => true,
                'failover_ffmpeg_pid' => null,
            ]);
            Log::info("Channel {$channel->id}: Flussonic is now pulling VOD: {$primarySource}");
        } else {
            Log::error("Channel {$channel->id}: Flussonic VOD push failed for: {$primarySource}");
        }

        return $success;
    }

    private function tryFlussonicYoutubeFallback(Channel $channel): bool
    {
        // If Flussonic is already pulling content (stream is alive), don't restart
        if ($this->flussonic->isStreamAlive($channel->stream_key)) {
            return true;
        }

        $youtubeItems = $channel->vodPlaylistItems
            ->where('type', 'youtube')
            ->where('status', 'active')
            ->sortBy('order');

        if ($youtubeItems->isEmpty()) {
            return false;
        }

        $firstItem = $youtubeItems->first();
        $youtubeUrl = $firstItem->file_path_or_url;

        // Resolve the YouTube URL to a direct stream URL via yt-dlp
        $streamUrl = $this->youtubeService->resolveStreamUrl($youtubeUrl);

        // If we got a real stream URL (not the original YouTube page), use it
        if ($streamUrl && $streamUrl !== $youtubeUrl) {
            Log::info("Channel {$channel->id}: YouTube URL resolved to direct stream, pushing to Flussonic.");
            $success = $this->flussonic->pushVodToStream($channel->stream_key, $streamUrl);
        } else {
            // Last resort: try the original YouTube URL (Flussonic may handle it if yt-dlp is built in)
            Log::info("Channel {$channel->id}: Could not resolve YouTube to direct stream, trying original URL with Flussonic.");
            $success = $this->flussonic->pushVodToStream($channel->stream_key, $youtubeUrl);
        }

        if ($success) {
            $channel->update([
                'failover_active' => true,
                'failover_ffmpeg_pid' => null,
            ]);
            Log::info("Channel {$channel->id}: Flussonic is now pulling YouTube: {$youtubeUrl}");
        } else {
            Log::error("Channel {$channel->id}: Flussonic YouTube fallback failed. API returned error.");
        }

        return $success;
    }

    private function stopVodProcess(Channel $channel): void
    {
        $pid = $channel->failover_ffmpeg_pid;
        if (!$pid) return;

        // Kill the FFmpeg process and any children
        shell_exec("kill -9 {$pid} 2>/dev/null");
        shell_exec("pkill -P {$pid} 2>/dev/null");

        // Clean up script file
        @unlink(storage_path("ffmpeg/channel_{$channel->id}.sh"));

        Log::info("Stopped VOD FFmpeg PID {$pid} for channel {$channel->id}");
    }

    private function isFfmpegRunning(Channel $channel): bool
    {
        $pid = $channel->failover_ffmpeg_pid;
        if (!$pid) return false;

        $result = shell_exec("kill -0 {$pid} 2>/dev/null; echo \$?");
        return trim((string) $result) === '0';
    }
}
