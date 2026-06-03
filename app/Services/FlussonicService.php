<?php

namespace App\Services;

use App\Models\Channel;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FlussonicService
{
    private string $baseUrl;
    private string $login;
    private string $password;
    private int $httpPort;

    public function __construct()
    {
        $host = config('flussonic.host', '127.0.0.1');
        $apiPort = config('flussonic.api_port', 8080);
        $this->baseUrl = "http://{$host}:{$apiPort}";
        $this->login = config('flussonic.login', 'admin');
        $this->password = config('flussonic.password', 'admin');
        $this->httpPort = (int) config('flussonic.http_port', 8082);
    }

    public function getStreams(): array
    {
        return $this->get('/flussonic/api/v3/streams')['streams'] ?? [];
    }

    public function getStream(string $name): ?array
    {
        return $this->get("/flussonic/api/v3/streams/{$name}");
    }

    public function isStreamAlive(string $name): bool
    {
        $stream = $this->getStream($name);
        if (!$stream) return false;

        return ($stream['alive'] ?? false) === true;
    }

    public function getStreamStats(string $name): ?array
    {
        $stream = $this->getStream($name);
        if (!$stream) return null;

        return [
            'alive' => $stream['alive'] ?? false,
            'name' => $stream['name'] ?? $name,
            'input_url' => $stream['input_url'] ?? null,
            'clients' => $stream['clients'] ?? 0,
            'bitrate' => $stream['bitrate'] ?? 0,
            'uptime' => $stream['uptime'] ?? null,
        ];
    }

    public function getPlaybackUrls(string $name): array
    {
        $host = config('flussonic.host', '127.0.0.1');
        $rtmpPort = config('flussonic.rtmp_port', 1935);

        return [
            'rtmp' => "rtmp://{$host}:{$rtmpPort}/{$name}",
            'hls' => "http://{$host}:{$this->httpPort}/{$name}/index.m3u8",
            'dash' => "http://{$host}:{$this->httpPort}/{$name}/manifest.mpd",
            'screenshot' => "http://{$host}:{$this->httpPort}/{$name}/screenshot.jpg",
        ];
    }

    public function pushVodToStream(string $name, string $vodUrl): bool
    {
        try {
            $response = Http::withBasicAuth($this->login, $this->password)
                ->asJson()
                ->put("{$this->baseUrl}/flussonic/api/v3/streams/{$name}", [
                    'src' => $vodUrl,
                ]);

            return $response->successful();
        } catch (\Exception $e) {
            Log::error("Flussonic push VOD failed: {$e->getMessage()}");
            return false;
        }
    }

    public function stopStream(string $name): bool
    {
        try {
            $response = Http::withBasicAuth($this->login, $this->password)
                ->delete("{$this->baseUrl}/flussonic/api/v3/streams/{$name}");

            return $response->successful();
        } catch (\Exception $e) {
            Log::error("Flussonic stop stream failed: {$e->getMessage()}");
            return false;
        }
    }

    public function restartStream(string $name): bool
    {
        try {
            $response = Http::withBasicAuth($this->login, $this->password)
                ->post("{$this->baseUrl}/flussonic/api/v3/streams/{$name}/restart");

            return $response->successful();
        } catch (\Exception $e) {
            Log::error("Flussonic restart stream failed: {$e->getMessage()}");
            return false;
        }
    }

    public function getServerStats(): ?array
    {
        $response = $this->get('/flussonic/api/v3/server');
        if (!$response) return null;

        return [
            'version' => $response['version'] ?? 'unknown',
            'uptime' => $response['uptime'] ?? null,
            'total_streams' => $response['total_streams'] ?? 0,
            'active_streams' => $response['active_streams'] ?? 0,
            'cpu_usage' => $response['cpu'] ?? 0,
            'memory_used' => $response['memory_used'] ?? 0,
        ];
    }

    public function getInputUrl(Channel $channel): string
    {
        $host = config('flussonic.host', '127.0.0.1');
        $protocol = $channel->ingest_protocol;
        $port = $channel->ingest_port;
        $key = $channel->stream_key;

        return match ($protocol) {
            'rtmp' => "rtmp://{$host}:{$port}/{$key}",
            'srt' => "srt://{$host}:{$port}?streamid={$key}",
            'rtsp' => "rtsp://{$host}:{$port}/{$key}",
            'mpegts' => "udp://{$host}:{$port}",
            default => "rtmp://{$host}:{$port}/{$key}",
        };
    }

    public function isAvailable(): bool
    {
        try {
            $response = Http::withBasicAuth($this->login, $this->password)
                ->timeout(3)
                ->get("{$this->baseUrl}/flussonic/api/v3/server");

            return $response->successful();
        } catch (\Exception $e) {
            return false;
        }
    }

    private function get(string $endpoint): ?array
    {
        try {
            $response = Http::withBasicAuth($this->login, $this->password)
                ->timeout(5)
                ->get("{$this->baseUrl}{$endpoint}");

            if ($response->successful()) {
                return $response->json();
            }

            Log::warning("Flussonic API error: {$response->status()} on {$endpoint}");
            return null;
        } catch (\Exception $e) {
            Log::warning("Flussonic API unreachable: {$e->getMessage()}");
            return null;
        }
    }
}
