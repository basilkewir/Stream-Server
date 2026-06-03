<?php

namespace App\Console\Commands;

use App\Models\Channel;
use App\Services\StreamHealthMonitor;
use App\Services\FlussonicService;
use Illuminate\Console\Command;

class TestStreamFailover extends Command
{
    protected $signature = 'stream:test-failover {channel_id} {--simulate-offline} {--simulate-online} {--status}';
    protected $description = 'Test the hybrid streaming failover system';

    public function handle(StreamHealthMonitor $monitor, FlussonicService $flussonic)
    {
        $channel = Channel::findOrFail($this->argument('channel_id'));
        
        $this->info("🧪 Testing Failover System for Channel: {$channel->name}");
        $this->info("Stream Key: {$channel->stream_key}");
        
        if ($this->option('status')) {
            $this->showCurrentStatus($channel, $flussonic);
            return;
        }
        
        if ($this->option('simulate-offline')) {
            $this->simulateStreamOffline($channel, $monitor, $flussonic);
        }
        
        if ($this->option('simulate-online')) {
            $this->simulateStreamOnline($channel, $monitor, $flussonic);
        }
        
        if (!$this->option('simulate-offline') && !$this->option('simulate-online')) {
            $this->runFullFailoverTest($channel, $monitor, $flussonic);
        }
    }
    
    private function showCurrentStatus(Channel $channel, FlussonicService $flussonic)
    {
        $this->line('📊 Current Channel Status:');
        $this->line("  Live Streaming: " . ($channel->is_live_streaming ? '✅ Yes' : '❌ No'));
        $this->line("  Failover Active: " . ($channel->failover_active ? '✅ Yes' : '❌ No'));
        $this->line("  Last Live: " . ($channel->last_live_timestamp ? $channel->last_live_timestamp->diffForHumans() : 'Never'));
        
        $stats = $flussonic->getStreamStats($channel->stream_key);
        if ($stats) {
            $this->line("  Flussonic Status: " . ($stats['alive'] ? '🔴 Live' : '⚫ Offline'));
            $this->line("  Current Viewers: {$stats['clients']}");
            if ($stats['bitrate'] > 0) {
                $mbps = round($stats['bitrate'] / 1000000, 2);
                $this->line("  Bitrate: {$mbps} Mbps");
            }
        } else {
            $this->line("  Flussonic Status: ❓ Unable to connect");
        }
        
        $vodCount = $channel->vodPlaylistItems()->where('status', 'active')->count();
        $this->line("  VOD Playlist: {$vodCount} active items");
        
        if ($channel->failover_ffmpeg_pid) {
            $this->line("  FFmpeg PID: {$channel->failover_ffmpeg_pid}");
        }
    }
    
    private function simulateStreamOffline(Channel $channel, StreamHealthMonitor $monitor, FlussonicService $flussonic)
    {
        $this->warn("⚡ Simulating Stream Offline...");
        
        // Force channel to live state first
        $channel->update(['is_live_streaming' => true, 'last_live_timestamp' => now()]);
        
        $this->line("1. Channel marked as live");
        
        // Run health check - should detect offline and switch to VOD
        $result = $monitor->checkChannel($channel);
        
        $this->line("2. Health check result: {$result['action']}");
        
        $channel->refresh();
        
        if ($channel->failover_active) {
            $this->info("✅ SUCCESS: Failover activated - viewers now see VOD content");
        } else {\n            $this->error(\"❌ FAILED: Failover not activated\");\n        }\n        \n        // Show final status\n        $this->showCurrentStatus($channel, $flussonic);\n    }\n    \n    private function simulateStreamOnline(Channel $channel, StreamHealthMonitor $monitor, FlussonicService $flussonic)\n    {\n        $this->info(\"✅ Simulating Stream Online...\");\n        \n        // This would require actual live input to Flussonic\n        // For now, we'll just show what would happen\n        $this->comment(\"To test stream online:\");\n        $this->comment(\"1. Start streaming to: {$flussonic->getInputUrl($channel)}\");\n        $this->comment(\"2. Run: php artisan stream:monitor {$channel->id} --watch\");\n        $this->comment(\"3. System should automatically detect live stream and switch from VOD\");\n    }\n    \n    private function runFullFailoverTest(Channel $channel, StreamHealthMonitor $monitor, FlussonicService $flussonic)\n    {\n        $this->info(\"🔄 Running Complete Failover Test\");\n        \n        // Step 1: Check initial status\n        $this->line(\"\\n📋 Step 1: Initial Status\");\n        $this->showCurrentStatus($channel, $flussonic);\n        \n        // Step 2: Test VOD system\n        $this->line(\"\\n📹 Step 2: Testing VOD System\");\n        $vodCount = $channel->vodPlaylistItems()->where('status', 'active')->count();\n        \n        if ($vodCount > 0) {\n            $this->info(\"✅ VOD playlist has {$vodCount} active items\");\n            \n            // Test VOD playback\n            $this->line(\"Testing VOD playback...\");\n            $channel->update(['is_live_streaming' => false]);\n            $result = $monitor->checkChannel($channel);\n            $this->line(\"VOD test result: {$result['action']}\");\n        } else {\n            $this->warn(\"⚠️ No VOD items configured - failover will show black screen\");\n        }\n        \n        // Step 3: Test monitoring\n        $this->line(\"\\n👁️ Step 3: Testing Monitoring System\");\n        for ($i = 1; $i <= 3; $i++) {\n            $result = $monitor->checkChannel($channel);\n            $this->line(\"Check #{$i}: {$result['status']} - {$result['action']}\");\n            sleep(2);\n        }\n        \n        // Step 4: Recommendations\n        $this->line(\"\\n💡 Step 4: Recommendations\");\n        \n        if ($vodCount == 0) {\n            $this->comment(\"→ Add VOD content: Upload videos or add streaming URLs\");\n        }\n        \n        if (!$flussonic->isAvailable()) {\n            $this->comment(\"→ Check Flussonic connection and credentials\");\n        }\n        \n        $this->comment(\"→ Test live streaming by pushing to: {$flussonic->getInputUrl($channel)}\");\n        $this->comment(\"→ Monitor in real-time: php artisan stream:monitor {$channel->id} --watch\");\n        \n        $this->info(\"\\n🎉 Failover test complete!\");\n    }\n}\n