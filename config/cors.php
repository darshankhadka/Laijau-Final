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

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    'allowed_origins' => array_values(array_filter([
        'https://laijau.com',
        'https://www.laijau.com',
        env('FRONTEND_URL'),
        in_array(env('APP_ENV'), ['local', 'testing']) ? 'http://localhost:8000' : null,
        in_array(env('APP_ENV'), ['local', 'testing']) ? 'http://127.0.0.1:8000' : null,
        in_array(env('APP_ENV'), ['local', 'testing']) ? 'http://localhost:8080' : null,
        in_array(env('APP_ENV'), ['local', 'testing']) ? 'http://127.0.0.1:8080' : null,
        in_array(env('APP_ENV'), ['local', 'testing']) ? 'http://localhost:3000' : null,
    ])),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];
