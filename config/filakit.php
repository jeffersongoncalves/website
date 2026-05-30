<?php

return [
    'defaultCurrency' => 'brl',
    'defaultDateDisplayFormat' => 'M j, Y',
    'defaultIsoDateDisplayFormat' => 'L',
    'defaultDateTimeDisplayFormat' => 'M j, Y H:i:s',
    'defaultIsoDateTimeDisplayFormat' => 'LLL',
    'defaultNumberLocale' => null,
    'defaultTimeDisplayFormat' => 'H:i:s',
    'defaultIsoTimeDisplayFormat' => 'LT',
    'admin_panel_enabled' => true,
    'logo' => 'resources/images/admin-logo.png',
    'favicon' => [
        'enabled' => true,
        'manifest' => [
            'name' => env('APP_NAME', 'Jefferson Gonçalves — Full Stack PHP Developer'),
            'short_name' => 'Jefferson G.',
            'description' => 'Jefferson Simão Gonçalves — Full Stack PHP Developer. Filament plugins, Laravel packages, open-source work.',
            // start_url carries a `source=pwa` flag so analytics can split
            // installs vs regular web hits without affecting routing.
            'start_url' => '/?source=pwa',
            'scope' => '/',
            'display' => 'standalone',
            'orientation' => 'any',
            // theme_color = top browser chrome (Android address bar / iOS
            // status bar tint). background_color = splash bg shown before
            // the page paints. Both match the site's default-dark theme so
            // the install transition stays seamless with the first paint.
            'theme_color' => '#0B0A09',
            'background_color' => '#0B0A09',
            'lang' => 'pt-BR',
            'dir' => 'ltr',
            'categories' => ['productivity', 'developer'],
            // Density-based legacy entries used by the old `parseIcons`
            // helper. Kept for the Android density hint; the full PWA icon
            // array (including 512 + maskable) is emitted by
            // FaviconSupport::pwaIcons() at request time.
            'icons' => [
                '36' => '0.75',
                '48' => '1.0',
                '72' => '1.5',
                '96' => '2.0',
                '144' => '3.0',
                '192' => '4.0',
            ],
        ],
        'favicon' => 'resources/favicon/favicon.ico',
    ],
];
