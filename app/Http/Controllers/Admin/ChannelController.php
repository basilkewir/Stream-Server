<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Channel;
use Inertia\Inertia;

class ChannelController extends Controller
{
    public function index()
    {
        $channels = Channel::with('user')
            ->withCount('vodPlaylistItems')
            ->latest()
            ->paginate(20);

        $stats = [
            'total' => Channel::count(),
            'live' => Channel::where('is_live_streaming', true)->count(),
            'failover' => Channel::where('failover_active', true)->count(),
            'offline' => Channel::where('is_live_streaming', false)->where('failover_active', false)->count(),
        ];

        return Inertia::render('Admin/Channels', [
            'channels' => $channels,
            'stats' => $stats,
        ]);
    }

    public function show(Channel $channel)
    {
        $channel->load('user.subscriptionPlan', 'vodPlaylistItems', 'overlaySetting', 'healthLogs');

        return Inertia::render('Admin/ChannelDetail', [
            'channel' => $channel,
        ]);
    }
}
