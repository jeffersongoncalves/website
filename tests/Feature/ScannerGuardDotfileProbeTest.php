<?php

declare(strict_types=1);

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

it('does not ban visitors loading relative .github README images', function (): void {
    foreach (range(1, 5) as $i) {
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
            ->get("/es/projects/.github/readme/image-{$i}.png");
    }

    expect(ScannerGuardBan::query()->count())->toBe(0);
});
