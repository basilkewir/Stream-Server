<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionPlan;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SubscriptionController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/Subscriptions', [
            'plans' => SubscriptionPlan::withCount('users')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'storage_mb_limit' => 'required|integer|min:1',
            'max_channels' => 'required|integer|min:1',
            'features_json' => 'nullable|json',
        ]);

        SubscriptionPlan::create($validated);

        return back()->with('success', 'Subscription plan created.');
    }

    public function update(Request $request, SubscriptionPlan $plan)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'storage_mb_limit' => 'required|integer|min:1',
            'max_channels' => 'required|integer|min:1',
            'features_json' => 'nullable|json',
        ]);

        $plan->update($validated);

        return back()->with('success', 'Subscription plan updated.');
    }

    public function destroy(SubscriptionPlan $plan)
    {
        $plan->delete();

        return back()->with('success', 'Subscription plan deleted.');
    }
}
