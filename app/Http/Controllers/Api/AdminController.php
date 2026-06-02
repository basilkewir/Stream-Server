<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function subscriptions()
    {
        $plans = SubscriptionPlan::withCount('users')->get();

        return response()->json(['plans' => $plans]);
    }

    public function createSubscription(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'storage_mb_limit' => 'required|integer|min:1',
            'features_json' => 'nullable|json',
        ]);

        $plan = SubscriptionPlan::create($validated);

        return response()->json(['plan' => $plan], 201);
    }

    public function updateSubscription(Request $request, SubscriptionPlan $plan)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'storage_mb_limit' => 'required|integer|min:1',
            'features_json' => 'nullable|json',
        ]);

        $plan->update($validated);

        return response()->json(['plan' => $plan]);
    }

    public function deleteSubscription(SubscriptionPlan $plan)
    {
        $plan->delete();

        return response()->json(['message' => 'Deleted']);
    }

    public function users()
    {
        $users = User::where('role', 'channel_user')
            ->with('subscriptionPlan')
            ->withCount('channels')
            ->paginate(20);

        return response()->json(['users' => $users]);
    }

    public function updateUser(Request $request, User $user)
    {
        $validated = $request->validate([
            'subscription_plan_id' => 'nullable|exists:subscription_plans,id',
            'role' => 'required|in:admin,channel_user',
        ]);

        $user->update($validated);

        return response()->json(['user' => $user->fresh('subscriptionPlan')]);
    }

    public function stats()
    {
        $stats = [
            'total_users' => User::where('role', 'channel_user')->count(),
            'total_channels' => \App\Models\Channel::count(),
            'live_channels' => \App\Models\Channel::where('is_live_streaming', true)->count(),
            'failover_channels' => \App\Models\Channel::where('failover_active', true)->count(),
            'total_storage_used' => User::sum('storage_used_mb'),
        ];

        return response()->json(['stats' => $stats]);
    }
}
