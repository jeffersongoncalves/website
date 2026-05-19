<?php

namespace App\Console\Commands;

use App\Models\PushSubscription;
use Illuminate\Console\Command;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

class SendPushNotification extends Command
{
    protected $signature = 'push:send
        {--title= : Notification title (required)}
        {--body= : Notification body text}
        {--url=/ : URL the notification opens when clicked}
        {--tag=jg-push : Notification tag — same tag replaces previous}';

    protected $description = 'Broadcast a Web Push notification to every saved subscription.';

    public function handle(): int
    {
        $title = (string) $this->option('title');
        if ($title === '') {
            $this->error('--title is required.');

            return self::FAILURE;
        }

        $publicKey = config('services.webpush.public_key');
        $privateKey = config('services.webpush.private_key');
        $subject = config('services.webpush.subject');

        if (! is_string($publicKey) || ! is_string($privateKey)) {
            $this->error('VAPID keys are missing. Run `php artisan webpush:vapid` and set both VAPID_* env vars.');

            return self::FAILURE;
        }

        $payload = json_encode([
            'title' => $title,
            'body' => (string) $this->option('body'),
            'url' => (string) $this->option('url'),
            'tag' => (string) $this->option('tag'),
        ]);

        if ($payload === false) {
            $this->error('Failed to encode push payload as JSON.');

            return self::FAILURE;
        }

        $webPush = new WebPush([
            'VAPID' => [
                'subject' => is_string($subject) ? $subject : 'mailto:noreply@example.com',
                'publicKey' => $publicKey,
                'privateKey' => $privateKey,
            ],
        ]);

        $sent = 0;
        $expired = 0;

        PushSubscription::query()->chunkById(200, function ($subs) use ($webPush, $payload, &$sent) {
            foreach ($subs as $row) {
                $webPush->queueNotification(
                    Subscription::create([
                        'endpoint' => $row->endpoint,
                        'publicKey' => $row->p256dh,
                        'authToken' => $row->auth,
                    ]),
                    $payload,
                );
                $sent++;
            }
        });

        foreach ($webPush->flush() as $report) {
            // The Push Service tells us a subscription is permanently gone
            // (404 / 410). Drop those rows so we stop wasting bytes on them.
            if ($report->isSubscriptionExpired()) {
                PushSubscription::query()
                    ->where('endpoint_hash', PushSubscription::hashEndpoint($report->getEndpoint()))
                    ->delete();
                $expired++;
            }
        }

        $this->info("Queued {$sent} push(es). Pruned {$expired} expired subscription(s).");

        return self::SUCCESS;
    }
}
