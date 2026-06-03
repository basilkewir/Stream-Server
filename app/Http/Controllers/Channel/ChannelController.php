<?php

namespace App\Http\Controllers\Channel;

use App\Http\Controllers\Controller;
use App\Models\Channel;
use App\Services\FlussonicService;
use App\Services\OverlayService;
use App\Services\VodPlaylistService;
use App\Services\YouTubeService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redirect;

class ChannelController extends Controller
{
    public function show(Channel $channel)
    {
        $this->authorize('view', $channel);

        $channel->load('vodPlaylistItems', 'overlaySetting');
        $overlayService = app(OverlayService::class);
        $vodService = app(VodPlaylistService::class);

        return Inertia::render('Channel/Show', [
            'channel' => $channel,
            'overlay_settings' => $overlayService->getSettings($channel),
            'overlay_preview' => $overlayService->buildOverlayPreviewFilters($channel),
            'vod_items' => $channel->vodPlaylistItems,
            'playlist_stats' => $vodService->getPlaylistStats($channel),
            'playlist_timeline' => $vodService->getScheduledPlaylist($channel),
            'health_logs' => $channel->healthLogs()->latest()->take(20)->get(),
        ]);
    }

    public function create()
    {
        $user = auth()->user();

        return Inertia::render('Channel/Create', [
            'can_create' => $user->canCreateChannel(),
            'channel_count' => $user->channels()->count(),
            'max_channels' => $user->max_channels,
            'has_active_subscription' => $user->hasActiveSubscription(),
        ]);
    }

    public function store(Request $request)
    {
        $user = auth()->user();

        if (!$user->canCreateChannel()) {
            return back()->withErrors([
                'name' => 'Channel limit reached (' . $user->max_channels . ' max) or subscription expired. Upgrade your plan.',
            ]);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'ingest_protocol' => 'required|in:srt,rtmp,rtsp,mpegts',
        ]);

        $port = $this->assignPort($validated['ingest_protocol']);

        $channel = $user->channels()->create([
            'name' => $validated['name'],
            'ingest_protocol' => $validated['ingest_protocol'],
            'ingest_port' => $port,
            'output_protocols_json' => ['rtmp'],
        ]);

        $this->provisionFlussonic($channel);

        return Redirect::route('channel.show', $channel);
    }

    public function update(Request $request, Channel $channel)
    {
        $this->authorize('update', $channel);

        $validated = $request->validate([
            'name' => 'string|max:255',
            'ingest_protocol' => 'in:srt,rtmp,rtsp,mpegts',
            'output_protocols_json' => 'nullable|array',
            'ingest_port' => 'nullable|integer',
        ]);

        if (isset($validated['ingest_protocol']) && $validated['ingest_protocol'] !== $channel->ingest_protocol) {
            $validated['ingest_port'] = $this->assignPort($validated['ingest_protocol']);
        }

        $channel->update($validated);

        return back()->with('success', 'Channel updated.');
    }

    public function destroy(Channel $channel)
    {
        $this->authorize('delete', $channel);

        try {
            app(FlussonicService::class)->stopStream($channel->stream_key);
        } catch (\Exception $e) {
            Log::warning('Could not remove stream from Flussonic: ' . $e->getMessage());
        }

        $channel->delete();

        return Redirect::route('dashboard')->with('success', 'Channel deleted.');
    }

    private function provisionFlussonic(Channel $channel): void
    {
        try {
            $flussonic = app(FlussonicService::class);
            $host = config('flussonic.host', '127.0.0.1');
            $apiPort = config('flussonic.api_port', 8080);
            $login = config('flussonic.login', 'admin');
            $password = config('flussonic.password', 'admin');

            $ingestUrl = match ($channel->ingest_protocol) {
                'rtmp'   => "rtmp://:{$channel->ingest_port}/{$channel->stream_key}",
                'srt'    => "srt://:{$channel->ingest_port}?streamid={$channel->stream_key}",
                'rtsp'   => "rtsp://:{$channel->ingest_port}/{$channel->stream_key}",
                'mpegts' => "udp://:{$channel->ingest_port}",
                default  => "rtmp://:{$channel->ingest_port}/{$channel->stream_key}",
            };

            \Illuminate\Support\Facades\Http::withBasicAuth($login, $password)
                ->asJson()
                ->put("http://{$host}:{$apiPort}/api/v3/streams/{$channel->stream_key}", [
                    'name' => $channel->stream_key,
                    'src'  => $ingestUrl,
                ]);
        } catch (\Exception $e) {
            Log::warning('Could not provision stream in Flussonic: ' . $e->getMessage());
        }
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

            return back()->with('success', 'Video uploaded.');
        } catch (\RuntimeException $e) {
            return back()->withErrors(['file' => $e->getMessage()]);
        }
    }

    public function addYoutube(Request $request, Channel $channel)
    {
        $this->authorize('update', $channel);

        $request->validate([
            'url' => 'required|url',
        ]);

        $youtubeService = app(YouTubeService::class);

        if (!$youtubeService->validateUrl($request->input('url'))) {
            return back()->withErrors(['url' => 'Invalid YouTube URL.']);
        }

        $vodService = app(VodPlaylistService::class);
        $vodService->addYouTubeUrl($channel, $request->input('url'), $request->input('title'));

        return back()->with('success', 'YouTube video added to playlist.');
    }

    public function deleteVod(Channel $channel, $vodItemId)
    {
        $this->authorize('update', $channel);

        $item = $channel->vodPlaylistItems()->findOrFail($vodItemId);
        app(VodPlaylistService::class)->deleteItem($item);

        return back()->with('success', 'Playlist item removed.');
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

    public function updateVodItem(Request $request, Channel $channel, $vodItemId)
    {
        $this->authorize('update', $channel);

        $vodService = app(VodPlaylistService::class);
        $item = $vodService->updateItem($channel, $vodItemId, $request->all());

        return response()->json(['item' => $item]);
    }

    public function bulkUpdateVod(Request $request, Channel $channel)
    {
        $this->authorize('update', $channel);

        $request->validate([
            'items' => 'required|array',
        ]);

        app(VodPlaylistService::class)->bulkUpdate($channel, $request->input('items'));

        return response()->json(['status' => 'ok']);
    }

    public function updatePlaylistSettings(Request $request, Channel $channel)
    {
        $this->authorize('update', $channel);

        $validated = $request->validate([
            'playlist_mode' => 'required|in:sequential,shuffle,scheduled',
            'playlist_loop' => 'required|boolean',
            'playlist_fill_action' => 'required|in:black,logo,last_frame',
        ]);

        app(VodPlaylistService::class)->updateChannelPlaylistSettings($channel, $validated);

        return response()->json(['status' => 'ok', 'settings' => $channel->fresh()->only(['playlist_mode', 'playlist_loop', 'playlist_fill_action'])]);
    }

    public function playlistStats(Channel $channel)
    {
        $this->authorize('view', $channel);

        $stats = app(VodPlaylistService::class)->getPlaylistStats($channel);

        return response()->json($stats);
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

        return back()->with('success', 'Overlay settings updated.');
    }

    public function uploadLogo(Request $request, Channel $channel)
    {
        $this->authorize('update', $channel);

        $request->validate([
            'logo' => 'required|image|mimes:png|max:2048',
        ]);

        $url = app(OverlayService::class)->uploadLogo($channel, $request->file('logo'));

        return back()->with('success', 'Logo uploaded.');
    }

    private function assignPort(string $protocol): int
    {
        $basePorts = [
            'srt' => 10000,
            'rtmp' => 1935,
            'rtsp' => 8554,
            'mpegts' => 5000,
        ];

        $basePort = $basePorts[$protocol] ?? 10000;
        $usedPorts = Channel::where('ingest_protocol', $protocol)
            ->pluck('ingest_port')
            ->toArray();

        $port = $basePort;
        while (in_array($port, $usedPorts)) {
            $port += 2;
        }

        return $port;
    }
}
