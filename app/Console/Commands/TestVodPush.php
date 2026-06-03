<?php

namespace App\Console\Commands;

use App\Models\Channel;
use App\Services\StreamHealthMonitor;
use Illuminate\Console\Command;

class TestVodPush extends Command
{
    protected $signature = 'test:vod-push {channel}';
    protected $description = 'Test pushing VOD playlist to Flussonic for a specific channel';

    public function handle(StreamHealthMonitor $monitor): int
    {
        $channelId = $this->argument('channel');
        $channel = Channel::with(['overlaySetting', 'vodPlaylistItems'])->find($channelId);

        if (!$channel) {
            $this->error("Channel {$channelId} not found.");
            return 1;
        }

        $this->info("Testing VOD push for channel: {$channel->name}");
        $this->info("Stream Key: {$channel->stream_key}");
        
        $items = $channel->vodPlaylistItems()->where('status', 'active')->orderBy('order')->get();
        $this->info("Active VOD items: " . $items->count());
        
        foreach ($items as $item) {
            $this->line("- {$item->title} ({$item->type}): {$item->file_path_or_url}");
        }

        // Force switch to VOD mode
        $monitor->switchToVod($channel);
        
        $this->info("VOD failover triggered. Check Flussonic stream status.");
        
        return 0;
    }
}