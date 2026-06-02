<?php

namespace App\Console\Commands;

use App\Models\Channel;
use App\Services\StreamHealthMonitor;
use Illuminate\Console\Command;

class MonitorStreamHealth extends Command
{
    protected $signature = 'stream:monitor {--channel= : Monitor a specific channel ID} {--interval= : Health check interval in seconds}';
    protected $description = 'Monitor stream health and trigger failover/restore';

    public function handle(StreamHealthMonitor $monitor): int
    {
        $interval = $this->option('interval') ?? config('app.stream_health_check_interval', 3);

        if ($channelId = $this->option('channel')) {
            return $this->monitorSingle($monitor, $channelId);
        }

        $this->info("Starting stream health monitor (interval: {$interval}s)...");

        while (true) {
            $channels = Channel::with(['overlaySetting', 'vodPlaylistItems'])
                ->whereNotNull('ingest_port')
                ->get();

            foreach ($channels as $channel) {
                try {
                    $result = $monitor->checkChannel($channel);
                    $status = $result['status'] ?? 'unknown';
                    $action = $result['action'] ?? 'no_change';

                    if ($action !== 'no_change') {
                        $this->line("<info>[{$channel->name}]</info> Status: {$status}, Action: {$action}");
                    }
                } catch (\Exception $e) {
                    $this->error("[{$channel->name}] Error: {$e->getMessage()}");
                }
            }

            sleep($interval);
        }

        return 0;
    }

    private function monitorSingle(StreamHealthMonitor $monitor, int $channelId): int
    {
        $channel = Channel::with(['overlaySetting', 'vodPlaylistItems'])->find($channelId);

        if (!$channel) {
            $this->error("Channel {$channelId} not found.");
            return 1;
        }

        $this->info("Checking channel: {$channel->name}");

        $result = $monitor->checkChannel($channel);

        $this->table(['Key', 'Value'], [
            ['Status', $result['status'] ?? 'unknown'],
            ['Action', $result['action'] ?? 'no_change'],
            ['Is Live', $channel->is_live_streaming ? 'Yes' : 'No'],
            ['Failover Active', $channel->failover_active ? 'Yes' : 'No'],
        ]);

        return 0;
    }
}
