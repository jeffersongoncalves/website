<?php

declare(strict_types=1);

return [
    'singular' => 'Push Subscription',
    'plural' => 'Push Subscriptions',
    'navigation_label' => 'Push',

    'fields' => [
        'endpoint_host' => 'Service',
        'locale' => 'Locale',
        'user' => 'User',
        'anonymous' => 'anonymous',
        'user_agent' => 'User-Agent',
        'created_at' => 'Subscribed at',
        'last_used_at' => 'Last delivery',
    ],

    'broadcast' => [
        'label' => 'Send broadcast',
        'title' => 'Title',
        'body' => 'Body',
        'url' => 'Click URL',
        'success' => 'Push sent',
        'failure' => 'Push failed',
    ],
];
