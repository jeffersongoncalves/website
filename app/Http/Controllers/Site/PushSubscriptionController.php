<?php

namespace App\Http\Controllers\Site;

use App\Models\PushSubscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PushSubscriptionController
{
    /**
     * Persist a Push API subscription handed over by the browser. The
     * endpoint URL doubles as identity — same browser re-subscribing
     * upserts onto the same row so we never accumulate duplicates after
     * a permissions reset.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'endpoint' => ['required', 'string', 'url', 'max:2048'],
            'keys.p256dh' => ['required', 'string', 'max:255'],
            'keys.auth' => ['required', 'string', 'max:255'],
            'locale' => ['nullable', 'string', 'max:8'],
        ]);

        $endpoint = (string) $data['endpoint'];

        PushSubscription::query()->updateOrCreate(
            ['endpoint_hash' => PushSubscription::hashEndpoint($endpoint)],
            [
                'endpoint' => $endpoint,
                'p256dh' => $data['keys']['p256dh'],
                'auth' => $data['keys']['auth'],
                'locale' => $data['locale'] ?? app()->getLocale(),
                'user_agent' => substr((string) $request->userAgent(), 0, 255) ?: null,
                'last_used_at' => now(),
            ],
        );

        return response()->json(['ok' => true]);
    }

    /**
     * Drop the subscription row when the browser tells us the user
     * revoked permission or the user clicked the unsubscribe affordance.
     */
    public function destroy(Request $request): JsonResponse
    {
        $data = $request->validate([
            'endpoint' => ['required', 'string', 'url', 'max:2048'],
        ]);

        PushSubscription::query()
            ->where('endpoint_hash', PushSubscription::hashEndpoint((string) $data['endpoint']))
            ->delete();

        return response()->json(['ok' => true]);
    }
}
