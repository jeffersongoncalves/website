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

    'plugins_sync' => [
        // Bearer token the jeffersongoncalves/jeffersongoncalves repo's
        // notify-site-plugins-sync workflow sends when plugins.json changes,
        // so POST /api/plugins-sync can re-scan it for newly added repos.
        'token' => env('PLUGINS_SYNC_TOKEN'),
        'source_url' => env('PLUGINS_SYNC_SOURCE_URL', 'https://raw.githubusercontent.com/jeffersongoncalves/jeffersongoncalves/master/plugins.json'),

        // Flat "owner/repo" string lists generated from plugins.json by that
        // repo's extract-plugin-packages.js (pre-commit-regenerated whenever
        // plugins.json changes) — sorted, one entry per line, so the site can
        // diff them against its own last-fetched copy instead of re-scanning
        // and re-dispatching every entry in the nested plugins.json on every
        // sync. See SyncPluginsJsonJob.
        'owner_packages_url' => env('PLUGINS_SYNC_OWNER_PACKAGES_URL', 'https://raw.githubusercontent.com/jeffersongoncalves/jeffersongoncalves/master/plugins-packages-owner.json'),
        'collaborator_packages_url' => env('PLUGINS_SYNC_COLLABORATOR_PACKAGES_URL', 'https://raw.githubusercontent.com/jeffersongoncalves/jeffersongoncalves/master/plugins-packages-collaborator.json'),
    ],

];
