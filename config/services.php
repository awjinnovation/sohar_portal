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

    // Platform-level fallback; each tenant can override these in
    // tenants.gateway_credentials.bank_muscat (encrypted, admin panel).
    'bank_muscat' => [
        'gateway_url' => env('BANK_MUSCAT_GATEWAY_URL', 'https://spayuattrns.bmtest.om/transaction.do?command=initiateTransaction'),
        'access_code' => env('BANK_MUSCAT_ACCESS_CODE'),
        'working_key' => env('BANK_MUSCAT_WORKING_KEY'),
        'merchant_id' => env('BANK_MUSCAT_MERCHANT_ID'),
        // Optional comma-separated IP allowlist for the payment webhook.
        // Leave blank to allow all (e.g. local dev); set in production.
        'webhook_ips' => array_filter(array_map('trim', explode(',', (string) env('BANK_MUSCAT_WEBHOOK_IPS', '')))),
    ],

    // AI Experience (docs/ai_and_gate_contract.md §A). The key is read only
    // through config('services.openai.key') and is never logged.
    'openai' => [
        'key' => env('OPENAI_API_KEY'),
        'base_url' => rtrim((string) (env('OPENAI_BASE_URL') ?: 'https://api.openai.com/v1'), '/'),
        'organization' => env('OPENAI_ORGANIZATION'),
        // fast = concierge chat, ops summaries, daily briefing; smart = plan my visit, copilot.
        'model_fast' => env('OPENAI_MODEL_FAST') ?: 'gpt-6-luna',
        'model_smart' => env('OPENAI_MODEL_SMART') ?: 'gpt-6.1-sol',
        // Reasoning effort per tier (none|minimal|low|medium|high; model-dependent).
        'reasoning_fast' => env('OPENAI_REASONING_FAST', 'low'),
        'reasoning_smart' => env('OPENAI_REASONING_SMART', 'low'),
        'timeout' => (int) env('OPENAI_TIMEOUT', 45),
        'connect_timeout' => (int) env('OPENAI_CONNECT_TIMEOUT', 8),
        'retries' => (int) env('OPENAI_RETRIES', 2),
    ],

    'ismartsms' => [
        'url' => env('ISMARTSMS_URL', 'https://www.ismartsms.net/iBulkSMS/HttpWS/SMSDynamicAPI.aspx'),
        'user_id' => env('ISMARTSMS_USER_ID'),
        'password' => env('ISMARTSMS_PASSWORD'),
    ],
];
