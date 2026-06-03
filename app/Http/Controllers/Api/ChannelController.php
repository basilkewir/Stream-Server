<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Channel;
use App\Services\OverlayService;
use App\Services\StreamHealthMonitor;
use App\Services\VodPlaylistService;
use App\Services\YouTubeService;
use Illuminate\Http\Request;

class ChannelController extends Controller
{
    public function status(Channel $channel, StreamHealthMonitor $monitor)
    {
        $this->authorize('view', $channel);

        $result = $monitor->checkChannel($channel);

        return response()->json([
            'is_live' => $channel->is_live_streaming,
            'failover_active' => $channel->failover_active,
            'last_live_timestamp' => $channel->last_live_timestamp,
            'health_result' => $result,
        ]);
    }

    public function ingestInfo(Channel $channel)
    {
        $this->authorize('view', $channel);

        return response()->json([
            'protocol' => $channel->ingest_protocol,
            'ingest_url' => $channel->formatted_ingest_url,
            'stream_key' => $channel->stream_key,
            'port' => $channel->ingest_port,
        ]);
    }

    public function uploadVod(Request $request, Channel $channel)
    {
        $this->authorize('update', $channel);

        $request->validate([
            'file' => 'required|file|mimetypes:video/mp4,video/x-matroska,video/mp2t|max:512000',
            'title' => 'nullable|string|max:255',
        ]);

        try {
            $vodService = app(VodPlaylistService::class);
            $item = $vodService->uploadFile($channel, $request->file('file'), $request->input('title'));

            return response()->json([
                'item' => $item,
                'storage_used_mb' => $channel->user->storage_used_mb,
            ], 201);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function addYoutube(Request $request, Channel $channel)
    {
        $this->authorize('update', $channel);

        $request->validate([
            'url' => 'required|url',
            'title' => 'nullable|string',
        ]);

        $youtubeService = app(YouTubeService::class);

        if (!$youtubeService->validateUrl($request->input('url'))) {
            return response()->json(['error' => 'Invalid YouTube URL.'], 422);
        }

        $vodService = app(VodPlaylistService::class);
        $item = $vodService->addYouTubeUrl($channel, $request->input('url'), $request->input('title'));

        return response()->json(['item' => $item], 201);
    }

    public function deleteVod(Channel $channel, $vodItemId)
    {
        $this->authorize('update', $channel);

        $item = $channel->vodPlaylistItems()->findOrFail($vodItemId);
        app(VodPlaylistService::class)->deleteItem($item);

        return response()->json(['message' => 'Deleted', 'storage_used_mb' => $channel->user->storage_used_mb]);
    }

    public function reorderVod(Request $request, Channel $channel)
    {
        $this->authorize('update', $channel);

        $request->validate([
            'ordered_ids' => 'required|array',
        ]);

        app(VodPlaylistService::class)->reorder($channel, $request->input('ordered_ids'));

        return response()->json(['status' => 'ok']);
    }

    public function updateOverlay(Request $request, Channel $channel)
    {
        $this->authorize('update', $channel);

        $validated = $request->validate([
            'logo_position' => 'nullable|string',
            'logo_width' => 'nullable|integer|min:10|max:500',
            'ticker_text' => 'nullable|string|max:500',
            'ticker_speed' => 'nullable|integer|min:10|max:200',
            'ticker_direction' => 'nullable|in:left,right',
            'ticker_background_color' => 'nullable|string',
            'ticker_font_color' => 'nullable|string',
            'ticker_font_size' => 'nullable|integer|min:12|max:72',
            'show_clock' => 'nullable|boolean',
            'clock_position' => 'nullable|string',
            'enabled' => 'nullable|boolean',
        ]);

        app(OverlayService::class)->updateSettings($channel, $validated);

        return response()->json(['status' => 'ok', 'settings' => app(OverlayService::class)->getSettings($channel)]);
    }

    public function uploadLogo(Request $request, Channel $channel)
    {
        $this->authorize('update', $channel);

        $request->validate([
            'logo' => 'required|image|mimes:png|max:2048',
        ]);

        $url = app(OverlayService::class)->uploadLogo($channel, $request->file('logo'));

        return response()->json(['logo_url' => $url]);
    }

    public function refreshYoutubeMetadata(Channel $channel, $vodItemId)
    {
        $this->authorize('update', $channel);

        $item = $channel->vodPlaylistItems()->where('type', 'youtube')->findOrFail($vodItemId);
        $youtubeService = app(YouTubeService::class);
        
        $success = $youtubeService->refreshMetadata($item);
        
        if ($success) {
            return response()->json(['item' => $item->fresh(), 'message' => 'Metadata refreshed']);
        } else {
            return response()->json(['error' => 'Failed to refresh metadata. Ensure yt-dlp is installed.'], 422);
        }
    }
    {
        $this->authorize('view', $channel);

        $overlayService = app(OverlayService::class);

        return response()->json([
            'settings' => $overlayService->getSettings($channel),
            'preview' => $overlayService->buildOverlayPreviewFilters($channel),
        ]);
    }

    public function systemStatus()
    {
        $youtubeService = app(YouTubeService::class);
        $status = $youtubeService->getSystemStatus();
        
        return response()->json([
            'system_status' => $status,
            'recommendations' => $this->getRecommendations($status),
        ]);
    }

    private function getRecommendations(array $status): array
    {
        $recommendations = [];
        
        if (!$status['yt_dlp']['available'] && !$status['youtube_dl']['available']) {
            $recommendations[] = 'Install yt-dlp for YouTube metadata extraction: sudo pip3 install yt-dlp';
        }
        
        if (!$status['flussonic']['running']) {
            $recommendations[] = 'Flussonic service is not running. Check: sudo systemctl status flussonic';
        }
        
        if (!$status['flussonic']['accessible']) {
            $recommendations[] = 'Flussonic is not accessible on port 8090. Check firewall and service configuration.';
        }
        
        if (!$status['permissions']['web_user_can_execute']) {
            $recommendations[] = 'Web server user cannot execute YouTube tools. Check PATH and permissions.';
        }
        
        if (empty($recommendations)) {
            $recommendations[] = 'System is properly configured for YouTube integration!';
        }
        
        return $recommendations;
    }
}
