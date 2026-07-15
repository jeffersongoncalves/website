<?php

declare(strict_types=1);

namespace App\Services;

use PostHog\PostHog;

/**
 * Thin wrapper around the posthog/posthog-php SDK. No-ops when disabled or
 * unconfigured, so call sites don't need their own config checks.
 */
class PostHogService
{
    protected static bool $initialized = false;

    public function __construct()
    {
        if ($this->disabled()) {
            return;
        }

        if (! self::$initialized) {
            PostHog::init(config('services.posthog.api_key'), [
                'host' => config('services.posthog.host'),
            ]);
            self::$initialized = true;
        }
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    public function capture(string $distinctId, string $event, array $properties = []): void
    {
        if ($this->disabled()) {
            return;
        }

        PostHog::capture([
            'distinctId' => $distinctId,
            'event' => $event,
            'properties' => $properties,
        ]);
    }

    private function disabled(): bool
    {
        return (bool) config('services.posthog.disabled') || empty(config('services.posthog.api_key'));
    }
}
