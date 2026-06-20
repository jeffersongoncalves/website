<?php

declare(strict_types=1);

return [
    'locales' => [
        'pt_BR' => '🇧🇷 Português',
        'en' => '🇺🇸 Inglês',
        'es' => '🇪🇸 Espanhol',
    ],
    'locale_column' => 'locale',
    'theme_color_column' => 'theme_color',
    'avatar_column' => 'avatar_url',
    'disk' => env('FILESYSTEM_DISK', 'public'),
    'visibility' => 'public', // or replace by filesystem disk visibility with fallback value
];
