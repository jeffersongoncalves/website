<?php

declare(strict_types=1);

return [
    'singular' => 'Push-Abonnement',
    'plural' => 'Push-Abonnements',
    'navigation_label' => 'Push',

    'fields' => [
        'endpoint_host' => 'Dienst',
        'locale' => 'Sprache',
        'user' => 'Benutzer',
        'anonymous' => 'anonym',
        'user_agent' => 'User-Agent',
        'created_at' => 'Abonniert am',
        'last_used_at' => 'Letzte Zustellung',
    ],

    'broadcast' => [
        'label' => 'Broadcast senden',
        'title' => 'Titel',
        'body' => 'Text',
        'url' => 'Klick-URL',
        'success' => 'Push gesendet',
        'failure' => 'Push fehlgeschlagen',
    ],
];
