<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | GitHub Personal Access Token
    |--------------------------------------------------------------------------
    |
    | The token used to authenticate REST calls. Increases rate limits and
    | grants access to private repositories. Create at:
    | https://github.com/settings/tokens/new
    |
    | When this is null the client falls back to config('services.github.token').
    |
    */
    'token' => env('GITHUB_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | User Agent
    |--------------------------------------------------------------------------
    |
    | GitHub requires a User-Agent header on every request. Set this to a
    | value that identifies your application.
    |
    */
    'user_agent' => env('GITHUB_USER_AGENT', 'jeffersongoncalves-site'),

    /*
    |--------------------------------------------------------------------------
    | Request Timeout
    |--------------------------------------------------------------------------
    |
    | The number of seconds to wait for a response before giving up.
    |
    */
    'timeout' => (int) env('GITHUB_TIMEOUT', 8),
];
