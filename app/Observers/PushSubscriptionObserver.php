<?php

namespace App\Observers;

use App\Models\PushSubscription;
use Illuminate\Support\Facades\Cache;
use Psr\SimpleCache\InvalidArgumentException;

class PushSubscriptionObserver
{
    public function created(PushSubscription $subscription): void
    {
        $this->flush();
    }

    public function deleted(PushSubscription $subscription): void
    {
        $this->flush();
    }

    private function flush(): void
    {
        try {
            Cache::delete('push_subscriptions_count');
        } catch (InvalidArgumentException) {
        }
    }
}
