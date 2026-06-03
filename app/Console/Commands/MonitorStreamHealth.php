<?php

namespace App\Console\Commands;

use App\Models\Channel;
use App\Services\StreamHealthMonitor;
use Illuminate\Console\Command;

class MonitorStreamHealth extends Command
{
    protected $signature = 'stream:monitor {channel_id?} {--watch} {--interval=10}';
    protected $description = 'Monitor stream health and test failover system';

    public function handle(StreamHealthMonitor $monitor)
    {
        $channelId = $this->argument('channel_id');
        $watch = $this->option('watch');
        $interval = (int) $this->option('interval');

        if ($channelId) {
            $channel = Channel::findOrFail($channelId);
            $this->monitorSingleChannel($monitor, $channel, $watch, $interval);
        } else {
            $this->monitorAllChannels($monitor, $watch, $interval);
        }
    }

    private function monitorSingleChannel(StreamHealthMonitor $monitor, Channel $channel, bool $watch, int $interval)
    {
        $this->info("🔍 Monitoring Channel: {$channel->name} (ID: {$channel->id})");
        $this->info("Stream Key: {$channel->stream_key}");
        $this->info("Current Status: " . ($channel->is_live_streaming ? '🔴 LIVE' : '📹 VOD'));
        
        do {
            $result = $monitor->checkChannel($channel);
            $channel->refresh();
            
            $timestamp = now()->format('H:i:s');
            
            $statusIcon = match($result['status']) {
                'live' => '🔴',
                'vod' => '📹',
                default => '❓'
            };
            
            $actionIcon = match($result['action']) {
                'switched_to_live' => '✅ RESTORED',
                'switched_to_vod' => '⚡ FAILOVER',
                'monitoring_live' => '👁️ MONITORING',
                'monitoring_vod' => '👀 VOD ACTIVE',
                'grace_period' => '⏳ GRACE PERIOD',
                'restarted_vod' => '🔄 VOD RESTART',
                default => $result['action']
            };
            
            $line = "[{$timestamp}] {$statusIcon} {$result['status']} | {$actionIcon}";
            
            if (isset($result['viewers'])) {
                $line .= " | 👥 {$result['viewers']} viewers";
            }
            
            if (isset($result['bitrate']) && $result['bitrate'] > 0) {
                $bitrateMbps = round($result['bitrate'] / 1000000, 2);
                $line .= " | 📊 {$bitrateMbps} Mbps";
            }
            
            if (isset($result['offline_duration'])) {
                $line .= " | ⏰ Offline: {$result['offline_duration']}s";
            }
            
            if (isset($result['grace_remaining'])) {
                $line .= " | Remaining: {$result['grace_remaining']}s";
            }
            
            $this->line($line);
            
            if ($watch && $result['action'] !== 'no_change') {
                // Play system sound for important events
                if (in_array($result['action'], ['switched_to_live', 'switched_to_vod'])) {
                    $this->comment("🔔 Status change detected!");
                }
            }
            
            if ($watch) {
                sleep($interval);
            }
            
        } while ($watch);
    }

    private function monitorAllChannels(StreamHealthMonitor $monitor, bool $watch, int $interval)
    {
        do {
            $results = $monitor->checkAllChannels();
            
            $this->info('📺 HybridStream Failover Monitor - ' . now()->format('Y-m-d H:i:s'));
            $this->line(str_repeat('=', 80));
            
            if (empty($results)) {
                $this->warn('No channels configured for monitoring');
                return;
            }
            
            $liveCount = 0;
            $vodCount = 0;
            $errorCount = 0;
            
            foreach ($results as $channelId => $result) {
                $channel = Channel::find($channelId);
                if (!$channel) continue;
                
                $statusIcon = match($result['status']) {
                    'live' => '🔴',
                    'vod' => '📹',
                    'error' => '❌',
                    default => '❓'
                };
                
                $actionText = match($result['action']) {
                    'switched_to_live' => '<fg=green>RESTORED TO LIVE</>',
                    'switched_to_vod' => '<fg=yellow>FAILOVER TO VOD</>',
                    'monitoring_live' => '<fg=green>Live Active</>',
                    'monitoring_vod' => '<fg=yellow>VOD Active</>',
                    'grace_period' => '<fg=cyan>Grace Period</>',
                    'error' => '<fg=red>ERROR</>',
                    default => $result['action']
                };
                
                $this->line("  {$statusIcon} {$channel->name} ({$channel->stream_key}) - {$actionText}");
                
                if (isset($result['viewers'])) {
                    $this->line("      👥 Viewers: {$result['viewers']}");
                }
                
                match($result['status']) {
                    'live' => $liveCount++,
                    'vod' => $vodCount++,
                    'error' => $errorCount++,
                    default => null
                };\n            }\n            \n            $this->line(str_repeat('=', 80));\n            $this->info(\"📊 Summary: {$liveCount} Live | {$vodCount} VOD | {$errorCount} Errors\");\n            \n            if ($watch) {\n                $this->line('');\n                sleep($interval);\n                // Clear screen for next update\n                if (PHP_OS_FAMILY !== 'Windows') {\n                    system('clear');\n                }\n            }\n            \n        } while ($watch);\n    }\n}\n