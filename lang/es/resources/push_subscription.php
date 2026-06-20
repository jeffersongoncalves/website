<?php

declare(strict_types=1);

return [
    'singular' => 'Suscripción Push',
    'plural' => 'Suscripciones Push',
    'navigation_label' => 'Push',

    'fields' => [
        'endpoint_host' => 'Servicio',
        'locale' => 'Idioma',
        'user' => 'Usuario',
        'anonymous' => 'anónimo',
        'user_agent' => 'User-Agent',
        'created_at' => 'Suscrito el',
        'last_used_at' => 'Último envío',
    ],

    'broadcast' => [
        'label' => 'Enviar broadcast',
        'title' => 'Título',
        'body' => 'Mensaje',
        'url' => 'URL al hacer clic',
        'success' => 'Push enviado',
        'failure' => 'Push falló',
    ],
];
