<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may be executed
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        'http://localhost:3000',      // React dev server
        'http://localhost:5173',      // Vite dev server
        'http://localhost:5174',      // Vite alternative
        'http://127.0.0.1:3000',
        'http://127.0.0.1:5173',
        'http://127.0.0.1:5174',
    ],

    'allowed_origins_patterns' => [
        '#^http://localhost:[0-9]+$#',
        '#^http://127\.0\.0\.1:[0-9]+$#',
    ],

    'allowed_headers' => ['Content-Type', 'Authorization', 'X-Requested-With', 'Idempotency-Key'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];

