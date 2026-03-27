<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
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

    'api' => [
        'key' => env('API_KEY'),
    ],

    'vdp' => [
        'url' => env('VDP_API_URL'),
    ],

    'botpress' => [
        'webhook_url' => env('BOTPRESS_WEBHOOK_URL'),
    ],

    'c6bank' => [
        'base_url' => env('C6BANK_BASE_URL'),
        'client_id' => env('C6BANK_CLIENT_ID'),
        'client_secret' => env('C6BANK_CLIENT_SECRET'),
        'api_key' => env('C6BANK_API_KEY'),
    ],

    'payment' => [
        'gateway' => env('PAYMENT_GATEWAY', 'c6bank'),
    ],

    'appmax' => [
        'api_url' => env('APPMAX_API_URL', 'https://api.appmax.com.br'),
        'auth_url' => env('APPMAX_AUTH_URL', 'https://auth.appmax.com.br'),
        'client_id' => env('APPMAX_CLIENT_ID'),
        'client_secret' => env('APPMAX_CLIENT_SECRET'),
        'default_payment_method' => env('APPMAX_DEFAULT_PAYMENT_METHOD', 'pix'),
        'soft_descriptor' => env('APPMAX_SOFT_DESCRIPTOR', 'VDP'),
    ],

    'abacatepay' => [
        'api_url' => env('ABACATEPAY_API_URL', 'https://api.abacatepay.com/v1'),
        'api_key' => env('ABACATEPAY_API_KEY'),
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URL', '/auth/google/callback'),
    ],

];
