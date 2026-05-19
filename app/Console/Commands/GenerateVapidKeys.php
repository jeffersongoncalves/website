<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Minishlink\WebPush\VAPID;
use Throwable;

class GenerateVapidKeys extends Command
{
    protected $signature = 'webpush:vapid';

    protected $description = 'Generate a new VAPID keypair and print the .env lines to copy.';

    public function handle(): int
    {
        try {
            $keys = VAPID::createVapidKeys();
        } catch (Throwable $e) {
            $this->error('Failed to generate VAPID keys: '.$e->getMessage());
            $this->line('Make sure the openssl extension is available with EC support enabled.');

            return self::FAILURE;
        }

        $this->info('Copy the lines below into your .env file:');
        $this->newLine();
        $this->line('VAPID_PUBLIC_KEY='.$keys['publicKey']);
        $this->line('VAPID_PRIVATE_KEY='.$keys['privateKey']);
        $this->newLine();
        $this->line('Restart php-fpm / queue workers so the new keys take effect.');

        return self::SUCCESS;
    }
}
