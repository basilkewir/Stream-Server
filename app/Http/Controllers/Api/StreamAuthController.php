<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Channel;
use App\Models\StreamHealthLog;
use App\Services\FlussonicService;
use Illuminate\Http\Request;

class StreamAuthController extends Controller
{
    public function authorizeStream(Request $request)
    {
        // Called by Flussonic's auth backend or on_publish hook if configured
        $streamKey = $request->input('name')
            ?? $request->input('stream')
            ?? $request->input('stream_key');

        if (!$streamKey) {
            return response()->json(['code' => 1, 'reason' => 'Missing stream key'], 403);
        }

        $channel = Channel::where('stream_key', $streamKey)->first();

        if (!$channel) {
            return response()->json([
                'code' => 1,
                'reason' => 'Invalid stream key - no channel found',
            ], 403);
        }

        $channel->update([
            'is_live_streaming' => true,
            'last_live_timestamp' => now(),
            'failover_active' => false,
        ]);

        StreamHealthLog::create([
            'channel_id' => $channel->id,
            'is_live' => true,
            'event_type' => 'stream_started',
            'message' => "Live stream started on channel {$channel->name} ({$channel->ingest_protocol})",
        ]);

        return response()->json(['code' => 0, 'channel_id' => $channel->id]);
    }

    public function unauthorizeStream(Request $request)
    {
        $streamKey = $request->input('name')
            ?? $request->input('stream')
            ?? $request->input('stream_key');

        if (!$streamKey) {
            return response()->json(['code' => 1], 403);
        }

        $channel = Channel::where('stream_key', $streamKey)->first();

        if ($channel) {
            $channel->update(['is_live_streaming' => false]);

            StreamHealthLog::create([
                'channel_id' => $channel->id,
                'is_live' => false,
                'event_type' => 'stream_stopped',
                'message' => "Live stream stopped on channel {$channel->name}",
            ]);
        }

        return response()->json(['code' => 0]);
    }

    public function flussonicStatus(FlussonicService $flussonic)
    {
        $available = $flussonic->isAvailable();
        $serverStats = $available ? $flussonic->getServerStats() : null;
        $streams = $available ? $flussonic->getStreams() : [];

        return response()->json([
            'available' => $available,
            'server' => $serverStats,
            'streams' => $streams,
        ]);
    }
}
