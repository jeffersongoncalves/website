<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use JeffersonGoncalves\ScannerGuard\Jobs\SyncDenylist;
use JeffersonGoncalves\ScannerGuard\Models\ScannerGuardBan;

// nginx forwards dotfile probes to index.php (forge/nginx-*.conf) so they
// reach BlockScannerRequests through the short-url Route::fallback().
it('bans an ip that probes for .env and .git files, nested ones included', function (): void {
    $probe = fn (string $path) => $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])->get($path);

    $probe('/laravel/.env');
    $probe('/.git/config');
    $probe('/api/v1/.env.production')->assertNotFound();

    expect(ScannerGuardBan::query()->count())->toBe(1);

    $probe('/en')->assertNotFound();
});

it('writes banned ips to the nginx denylist', function (): void {
    $path = 'storage/framework/testing/scanner-guard/denylist.conf';
    config()->set('scanner-guard-nginx.path', $path);
    config()->set('scanner-guard-nginx.reload_command', null);

    foreach (['/laravel/.env', '/.git/config', '/.env'] as $probe) {
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.11'])->get($probe);
    }

    Queue::assertPushed(SyncDenylist::class);

    $this->artisan('scanner-guard:sync-denylist')->assertSuccessful();

    expect(File::get(base_path($path)))->toContain('deny 203.0.113.11; # scanner_path');

    File::deleteDirectory(dirname(base_path($path)));
});

it('does not ban visitors loading relative .github README images', function (): void {
    foreach (range(1, 5) as $i) {
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
            ->get("/es/projects/.github/readme/image-{$i}.png");
    }

    expect(ScannerGuardBan::query()->count())->toBe(0);
});
