<?php

namespace App\Http\Controllers\Channel;

use App\Http\Controllers\Controller;
use App\Models\Channel;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $channels = $user->channels()->with('overlaySetting')->get();

        return Inertia::render('Channel/Dashboard', [
            'channels' => $channels,
            'storage_used_mb' => $user->storage_used_mb,
            'storage_limit_mb' => $user->storage_limit_mb,
            'storage_used_percent' => $user->storage_used_percent,
            'subscription' => $user->subscriptionPlan,
        ]);
    }
}
