<?php

namespace App\Console\Commands;

use App\Models\Channel;
use App\Services\VodPlaylistService;
use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class TestVideoUpload extends Command
{
    protected $signature = 'test:video-upload {channel} {--url=} {--title=} {--type=upload}';
    protected $description = 'Test video upload functionality with different methods';

    public function handle(VodPlaylistService $vodService): int
    {
        $channelId = $this->argument('channel');
        $url = $this->option('url');
        $title = $this->option('title') ?? 'Test Video';
        $type = $this->option('type');
        
        $channel = Channel::with(['vodPlaylistItems'])->find($channelId);
        if (!$channel) {
            $this->error("Channel {$channelId} not found.");
            return 1;
        }

        $this->info("Testing video upload for channel: {$channel->name}");
        $this->info("Type: {$type}");

        try {
            if ($type === 'direct-url' && $url) {
                $item = $vodService->addDirectUrl($channel, $url, $title);
                $this->info("✅ Direct URL added successfully!");
                $this->displayItemInfo($item);
            } 
            elseif ($type === 'sample-video') {
                $this->createSampleVideo($channel, $vodService);
            }
            elseif ($url) {
                $item = $vodService->addDirectUrl($channel, $url, $title);
                $this->info("✅ URL added successfully!");
                $this->displayItemInfo($item);
            }
            else {
                $this->info("Available test options:");
                $this->line("--type=direct-url --url=http://example.com/video.mp4");
                $this->line("--type=sample-video (creates a test video file)");
            }

            return 0;
        } catch (\Exception $e) {
            $this->error("Failed: " . $e->getMessage());
            return 1;
        }
    }

    private function createSampleVideo(Channel $channel, VodPlaylistService $vodService): void
    {
        $this->info("Creating sample test video...");
        
        // Create a simple 30-second test video
        $videoPath = storage_path('app/public/test_video.mp4');
        $cmd = 'ffmpeg -f lavfi -i "color=green:size=640x480:rate=25" '
            . '-f lavfi -i "anullsrc=channel_layout=stereo:sample_rate=44100" '
            . '-vf "drawtext=fontfile=/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf:'
            . 'text=\'TEST VIDEO\':fontcolor=white:fontsize=24:x=(w-tw)/2:y=(h-th)/2" '
            . '-t 30 -c:v libx264 -c:a aac -y "' . $videoPath . '"';

        exec($cmd . ' 2>/dev/null', $output, $returnCode);
        
        if ($returnCode === 0 && file_exists($videoPath)) {
            // Create fake UploadedFile from generated video
            $fakeFile = new UploadedFile(
                $videoPath,
                'test_video.mp4',
                'video/mp4',
                null,
                true // test mode
            );
            
            $item = $vodService->uploadFile($channel, $fakeFile, 'Sample Test Video');
            $this->info("✅ Sample video uploaded successfully!");
            $this->displayItemInfo($item);
            
            // Clean up
            @unlink($videoPath);
        } else {
            $this->error("Failed to create sample video");
        }
    }

    private function displayItemInfo($item): void
    {
        $this->table(['Property', 'Value'], [
            ['ID', $item->id],
            ['Title', $item->title],
            ['Type', $item->type],
            ['Duration', $item->duration_sec ? gmdate('H:i:s', $item->duration_sec) : 'Unknown'],
            ['File Size', $this->formatBytes($item->file_size_bytes)],
            ['Status', $item->status],
            ['Created', $item->created_at->format('Y-m-d H:i:s')],
        ]);
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, 2) . ' ' . $units[$pow];
    }
}