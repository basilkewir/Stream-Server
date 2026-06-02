<?php

namespace App\Policies;

use App\Models\Channel;
use App\Models\User;

class ChannelPolicy
{
    public function view(User $user, Channel $channel): bool
    {
        return $user->id === $channel->user_id || $user->role === 'admin';
    }

    public function update(User $user, Channel $channel): bool
    {
        return $user->id === $channel->user_id || $user->role === 'admin';
    }

    public function delete(User $user, Channel $channel): bool
    {
        return $user->id === $channel->user_id || $user->role === 'admin';
    }
}
