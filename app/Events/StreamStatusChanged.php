<?php

namespace App\Events;

use App\Models\Channel;
use Illuminate\Broadcasting\Channel as BroadcastChannel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StreamStatusChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Channel $channel,
        public bool $isLive,
        public bool $failoverActive,
        public string $action,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new BroadcastChannel('stream.' . $this->channel->id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'stream.status';
    }
}
