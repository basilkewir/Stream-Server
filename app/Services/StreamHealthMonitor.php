<?php

namespace App\Services;

use App\Models\Channel;
use App\Models\StreamHealthLog;
use App\Events\StreamStatusChanged;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class StreamHealthMonitor
{
    public function __construct(
        private FFmpegCommandBuilder $ffmpeg,
        private FlussonicService $flussonic,
    ) {}

    public function checkChannel(Channel $channel): array
    {
        $streamKey = $channel->stream_key;
        $wasLive = $channel->is_live_streaming;

        $isAlive = $this->flussonic->isStreamAlive($streamKey);

        if ($isAlive && !$wasLive) {
            $this->switchToLive($channel);
            return ['status' => 'live', 'action' => 'switched_to_live'];
        }

        if ($isAlive && $wasLive) {
            $channel->update(['last_live_timestamp' => now()]);
            return ['status' => 'live', 'action' => 'already_live'];
        }

        if (!$isAlive && $wasLive) {
            $this->switchToVod($channel);
            return ['status' => 'vod', 'action' => 'switched_to_vod'];
        }

        return ['status' => $wasLive ? 'live' : 'vod', 'action' => 'no_change'];
    }

    public function checkAllChannels(): array
    {
        $results = [];
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

        $channel->update([
            'is_live_streaming' => true,
            'failover_active' => false,
            'last_live_timestamp' => now(),
            'failover_ffmpeg_pid' => null,
        ]);

        StreamHealthLog::create([
            'channel_id' => $channel->id,
            'is_live' => true,
            'switched_back_at' => now(),
            'event_type' => 'live_restored',
            'message' => "Live stream restored for channel {$channel->name}",
        ]);

        event(new StreamStatusChanged($channel, true, false, 'live_restored'));

        Log::info("Switched channel {$channel->id} back to live stream");
    }

    public function switchToVod(Channel $channel): void
    {
        $channel->update([
            'is_live_streaming' => false,
            'failover_active' => true,
        ]);

        StreamHealthLog::create([
            'channel_id' => $channel->id,
            'is_live' => false,
            'switched_to_vod_at' => now(),
            'event_type' => 'vod_failover',
            'message' => "Live stream lost for channel {$channel->name}, switching to VOD failover",
        ]);

        $this->startVodPlayback($channel);

        event(new StreamStatusChanged($channel, false, true, 'vod_failover'));

        Log::info("Switched channel {$channel->id} to VOD failover");
    }

    private function startVodPlayback(Channel $channel): void
    {
        $cmd = $this->ffmpeg->buildVodWithOverlayCommand($channel);

        try {
            $process = Process::start($cmd);
            $pid = $process->id();
            $channel->update(['failover_ffmpeg_pid' => (string) $pid]);
            Log::info("Started VOD playback for channel {$channel->id}, PID: {$pid}");
        } catch (\Exception $e) {
            Log::error("Failed to start VOD playback for channel {$channel->id}: {$e->getMessage()}");
        }
    }

    private function stopVodProcess(Channel $channel): void
    {
        if ($channel->failover_ffmpeg_pid) {
            try {
                if (PHP_OS_FAMILY === 'Windows') {
                    Process::run("taskkill /F /PID {$channel->failover_ffmpeg_pid}");
                } else {
                    Process::run("kill -9 {$channel->failover_ffmpeg_pid}");
                }
                Log::info("Stopped VOD FFmpeg process PID: {$channel->failover_ffmpeg_pid}");
            } catch (\Exception $e) {
                Log::warning("Failed to stop VOD process: {$e->getMessage()}");
            }
        }
    }
}
