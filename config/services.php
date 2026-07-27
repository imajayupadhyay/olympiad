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

    'razorpay' => [
        'key_id' => env('RAZORPAY_KEY_ID'),
        'key_secret' => env('RAZORPAY_KEY_SECRET'),
        'webhook_secret' => env('RAZORPAY_WEBHOOK_SECRET'),
        'reconciliation' => [
            'delay_minutes' => (int) env('RAZORPAY_RECONCILIATION_DELAY_MINUTES', 2),
            'lookback_days' => (int) env('RAZORPAY_RECONCILIATION_LOOKBACK_DAYS', 30),
            'batch_size' => (int) env('RAZORPAY_RECONCILIATION_BATCH_SIZE', 100),
        ],
    ],

    'brevo' => [
        'api_key' => env('BREVO_API_KEY'),
        'endpoint' => env('BREVO_ENDPOINT', 'https://api.brevo.com/v3/smtp/email'),
        'sender_email' => env('BREVO_SENDER_EMAIL', env('MAIL_FROM_ADDRESS', 'hello@example.com')),
        'sender_name' => env('BREVO_SENDER_NAME', env('MAIL_FROM_NAME', env('APP_NAME', 'National Olympiad Hunt'))),
        'reply_to_email' => env('BREVO_REPLY_TO_EMAIL'),
        'reply_to_name' => env('BREVO_REPLY_TO_NAME'),
        'support_email' => env('SUPPORT_EMAIL', env('MAIL_FROM_ADDRESS')),
        'support_phone' => env('SUPPORT_PHONE', '+91 72890 89009'),
    ],

    'aisensy' => [
        'api_key' => env('AISENSY_API_KEY'),
        'campaign_name' => env('AISENSY_CAMPAIGN_NAME'),
        'endpoint' => env('AISENSY_ENDPOINT', 'https://backend.aisensy.com/campaign/t1/api/v2'),
        'source' => env('AISENSY_SOURCE', 'neo_student_login'),
    ],

    'auth_otp' => [
        // Use a dedicated random production secret. APP_KEY is a safe fallback
        // for development and prevents an empty pepper from weakening hashes.
        'pepper' => env('OTP_PEPPER') ?: env('APP_KEY'),
    ],

];
