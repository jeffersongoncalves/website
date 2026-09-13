<?php

declare(strict_types=1);

use App\Jobs\ImportGithubRepoJob;
use App\Jobs\SyncPluginsJsonJob;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

it('rejects a request with no or a wrong bearer token', function () {
    config(['services.plugins_sync.token' => 'secret-token']);

    $this->postJson('/api/plugins-sync')->assertUnauthorized();
    $this->postJson('/api/plugins-sync', [], ['Authorization' => 'Bearer wrong'])->assertUnauthorized();
});

it('queues the sync job for a valid bearer token', function () {
    config(['services.plugins_sync.token' => 'secret-token']);
    Queue::fake();

    $this->postJson('/api/plugins-sync', [], ['Authorization' => 'Bearer secret-token'])
        ->assertStatus(202);

    Queue::assertPushed(SyncPluginsJsonJob::class);
});

it('always rejects when no token is configured, even with an empty header', function () {
    config(['services.plugins_sync.token' => null]);

    $this->postJson('/api/plugins-sync', [], ['Authorization' => 'Bearer '])->assertUnauthorized();
});

it('fans out an ImportGithubRepoJob per plugins.json entry, following repo overrides and nested groups', function () {
    // The global beforeEach fake (tests/Pest.php) already stubs
    // raw.githubusercontent.com/* with a '# README' fixture, and the first
    // matching stub wins — swap in a fresh factory so this test's fake is the
    // only one in play.
    Http::swap(new Factory);
    Http::fake([
        'raw.githubusercontent.com/*' => Http::response([
            'startkit' => [
                'featured' => [
                    ['title' => 'Fila Kit v5', 'package' => 'jeffersongoncalves/filakitv5'],
                ],
            ],
            'filament' => [
                'plugins' => [
                    ['title' => 'Filament Ban', 'package' => 'jeffersongoncalves/filament-ban'],
                ],
                'collaborator' => [
                    ['title' => 'Filament Activity Log', 'package' => 'rmsramos/activitylog'],
                ],
            ],
            'cakephp' => [
                ['title' => 'CakePHP Analyzer', 'package' => 'jeffersonsimaogoncalves/cakephp-analyzer', 'repo' => 'jeffersongoncalves/cakephp-analyzer'],
            ],
        ], 200),
    ]);
    Bus::fake();

    (new SyncPluginsJsonJob)->handle();

    Bus::assertDispatched(ImportGithubRepoJob::class, fn (ImportGithubRepoJob $job) => $job->githubUrl === 'https://github.com/jeffersongoncalves/filakitv5' && $job->fallbackCategory === 'starter_kit' && $job->isMaintainer === false);
    Bus::assertDispatched(ImportGithubRepoJob::class, fn (ImportGithubRepoJob $job) => $job->githubUrl === 'https://github.com/jeffersongoncalves/filament-ban' && $job->fallbackCategory === 'filament_plugin' && $job->isMaintainer === false);
    // repo overrides package for the Composer-vendor-mismatch case.
    Bus::assertDispatched(ImportGithubRepoJob::class, fn (ImportGithubRepoJob $job) => $job->githubUrl === 'https://github.com/jeffersongoncalves/cakephp-analyzer' && $job->fallbackCategory === 'cakephp_package' && $job->isMaintainer === false);
    // filament.collaborator entries are repos Jefferson maintains but doesn't own.
    Bus::assertDispatched(ImportGithubRepoJob::class, fn (ImportGithubRepoJob $job) => $job->githubUrl === 'https://github.com/rmsramos/activitylog' && $job->isMaintainer === true);
});

it('does not re-dispatch an entry already present in the last-synced snapshot', function () {
    Cache::forever('plugins-json:last-synced-slugs', [
        'jeffersongoncalves/filament-ban' => false,
    ]);

    Http::swap(new Factory);
    Http::fake([
        'raw.githubusercontent.com/*' => Http::response([
            'filament' => [
                'plugins' => [
                    ['title' => 'Filament Ban', 'package' => 'jeffersongoncalves/filament-ban'],
                ],
            ],
        ], 200),
    ]);
    Bus::fake();

    (new SyncPluginsJsonJob)->handle();

    Bus::assertNotDispatched(ImportGithubRepoJob::class);
});

it('re-dispatches an entry promoted to maintainer status even though its slug was already synced', function () {
    Cache::forever('plugins-json:last-synced-slugs', [
        'rmsramos/activitylog' => false,
    ]);

    Http::swap(new Factory);
    Http::fake([
        'raw.githubusercontent.com/*' => Http::response([
            'filament' => [
                'collaborator' => [
                    ['title' => 'Filament Activity Log', 'package' => 'rmsramos/activitylog'],
                ],
            ],
        ], 200),
    ]);
    Bus::fake();

    (new SyncPluginsJsonJob)->handle();

    Bus::assertDispatched(ImportGithubRepoJob::class, fn (ImportGithubRepoJob $job) => $job->githubUrl === 'https://github.com/rmsramos/activitylog' && $job->isMaintainer === true);
});

it('persists the current entry list as the new last-synced snapshot', function () {
    Http::swap(new Factory);
    Http::fake([
        'raw.githubusercontent.com/*' => Http::response([
            'filament' => [
                'plugins' => [
                    ['title' => 'Filament Ban', 'package' => 'jeffersongoncalves/filament-ban'],
                ],
            ],
        ], 200),
    ]);
    Bus::fake();

    (new SyncPluginsJsonJob)->handle();

    expect(Cache::get('plugins-json:last-synced-slugs'))
        ->toBe(['jeffersongoncalves/filament-ban' => false]);
});
