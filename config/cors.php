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
    'allowed_origins' => ['http://127.0.0.1:3000', '*'],
    'allowed_origins_patterns' => [':3000'],
    'allowed_headers' => [
        'Content-Type',
        'X-Requested-With',
        'Authorization',
        'X-CSRF-TOKEN',
        'Access-Control-Allow-Origin'
    ],

    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => true

];
