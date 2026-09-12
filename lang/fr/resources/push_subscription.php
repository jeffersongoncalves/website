<?php

declare(strict_types=1);

return [
    'singular' => 'Abonnement push',
    'plural' => 'Abonnements push',
    'navigation_label' => 'Push',

    'fields' => [
        'endpoint_host' => 'Service',
        'locale' => 'Langue',
        'user' => 'Utilisateur',
        'anonymous' => 'anonyme',
        'user_agent' => 'User-Agent',
        'created_at' => 'Abonné le',
        'last_used_at' => 'Dernier envoi',
    ],

    'broadcast' => [
        'label' => 'Envoyer une diffusion',
        'title' => 'Titre',
        'body' => 'Corps',
        'url' => 'URL de clic',
        'success' => 'Push envoyé',
        'failure' => 'Échec du push',
    ],
];
