<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;
use Tighten\Ziggy\Ziggy;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        $user = $request->user();

        $shared = [
            ...parent::share($request),
            'ziggy' => fn () => [
                ...(new Ziggy)->toArray(),
                'location' => $request->url(),
            ],
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'is_admin' => $user->isAdmin(),
                    'storage_used_mb' => $user->storage_used_mb,
                    'storage_limit_mb' => $user->storage_limit_mb,
                    'storage_used_percent' => $user->storage_used_percent,
                    'max_channels' => $user->max_channels,
                    'channel_count' => $user->channels()->count(),
                    'has_active_subscription' => $user->hasActiveSubscription(),
                ] : null,
            ],
        ];

        if ($user && !$user->isAdmin()) {
            $shared['channels'] = $user->channels()->select('id', 'name', 'is_live_streaming', 'failover_active', 'ingest_protocol')->get();
        }

        return $shared;
    }
}
