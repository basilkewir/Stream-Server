<?php

namespace App\Services;

use App\Models\Channel;
use App\Models\StreamHealthLog;
use App\Events\StreamStatusChanged;
use Illuminate\Support\Facades\Log;

class StreamHealthMonitor
{
    public function __construct(
        private FFmpegCommandBuilder $ffmpeg,
        private FlussonicService $flussonic,
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

        // Still offline — ensure FFmpeg is running as failover
        if (!$isAlive && !$wasLive) {
            $hasVod = $channel->vodPlaylistItems()->where('status', 'active')->exists();

            if ($hasVod && !$this->isFfmpegRunning($channel)) {
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

        $hasVod = $channel->vodPlaylistItems()->where('status', 'active')->exists();
        if ($hasVod) {
            $this->startVodPlayback($channel);
        }

        event(new StreamStatusChanged($channel, false, true, 'vod_failover'));
        Log::info("Channel {$channel->id} switched to VOD failover");
    }

    private function startVodPlayback(Channel $channel): void
    {
        // Reload with relations needed for FFmpeg command
        $channel->load(['overlaySetting', 'vodPlaylistItems']);

        $cmd = $this->ffmpeg->buildVodWithOverlayCommand($channel);

        if (empty($cmd)) {
            Log::error("Empty FFmpeg command for channel {$channel->id}");
            return;
        }

        // Write command to a temp script so nohup can run it cleanly
        $scriptPath = "/tmp/ffmpeg_channel_{$channel->id}.sh";
        $logPath    = "/var/log/ffmpeg_channel_{$channel->id}.log";

        file_put_contents($scriptPath, "#!/bin/bash\n{$cmd}\n");
        chmod($scriptPath, 0755);

        // Launch detached from PHP process entirely
        $fullCmd = "nohup bash {$scriptPath} > {$logPath} 2>&1 & echo \$!";
        $pid     = trim((string) shell_exec($fullCmd));

        if ($pid && is_numeric($pid)) {
            $channel->update(['failover_ffmpeg_pid' => $pid]);
            Log::info("Started VOD FFmpeg for channel {$channel->id}, PID: {$pid}");
        } else {
            Log::error("Failed to start VOD FFmpeg for channel {$channel->id}");
        }
    }

    private function stopVodProcess(Channel $channel): void
    {
        $pid = $channel->failover_ffmpeg_pid;
        if (!$pid) return;

        // Kill the FFmpeg process and any children
        shell_exec("kill -9 {$pid} 2>/dev/null");
        shell_exec("pkill -P {$pid} 2>/dev/null");

        // Clean up script file
        @unlink("/tmp/ffmpeg_channel_{$channel->id}.sh");

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
