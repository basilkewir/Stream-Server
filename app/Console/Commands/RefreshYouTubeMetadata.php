<?php

namespace App\Console\Commands;

use App\Models\VodPlaylistItem;
use App\Services\YouTubeService;
use Illuminate\Console\Command;

class RefreshYouTubeMetadata extends Command
{
    protected $signature = 'youtube:refresh-metadata 
                            {--all : Refresh all YouTube videos, not just those with missing duration}
                            {--channel= : Only refresh videos for specific channel ID}';

    protected $description = 'Refresh YouTube video metadata for videos with missing duration';

    public function handle(YouTubeService $youtubeService)
    {
        $query = VodPlaylistItem::where('type', 'youtube');
        
        if ($this->option('channel')) {
            $query->where('channel_id', $this->option('channel'));
        }
        
        if (!$this->option('all')) {
            $query->where(function ($q) {
                $q->whereNull('duration_sec')->orWhere('duration_sec', 0);
            });
        }
        
        $items = $query->get();
        
        if ($items->isEmpty()) {
            $this->info('No YouTube videos found that need metadata refresh.');
            return 0;
        }
        
        $this->info("Found {$items->count()} YouTube videos to refresh.");
        $bar = $this->output->createProgressBar($items->count());
        
        $success = 0;
        $failed = 0;
        
        foreach ($items as $item) {
            $bar->advance();
            
            if ($youtubeService->refreshMetadata($item)) {
                $success++;
                $this->newLine();
                $this->line("✓ Refreshed: {$item->title} ({$item->duration_sec}s)");
            } else {
                $failed++;
                $this->newLine();
                $this->error("✗ Failed: {$item->title}");
            }
            
            // Small delay to be respectful to YouTube
            usleep(500000); // 0.5 seconds
        }
        
        $bar->finish();
        $this->newLine(2);
        
        $this->info("Refresh completed!");
        $this->info("Successfully refreshed: {$success}");
        if ($failed > 0) {
            $this->error("Failed to refresh: {$failed}");
            $this->newLine();
            $this->warn('If many failed, ensure yt-dlp is installed and working:');
            $this->line('Run: ./install-youtube-tools.sh (Linux/Mac) or install-youtube-tools.bat (Windows)');
        }
        
        return 0;
    }
}