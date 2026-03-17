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
        'base_url' => env('APPMAX_BASE_URL', 'https://homolog.sandboxappmax.com.br'),
        'access_token' => env('APPMAX_ACCESS_TOKEN'),
        'default_payment_method' => env('APPMAX_DEFAULT_PAYMENT_METHOD', 'pix'),
        'webhook_secret' => env('APPMAX_WEBHOOK_SECRET'),
        'webhook_signature_header' => env('APPMAX_WEBHOOK_SIGNATURE_HEADER', 'X-Appmax-Signature'),
    ],

];
