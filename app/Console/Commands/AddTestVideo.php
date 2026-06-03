<?php

namespace App\Console\Commands;

use App\Models\Channel;
use App\Services\VodPlaylistService;
use Illuminate\Console\Command;

class AddTestVideo extends Command
{
    protected $signature = 'test:add-video {channel} {url} {--title=}';
    protected $description = 'Add a test YouTube video to channel VOD playlist';

    public function handle(VodPlaylistService $vodService): int
    {
        $channelId = $this->argument('channel');
        $url = $this->argument('url');
        $title = $this->option('title');
        
        $channel = Channel::find($channelId);
        if (!$channel) {
            $this->error("Channel {$channelId} not found.");
            return 1;
        }

        try {
            $item = $vodService->addYouTubeUrl($channel, $url, $title);
            $this->info("Added YouTube video: {$item->title}");
            $this->info("Duration: " . ($item->duration_sec ? round($item->duration_sec) . " seconds" : "Unknown"));
            $this->info("Status: {$item->status}");
            
            return 0;
        } catch (\Exception $e) {
            $this->error("Failed to add video: " . $e->getMessage());
            return 1;
        }
    }
}