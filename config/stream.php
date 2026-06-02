<?php

return [
    'host' => env('STREAM_HOST', '127.0.0.1'),
    'health_check_interval' => env('STREAM_HEALTH_CHECK_INTERVAL', 3),
    'max_channels_per_server' => env('STREAM_MAX_CHANNELS_PER_SERVER', 10),

    'flussonic' => [
        'host' => env('FLUSSONIC_HOST', '127.0.0.1'),
        'api_port' => env('FLUSSONIC_API_PORT', 8080),
        'http_port' => env('FLUSSONIC_HTTP_PORT', 8082),
        'rtmp_port' => env('FLUSSONIC_RTMP_PORT', 1935),
        'login' => env('FLUSSONIC_LOGIN', 'admin'),
        'password' => env('FLUSSONIC_PASSWORD', 'admin'),
    ],

    'default_port' => [
        'srt' => 10000,
        'rtmp' => 1935,
        'rtsp' => 8554,
        'mpegts' => 5000,
    ],
];
