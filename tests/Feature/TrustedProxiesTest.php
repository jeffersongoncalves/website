<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('reads the visitor IP from the forwarded header when the peer is Cloudflare', function () {
    // Without this, every visitor looks like the Cloudflare edge: throttling
    // collapses into one global bucket and short-url visit tracking hashes the
    // same "visitor" for all traffic.
    $this->call('GET', '/', server: [
        'REMOTE_ADDR' => '162.158.1.1',          // inside 162.158.0.0/15
        'HTTP_X_FORWARDED_FOR' => '203.0.113.9',
    ])->assertOk();

    expect(request()->ip())->toBe('203.0.113.9');
});

it('ignores a forwarded header from a peer that is not Cloudflare', function () {
    // The origin answers on its own address too, so an unproxied caller must
    // not be able to forge whatever visitor IP it likes.
    $this->call('GET', '/', server: [
        'REMOTE_ADDR' => '198.51.100.7',         // not a Cloudflare range
        'HTTP_X_FORWARDED_FOR' => '203.0.113.9',
    ])->assertOk();

    expect(request()->ip())->toBe('198.51.100.7');
});
