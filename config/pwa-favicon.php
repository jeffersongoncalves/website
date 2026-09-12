<?php

declare(strict_types=1);

return [
    // Master switch. When false the package registers no routes at all, so
    // /manifest.json, /browserconfig.xml and /favicon.ico stay 404.
    'enabled' => true,

    // The Web App Manifest. These values are emitted verbatim by the
    // /manifest.json endpoint; the spec-shaped `icons[]` array (Android
    // densities + 512 master + maskable) is built at request time from the
    // `manifest.icons` density map below, so do NOT hand-write `icons` here.
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
        // theme_color = top browser chrome (Android address bar / iOS status
        // bar tint). background_color = splash bg shown before the page
        // paints. Keep them aligned with your first paint for a seamless
        // install transition.
        'theme_color' => '#0B0A09',
        'background_color' => '#0B0A09',
        'lang' => 'pt-BR',
        'dir' => 'ltr',
        'categories' => ['productivity', 'developer'],
        // Density-based Android icon hints. Each `size => density` pair maps
        // to resources/favicon/android-icon-{size}x{size}.png and is emitted
        // as one entry in the manifest `icons[]` array. The 512 master and
        // maskable variant are appended automatically.
        'icons' => [
            '36' => '0.75',
            '48' => '1.0',
            '72' => '1.5',
            '96' => '2.0',
            '144' => '3.0',
            '192' => '4.0',
        ],
    ],
];
