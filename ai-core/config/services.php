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

    'whatsapp_cloud' => [
        'access_token' => env('WHATSAPP_CLOUD_API_TOKEN'),
        'phone_number_id' => env('WHATSAPP_CLOUD_PHONE_NUMBER_ID'),
        'api_version' => env('WHATSAPP_CLOUD_API_VERSION'),
        'order_template_name' => env('WHATSAPP_ORDER_TEMPLATE_NAME'),
        'order_template_language' => env('WHATSAPP_ORDER_TEMPLATE_LANGUAGE', 'ar'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'menu_scan_model' => env('OPENAI_MENU_SCAN_MODEL', 'gpt-5.5'),
        'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
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

];
