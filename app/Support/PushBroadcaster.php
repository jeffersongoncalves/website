<?php

namespace App\Support;

use App\Models\PushSubscription;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

class PushBroadcaster
{
    /**
     * Broadcast a Web Push notification to every saved subscription.
     * Shared by the `push:send` command and the
     * `SendProjectPublishedNotification` job so both run the exact same
     * VAPID setup, batching and expired-subscription pruning.
     *
     * @return array{configured: bool, queued: int, delivered: int, expired: int, failed: int}
     */
    public static function send(string $title, string $body = '', string $url = '/', string $tag = 'jg-push'): array
    {
        $publicKey = config('services.webpush.public_key');
        $privateKey = config('services.webpush.private_key');
        $subject = config('services.webpush.subject');

        if (! is_string($publicKey) || $publicKey === '' || ! is_string($privateKey) || $privateKey === '') {
            return ['configured' => false, 'queued' => 0, 'delivered' => 0, 'expired' => 0, 'failed' => 0];
        }

        $payload = json_encode([
            'title' => $title,
            'body' => $body,
            'url' => $url,
            'tag' => $tag,
        ]);

        if ($payload === false) {
            return ['configured' => true, 'queued' => 0, 'delivered' => 0, 'expired' => 0, 'failed' => 0];
        }

        $webPush = new WebPush([
            'VAPID' => [
                'subject' => is_string($subject) ? $subject : 'mailto:noreply@example.com',
                'publicKey' => $publicKey,
                'privateKey' => $privateKey,
            ],
        ]);

        $queued = 0;

        PushSubscription::query()->chunkById(200, function ($subs) use ($webPush, $payload, &$queued) {
            foreach ($subs as $row) {
                $webPush->queueNotification(
                    Subscription::create([
                        'endpoint' => $row->endpoint,
                        'publicKey' => $row->p256dh,
                        'authToken' => $row->auth,
                    ]),
                    $payload,
                );
                $queued++;
            }
        });

        $delivered = 0;
        $expired = 0;
        $failed = 0;

        foreach ($webPush->flush() as $report) {
            if ($report->isSuccess()) {
                $delivered++;

                continue;
            }

            // 404 / 410 — the Push Service says this subscription is gone
            // for good. Prune it so we stop paying to encrypt for a dead
            // endpoint on every future broadcast.
            if ($report->isSubscriptionExpired()) {
                PushSubscription::query()
                    ->where('endpoint_hash', PushSubscription::hashEndpoint($report->getEndpoint()))
                    ->delete();
                $expired++;

                continue;
            }

            $failed++;
        }

        return [
            'configured' => true,
            'queued' => $queued,
            'delivered' => $delivered,
            'expired' => $expired,
            'failed' => $failed,
        ];
    }
}
