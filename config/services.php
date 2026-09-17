<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],



    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI')
            ? rtrim(env('GOOGLE_REDIRECT_URI'), '/')
            : (rtrim(env('APP_URL', 'https://laijau.com'), '/') . '/auth/google/callback'),
    ],

    'storefront' => [
        'revalidate_url' => env('STOREFRONT_REVALIDATE_URL', 'disabled'),
        'revalidate_secret' => env('STOREFRONT_REVALIDATE_SECRET', env('REVALIDATION_SECRET')),
    ],

    'ncm' => [
        'mode' => env('NCM_MODE', 'sandbox'),
        'sandbox_url' => env('NCM_SANDBOX_URL', 'https://demo.nepalcanmove.com'),
        'production_url' => env('NCM_PRODUCTION_URL', 'https://nepalcanmove.com'),
        'api_token' => env('NCM_API_TOKEN'),
        'from_branch' => env('NCM_DEFAULT_FROM_BRANCH', 'TINKUNE'),
        'webhook_secret' => env('NCM_WEBHOOK_SECRET'),
    ],

    'pathao' => [
        'mode' => env('PATHAO_MODE', 'sandbox'),
        'sandbox_url' => env('PATHAO_SANDBOX_URL', 'https://courier-api-sandbox.pathao.com'),
        'production_url' => env('PATHAO_PRODUCTION_URL', 'https://courier-api.pathao.com'),
        'client_id' => env('PATHAO_CLIENT_ID'),
        'client_secret' => env('PATHAO_CLIENT_SECRET'),
        'username' => env('PATHAO_USERNAME'),
        'password' => env('PATHAO_PASSWORD'),
        'store_id' => env('PATHAO_STORE_ID'),
        'webhook_secret' => env('PATHAO_WEBHOOK_SECRET'),
    ],

    'connectips' => [
        'mode' => env('CONNECTIPS_MODE', env('CONNECTIPS_ENV', 'sandbox')),
        'merchant_id' => env('CONNECTIPS_MERCHANT_ID', ''),
        'app_id' => env('CONNECTIPS_APP_ID', ''),
        'app_name' => env('CONNECTIPS_APP_NAME', 'LAIJAU'),
        'password' => env('CONNECTIPS_PASSWORD', ''),
        'cert_path' => env('CONNECTIPS_CERT_PATH', ''),
        'cert_password' => env('CONNECTIPS_CERT_PASSWORD', ''),
        'sandbox_url' => env('CONNECTIPS_SANDBOX_URL', 'https://uat.connectips.com'),
        'production_url' => env('CONNECTIPS_PRODUCTION_URL', 'https://login.connectips.com'),
    ],

];

