<?php

declare(strict_types=1);

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

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'github' => [
        'token' => env('GITHUB_TOKEN'),
        'username' => env('GITHUB_USERNAME', 'jeffersongoncalves'),
    ],

    'maxmind' => [
        // Free account + license key: https://www.maxmind.com/en/geolite2/signup-form
        // Used by the geoip:update command (App\Console\Commands\UpdateGeoIpDatabase).
        'license_key' => env('MAXMIND_LICENSE_KEY'),
    ],

    'plugins_sync' => [
        // Bearer token the jeffersongoncalves/jeffersongoncalves repo's
        // notify-site-plugins-sync workflow sends when plugins.json changes,
        // so POST /api/plugins-sync can re-scan it for newly added repos.
        'token' => env('PLUGINS_SYNC_TOKEN'),
        'source_url' => env('PLUGINS_SYNC_SOURCE_URL', 'https://raw.githubusercontent.com/jeffersongoncalves/jeffersongoncalves/master/plugins.json'),
    ],

];
