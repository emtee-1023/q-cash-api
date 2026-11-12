<?php

$webhookBaseUrl = (string) env('MPESA_WEBHOOK_BASE_URL');

if ($webhookBaseUrl === '') {
    $webhookBaseUrl = (string) env('APP_URL', 'http://localhost');
}

$webhookBaseUrl = rtrim($webhookBaseUrl, '/');

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

    'africastalking' => [
        'username' => env('AFRICASTALKING_USERNAME'),
        'api_key' => env('AFRICASTALKING_API_KEY'),
        'endpoint' => env('AFRICASTALKING_ENDPOINT'),

    ],

    'ml_api' => [
        'url' => env('QCASH_ML_API_URL'),
        'key' => env('QCASH_ML_API_KEY'),
    ],

    'mpesa' => [
        'paybill_number' => env('MPESA_PAYBILL_NUMBER'),
        'consumer_key' => env('MPESA_CONSUMER_KEY'),
        'consumer_secret' => env('MPESA_CONSUMER_SECRET'),
        'passkey' => env('MPESA_PASSKEY'),
        'environment' => env('MPESA_ENVIRONMENT', 'sandbox'),
        'base_url' => env('MPESA_BASE_URL'),
        'callback_url' => env('MPESA_CALLBACK_URL', $webhookBaseUrl.'/api/webhooks/confirmation'),
        'validation_url' => env('MPESA_VALIDATION_URL', $webhookBaseUrl.'/api/webhooks/validation'),
        'validation_enabled' => env('MPESA_VALIDATION_ENABLED', false),
        'stk_callback_url' => env('MPESA_STK_CALLBACK_URL', $webhookBaseUrl.'/api/webhooks/stk'),
        'timezone' => env('MPESA_TIMEZONE', 'Africa/Nairobi'),
        'shortcode' => env('MPESA_SHORTCODE'),
        'b2c' => [
            'shortcode' => env('MPESA_B2C_SHORTCODE', env('MPESA_SHORTCODE')),
            'initiator_name' => env('MPESA_B2C_INITIATOR_NAME'),
            'initiator_password' => env('MPESA_B2C_INITIATOR_PASSWORD'),
            'certificate_path' => env('MPESA_B2C_CERTIFICATE_PATH', storage_path('app/certificates/mpesa_public_cert.cer')),
            'security_credential' => env('MPESA_B2C_SECURITY_CREDENTIAL'),
            'command_id' => env('MPESA_B2C_COMMAND_ID', 'BusinessPayment'),
            'result_url' => env('MPESA_B2C_RESULT_URL', $webhookBaseUrl.'/api/webhooks/b2c/result'),
            'timeout_url' => env('MPESA_B2C_TIMEOUT_URL', $webhookBaseUrl.'/api/webhooks/b2c/timeout'),
        ],
    ],

];
