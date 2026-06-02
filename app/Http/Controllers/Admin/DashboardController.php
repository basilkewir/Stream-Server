<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Channel;
use App\Models\StreamHealthLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        $totalUsers = User::where('role', 'channel_user')->count();
        $totalChannels = Channel::count();
        $liveChannels = Channel::where('is_live_streaming', true)->count();
        $failoverChannels = Channel::where('failover_active', true)->count();
        $totalStorage = User::sum('storage_used_mb');
        $totalSubs = User::whereNotNull('subscription_plan_id')->count();

        $protocolBreakdown = Channel::select('ingest_protocol', DB::raw('count(*) as count'))
            ->groupBy('ingest_protocol')->get();

        $healthToday = StreamHealthLog::whereDate('created_at', today())->count();
        $failoverToday = StreamHealthLog::whereDate('created_at', today())
            ->where('event_type', 'vod_failover')->count();
        $restoreToday = StreamHealthLog::whereDate('created_at', today())
            ->where('event_type', 'live_restored')->count();

        $topChannels = Channel::with('user')
            ->withCount(['vodPlaylistItems', 'healthLogs'])
            ->latest()->take(10)->get();

        $recentHealth = StreamHealthLog::with('channel.user')
            ->latest()->take(30)->get();

        $serverInfo = [
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'PHP Built-in',
            'server_os' => PHP_OS,
            'server_time' => now()->toDateTimeString(),
            'server_uptime' => $this->getServerUptime(),
            'disk_free' => $this->formatBytes(disk_free_space(storage_path())),
            'disk_total' => $this->formatBytes(disk_total_space(storage_path())),
            'memory_usage' => $this->getMemoryUsage(),
        ];

        return Inertia::render('Admin/Dashboard', [
            'stats' => compact('totalUsers', 'totalChannels', 'liveChannels', 'failoverChannels', 'totalStorage', 'totalSubs'),
            'protocol_breakdown' => $protocolBreakdown,
            'health_stats' => compact('healthToday', 'failoverToday', 'restoreToday'),
            'recent_health_logs' => $recentHealth,
            'channels' => $topChannels,
            'server' => $serverInfo,
        ]);
    }

    private function getServerUptime(): string
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $output = shell_exec('systeminfo | find "System Boot Time"');
            return $output ? trim(explode(':', $output, 2)[1] ?? 'Unknown') : 'Unknown';
        }
        $uptime = @file_get_contents('/proc/uptime');
        if ($uptime) {
            $seconds = (int) explode(' ', $uptime)[0];
            $d = floor($seconds / 86400);
            $h = floor(($seconds % 86400) / 3600);
            $m = floor(($seconds % 3600) / 60);
            return "{$d}d {$h}h {$m}m";
        }
        return 'Unknown';
    }

    private function getMemoryUsage(): string
    {
        if (function_exists('memory_get_usage')) {
            $used = memory_get_usage(true);
            $peak = memory_get_peak_usage(true);
            return $this->formatBytes($peak);
        }
        return 'N/A';
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 1) . ' ' . $units[$i];
    }
}
