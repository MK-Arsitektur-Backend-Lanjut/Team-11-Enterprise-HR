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

    /*
    |--------------------------------------------------------------------------
    | Microservice Endpoints
    |--------------------------------------------------------------------------
    |
    | URL endpoint untuk berkomunikasi dengan microservice lain.
    | Employee Module: Profil karyawan, hierarki, leave balance.
    | Approval Module: Data pengajuan cuti yang sudah approved.
    |
    */

    'employee' => [
        'url' => env('EMPLOYEE_SERVICE_URL', 'http://127.0.0.1:8002/api/v1'),
    ],

    'approval' => [
        'url' => env('APPROVAL_SERVICE_URL', 'http://127.0.0.1:8003/api/v1'),
    ],

];
