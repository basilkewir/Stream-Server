<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Channel extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'ingest_protocol',
        'ingest_endpoint',
        'stream_key',
        'output_protocols_json',
        'ingest_port',
        'is_live_streaming',
        'last_live_timestamp',
        'failover_active',
        'failover_ffmpeg_pid',
        'playlist_mode',
        'playlist_loop',
        'playlist_fill_action',
    ];

    protected function casts(): array
    {
        return [
            'output_protocols_json' => 'array',
            'is_live_streaming' => 'boolean',
            'failover_active' => 'boolean',
            'playlist_loop' => 'boolean',
            'last_live_timestamp' => 'datetime',
        ];
    }

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($channel) {
            if (empty($channel->stream_key)) {
                $channel->stream_key = bin2hex(random_bytes(8)); // 16 char alphanumeric, no special chars
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function vodPlaylistItems(): HasMany
    {
        return $this->hasMany(VodPlaylistItem::class)->orderBy('order');
    }

    public function overlaySetting()
    {
        return $this->hasOne(VodOverlaySetting::class);
    }

    public function healthLogs(): HasMany
    {
        return $this->hasMany(StreamHealthLog::class);
    }

    public function getFormattedIngestUrlAttribute(): string
    {
        $host = config('flussonic.host', request()->getHost());
        $protocol = $this->ingest_protocol;
        $port = $this->ingest_port;
        $key = $this->stream_key;

        return match ($protocol) {
            'rtmp' => "rtmp://{$host}:{$port}/static/{$key}",
            'srt' => "srt://{$host}:{$port}?streamid=static/{$key}",
            'rtsp' => "rtsp://{$host}:{$port}/static/{$key}",
            'mpegts' => "udp://{$host}:{$port}",
            default => "rtmp://{$host}:{$port}/static/{$key}",
        };
    }

    public function getFormattedOutputUrlsAttribute(): array
    {
        $host = config('flussonic.host', request()->getHost());
        $key = $this->stream_key;
        $rtmpPort = config('flussonic.rtmp_port', 1935);

        return [
            'rtmp' => "rtmp://{$host}:{$rtmpPort}/static/{$key}",
            'hls' => "http://{$host}/static/{$key}/index.m3u8",
            'dash' => "http://{$host}/static/{$key}/manifest.mpd",
            'screenshot' => "http://{$host}/static/{$key}/screenshot.jpg",
        ];
    }
}
