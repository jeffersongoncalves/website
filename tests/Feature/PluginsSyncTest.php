<?php

declare(strict_types=1);

use App\Jobs\ImportGithubRepoJob;
use App\Jobs\SyncPluginsJsonJob;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Bus;
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
            ],
            'cakephp' => [
                ['title' => 'CakePHP Analyzer', 'package' => 'jeffersonsimaogoncalves/cakephp-analyzer', 'repo' => 'jeffersongoncalves/cakephp-analyzer'],
            ],
        ], 200),
    ]);
    Bus::fake();

    (new SyncPluginsJsonJob)->handle();

    Bus::assertDispatched(ImportGithubRepoJob::class, fn (ImportGithubRepoJob $job) => $job->githubUrl === 'https://github.com/jeffersongoncalves/filakitv5' && $job->fallbackCategory === 'starter_kit');
    Bus::assertDispatched(ImportGithubRepoJob::class, fn (ImportGithubRepoJob $job) => $job->githubUrl === 'https://github.com/jeffersongoncalves/filament-ban' && $job->fallbackCategory === 'filament_plugin');
    // repo overrides package for the Composer-vendor-mismatch case.
    Bus::assertDispatched(ImportGithubRepoJob::class, fn (ImportGithubRepoJob $job) => $job->githubUrl === 'https://github.com/jeffersongoncalves/cakephp-analyzer' && $job->fallbackCategory === 'cakephp_package');
});
