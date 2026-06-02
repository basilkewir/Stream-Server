<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;

class UserController extends Controller
{
    public function index()
    {
        $users = User::where('role', 'channel_user')
            ->with('subscriptionPlan')
            ->withCount('channels')
            ->latest()
            ->paginate(20);

        return Inertia::render('Admin/Users', [
            'users' => $users,
        ]);
    }

    public function create()
    {
        return Inertia::render('Admin/UserCreate', [
            'plans' => SubscriptionPlan::all(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
            'password' => 'required|string|min:8',
            'role' => 'required|in:admin,channel_user',
            'subscription_plan_id' => 'nullable|exists:subscription_plans,id',
        ]);

        $validated['password'] = Hash::make($validated['password']);

        User::create($validated);

        return redirect()->route('admin.users')->with('success', 'User created.');
    }

    public function show(User $user)
    {
        $user->load('subscriptionPlan', 'channels.vodPlaylistItems', 'channels.overlaySetting');

        return Inertia::render('Admin/UserDetail', [
            'user' => $user,
            'plans' => SubscriptionPlan::all(),
        ]);
    }

    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'email' => 'nullable|email|unique:users,email,' . $user->id,
            'password' => 'nullable|string|min:8',
            'subscription_plan_id' => 'nullable|exists:subscription_plans,id',
            'subscription_expires_at' => 'nullable|date',
            'role' => 'required|in:admin,channel_user',
        ]);

        if (!empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        $user->update($validated);

        return back()->with('success', 'User updated.');
    }

    public function destroy(User $user)
    {
        $user->delete();

        return redirect()->route('admin.users')->with('success', 'User deleted.');
    }

    public function extendSubscription(Request $request, User $user)
    {
        $request->validate([
            'days' => 'required|integer|min:1|max:3650',
        ]);

        $current = $user->subscription_expires_at ?? now();
        $user->update([
            'subscription_expires_at' => $current->addDays($request->input('days')),
        ]);

        return back()->with('success', "Subscription extended by {$request->input('days')} days.");
    }
}
