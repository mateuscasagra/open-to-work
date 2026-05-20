<?php

declare(strict_types=1);

return [
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

    // --- OAuth (Laravel Socialite) ---------------------------------------
    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI'),
    ],

    'linkedin' => [
        'client_id' => env('LINKEDIN_CLIENT_ID'),
        'client_secret' => env('LINKEDIN_CLIENT_SECRET'),
        'redirect' => env('LINKEDIN_REDIRECT_URI'),
    ],

    'github' => [
        'client_id' => env('GITHUB_CLIENT_ID'),
        'client_secret' => env('GITHUB_CLIENT_SECRET'),
        'redirect' => env('GITHUB_REDIRECT_URI'),
    ],

    // --- Asaas (pagamento PIX recorrente) --------------------------------
    'asaas' => [
        'api_key' => env('ASAAS_API_KEY'),
        'base_url' => env('ASAAS_BASE_URL', 'https://sandbox.asaas.com/api/v3'),
        'webhook_token' => env('ASAAS_WEBHOOK_TOKEN'),
        'pro_value_cents' => (int) env('ASAAS_PRO_VALUE_CENTS', 2500),
    ],

    // --- Suporte ----------------------------------------------------------
    // Destino de e-mails do formulário "Preciso de ajuda" na landing.
    'support' => [
        'recipient' => env('SUPPORT_EMAIL', 'zandonacasagrande@gmail.com'),
    ],
];
