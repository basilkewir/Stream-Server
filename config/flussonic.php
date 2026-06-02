<?php

return [
    'host' => env('FLUSSONIC_HOST', '127.0.0.1'),
    'api_port' => env('FLUSSONIC_API_PORT', 8080),
    'http_port' => env('FLUSSONIC_HTTP_PORT', 8082),
    'rtmp_port' => env('FLUSSONIC_RTMP_PORT', 1935),
    'login' => env('FLUSSONIC_LOGIN', 'admin'),
    'password' => env('FLUSSONIC_PASSWORD', 'admin'),
    'api_url' => env('FLUSSONIC_API_URL', 'http://127.0.0.1:8080'),
];
