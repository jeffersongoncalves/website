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

        $total = PushSubscription::query()->count();

        if ($total === 0) {
            $this->warn('No push subscriptions in the database — nothing to send.');
            $this->line('Make sure a visitor clicked the bell button + granted permission first.');

            return self::SUCCESS;
        }

        $this->info("Queueing push to {$total} subscription(s)...");

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
            $endpointHost = parse_url($report->getEndpoint(), PHP_URL_HOST) ?: 'unknown';

            if ($report->isSuccess()) {
                $delivered++;
                $this->line("  <fg=green>ok</> {$endpointHost}");

                continue;
            }

            // The Push Service tells us a subscription is permanently gone
            // (404 / 410). Drop those rows so we stop wasting bytes on them.
            if ($report->isSubscriptionExpired()) {
                PushSubscription::query()
                    ->where('endpoint_hash', PushSubscription::hashEndpoint($report->getEndpoint()))
                    ->delete();
                $expired++;
                $this->line("  <fg=yellow>expired</> {$endpointHost} — subscription pruned");

                continue;
            }

            $failed++;
            $response = $report->getResponse();
            $status = $response !== null ? $response->getStatusCode() : 'n/a';
            $reason = $report->getReason();
            $this->line("  <fg=red>fail</> {$endpointHost} — HTTP {$status} · {$reason}");
        }

        $this->newLine();
        $this->info("Delivered: {$delivered} · Expired (pruned): {$expired} · Failed: {$failed} · Queued: {$queued}");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
