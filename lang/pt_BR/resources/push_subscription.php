<?php

declare(strict_types=1);

return [
    'singular' => 'Inscrição Push',
    'plural' => 'Inscrições Push',
    'navigation_label' => 'Push',

    'fields' => [
        'endpoint_host' => 'Serviço',
        'locale' => 'Idioma',
        'user' => 'Usuário',
        'anonymous' => 'anônimo',
        'user_agent' => 'User-Agent',
        'created_at' => 'Inscrito em',
        'last_used_at' => 'Último envio',
    ],

    'broadcast' => [
        'label' => 'Enviar broadcast',
        'title' => 'Título',
        'body' => 'Mensagem',
        'url' => 'URL ao clicar',
        'success' => 'Push enviado',
        'failure' => 'Push falhou',
    ],
];
