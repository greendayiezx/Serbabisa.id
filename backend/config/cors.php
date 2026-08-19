<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    // JANGAN pakai '*' di production. Whitelist origin frontend saja,
    // diambil dari env FRONTEND_URL (bisa lebih dari satu, dipisah koma).
    'allowed_origins' => array_filter(
        explode(',', env('FRONTEND_URL', 'http://localhost:5173'))
    ),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    // true jika suatu saat pindah ke Sanctum SPA (cookie). Untuk token
    // Bearer di header, false sudah cukup.
    'supports_credentials' => false,

];
