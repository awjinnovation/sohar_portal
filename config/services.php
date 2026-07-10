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
        'token' => env('POSTMARK_TOKEN'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
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

    'thawani' => [
        'url' => env('THAWANI_URL', 'https://uatcheckout.thawani.om'),
        'secret_key' => env('THAWANI_SECRET_KEY'),
        'publishable_key' => env('THAWANI_PUBLISHABLE_KEY'),
    ],

    'bank_muscat' => [
        'gateway_url' => env('BANK_MUSCAT_GATEWAY_URL', 'https://spayuattrns.bmtest.om/transaction.do?command=initiateTransaction'),
        'access_code' => env('BANK_MUSCAT_ACCESS_CODE'),
        'working_key' => env('BANK_MUSCAT_WORKING_KEY'),
        'merchant_id' => env('BANK_MUSCAT_MERCHANT_ID'),
        // Optional comma-separated IP allowlist for the payment webhook.
        // Leave blank to allow all (e.g. local dev); set in production.
        'webhook_ips' => array_filter(array_map('trim', explode(',', (string) env('BANK_MUSCAT_WEBHOOK_IPS', '')))),
    ],

    'ismartsms' => [
        'url' => env('ISMARTSMS_URL', 'https://www.ismartsms.net/iBulkSMS/HttpWS/SMSDynamicAPI.aspx'),
        'user_id' => env('ISMARTSMS_USER_ID'),
        'password' => env('ISMARTSMS_PASSWORD'),
    ],
];
