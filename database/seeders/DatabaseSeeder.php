<?php

namespace Database\Seeders;

use App\Models\Channel;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Starter',
                'price' => 0,
                'storage_mb_limit' => 500,
                'max_channels' => 3,
                'features_json' => json_encode(['srt', 'rtmp', 'rtsp', 'vod_failover', 'overlay']),
            ],
            [
                'name' => 'Pro',
                'price' => 29.99,
                'storage_mb_limit' => 2000,
                'max_channels' => 10,
                'features_json' => json_encode(['srt', 'rtmp', 'rtsp', 'hls', 'vod_failover', 'overlay', 'youtube']),
            ],
            [
                'name' => 'Enterprise',
                'price' => 99.99,
                'storage_mb_limit' => 10000,
                'max_channels' => 50,
                'features_json' => json_encode(['srt', 'rtmp', 'rtsp', 'mpegts', 'hls', 'vod_failover', 'overlay', 'youtube', 'priority_support']),
            ],
        ];

        foreach ($plans as $plan) {
            SubscriptionPlan::create($plan);
        }

        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@hybridstream.local',
            'password' => Hash::make('admin123'),
            'role' => 'admin',
        ]);

        $user = User::create([
            'name' => 'Demo Channel',
            'email' => 'demo@hybridstream.local',
            'password' => Hash::make('demo123'),
            'role' => 'channel_user',
            'subscription_plan_id' => SubscriptionPlan::first()->id,
        ]);

        $channel = Channel::create([
            'user_id' => $user->id,
            'name' => 'My First Channel',
            'ingest_protocol' => 'rtmp',
            'ingest_port' => 1935,
            'output_protocols_json' => ['rtmp', 'hls'],
        ]);

        $this->command->info("Seeded database!");
        $this->command->info("Admin: admin@hybridstream.local / admin123");
        $this->command->info("User: demo@hybridstream.local / demo123");
    }
}
