<?php

namespace App\Console\Commands;

use App\Support\PushBroadcaster;
use Illuminate\Console\Command;

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

        $result = PushBroadcaster::send(
            $title,
            (string) $this->option('body'),
            (string) $this->option('url'),
            (string) $this->option('tag'),
        );

        if (! $result['configured']) {
            $this->error('VAPID keys are missing. Run `php artisan webpush:vapid` and set both VAPID_* env vars.');

            return self::FAILURE;
        }

        if ($result['queued'] === 0) {
            $this->warn('No push subscriptions in the database — nothing to send.');
            $this->line('Make sure a visitor clicked the bell button + granted permission first.');

            return self::SUCCESS;
        }

        $this->info(sprintf(
            'Delivered: %d · Expired (pruned): %d · Failed: %d · Queued: %d',
            $result['delivered'],
            $result['expired'],
            $result['failed'],
            $result['queued'],
        ));

        return $result['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
