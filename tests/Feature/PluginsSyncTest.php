<?php

declare(strict_types=1);

use App\Jobs\ImportGithubRepoJob;
use App\Jobs\SyncPluginsJsonJob;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

const PLUGINS_JSON_URL = 'https://raw.githubusercontent.com/jeffersongoncalves/jeffersongoncalves/master/plugins.json';
const OWNER_PACKAGES_URL = 'https://raw.githubusercontent.com/jeffersongoncalves/jeffersongoncalves/master/plugins-packages-owner.json';
const COLLABORATOR_PACKAGES_URL = 'https://raw.githubusercontent.com/jeffersongoncalves/jeffersongoncalves/master/plugins-packages-collaborator.json';

/**
 * @param  array<int, string>  $owner
 * @param  array<int, string>  $collaborator
 * @param  array<string, mixed>  $pluginsJson
 */
function fakePluginsSyncSources(array $owner, array $collaborator, array $pluginsJson = []): void
{
    // The global beforeEach fake (tests/Pest.php) already stubs
    // raw.githubusercontent.com/* with a '# README' fixture, and the first
    // matching stub wins — swap in a fresh factory so this test's fakes
    // (exact URLs, not a wildcard, since the three endpoints must each
    // return a different body) are the only ones in play.
    Http::swap(new Factory);
    Http::fake([
        PLUGINS_JSON_URL => Http::response($pluginsJson, 200),
        OWNER_PACKAGES_URL => Http::response($owner, 200),
        COLLABORATOR_PACKAGES_URL => Http::response($collaborator, 200),
    ]);
}

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

it('dispatches an ImportGithubRepoJob for every owner/collaborator entry on a first run (no stored snapshot yet)', function () {
    Storage::fake('local');
    fakePluginsSyncSources(
        owner: ['jeffersongoncalves/filakitv5', 'jeffersongoncalves/filament-ban'],
        collaborator: ['rmsramos/activitylog'],
        pluginsJson: [
            'startkit' => ['featured' => [['title' => 'Fila Kit v5', 'package' => 'jeffersongoncalves/filakitv5']]],
            'filament' => [
                'plugins' => [['title' => 'Filament Ban', 'package' => 'jeffersongoncalves/filament-ban']],
                'collaborator' => [['title' => 'Filament Activity Log', 'package' => 'rmsramos/activitylog']],
            ],
        ],
    );
    Bus::fake();

    (new SyncPluginsJsonJob)->handle();

    Bus::assertDispatched(ImportGithubRepoJob::class, fn (ImportGithubRepoJob $job) => $job->githubUrl === 'https://github.com/jeffersongoncalves/filakitv5' && $job->fallbackCategory === 'starter_kit' && $job->isMaintainer === false);
    Bus::assertDispatched(ImportGithubRepoJob::class, fn (ImportGithubRepoJob $job) => $job->githubUrl === 'https://github.com/jeffersongoncalves/filament-ban' && $job->fallbackCategory === 'filament_plugin' && $job->isMaintainer === false);
    // Collaborator-list entries dispatch with isMaintainer = true regardless
    // of plugins.json structure — membership in that file is authoritative.
    Bus::assertDispatched(ImportGithubRepoJob::class, fn (ImportGithubRepoJob $job) => $job->githubUrl === 'https://github.com/rmsramos/activitylog' && $job->fallbackCategory === 'filament_plugin' && $job->isMaintainer === true);
});

it('does not re-dispatch an entry already present in the stored snapshot', function () {
    Storage::fake('local');
    Storage::disk('local')->put('plugins-sync/owner.json', json_encode(['jeffersongoncalves/filament-ban']));
    Storage::disk('local')->put('plugins-sync/collaborator.json', json_encode([]));

    fakePluginsSyncSources(
        owner: ['jeffersongoncalves/filament-ban'],
        collaborator: [],
    );
    Bus::fake();

    (new SyncPluginsJsonJob)->handle();

    Bus::assertNotDispatched(ImportGithubRepoJob::class);
});

it('dispatches only the newly-added slug when the rest were already synced', function () {
    Storage::fake('local');
    Storage::disk('local')->put('plugins-sync/owner.json', json_encode(['jeffersongoncalves/filament-ban']));
    Storage::disk('local')->put('plugins-sync/collaborator.json', json_encode([]));

    fakePluginsSyncSources(
        owner: ['jeffersongoncalves/filament-ban', 'jeffersongoncalves/filakitv5'],
        collaborator: [],
        pluginsJson: ['startkit' => ['featured' => [['title' => 'Fila Kit v5', 'package' => 'jeffersongoncalves/filakitv5']]]],
    );
    Bus::fake();

    (new SyncPluginsJsonJob)->handle();

    Bus::assertDispatchedTimes(ImportGithubRepoJob::class, 1);
    Bus::assertDispatched(ImportGithubRepoJob::class, fn (ImportGithubRepoJob $job) => $job->githubUrl === 'https://github.com/jeffersongoncalves/filakitv5' && $job->fallbackCategory === 'starter_kit');
});

it('re-dispatches a slug that moved from the owner list into the collaborator list', function () {
    Storage::fake('local');
    Storage::disk('local')->put('plugins-sync/owner.json', json_encode(['rmsramos/activitylog']));
    Storage::disk('local')->put('plugins-sync/collaborator.json', json_encode([]));

    // rmsramos/activitylog moved out of owner.json and into collaborator.json.
    fakePluginsSyncSources(
        owner: [],
        collaborator: ['rmsramos/activitylog'],
    );
    Bus::fake();

    (new SyncPluginsJsonJob)->handle();

    Bus::assertDispatched(ImportGithubRepoJob::class, fn (ImportGithubRepoJob $job) => $job->githubUrl === 'https://github.com/rmsramos/activitylog' && $job->isMaintainer === true);
});

it('falls back to a generic category when a slug is missing from plugins.json', function () {
    Storage::fake('local');
    fakePluginsSyncSources(
        owner: ['someowner/unlisted-repo'],
        collaborator: [],
        pluginsJson: [],
    );
    Bus::fake();

    (new SyncPluginsJsonJob)->handle();

    Bus::assertDispatched(ImportGithubRepoJob::class, fn (ImportGithubRepoJob $job) => $job->githubUrl === 'https://github.com/someowner/unlisted-repo' && $job->fallbackCategory === 'awesome_list');
});

it('logs a warning and skips the diff when the owner/collaborator list fetch fails', function () {
    Storage::fake('local');
    Http::swap(new Factory);
    Http::fake([
        PLUGINS_JSON_URL => Http::response([], 200),
        OWNER_PACKAGES_URL => Http::response('server error', 500),
        COLLABORATOR_PACKAGES_URL => Http::response([], 200),
    ]);
    Bus::fake();

    (new SyncPluginsJsonJob)->handle();

    Bus::assertNotDispatched(ImportGithubRepoJob::class);
    Storage::disk('local')->assertMissing('plugins-sync/owner.json');
});

it('logs a warning and skips the diff when the owner/collaborator list does not decode to an array', function () {
    Storage::fake('local');
    Http::swap(new Factory);
    Http::fake([
        PLUGINS_JSON_URL => Http::response([], 200),
        OWNER_PACKAGES_URL => Http::response('not json', 200),
        COLLABORATOR_PACKAGES_URL => Http::response([], 200),
    ]);
    Bus::fake();

    (new SyncPluginsJsonJob)->handle();

    Bus::assertNotDispatched(ImportGithubRepoJob::class);
    Storage::disk('local')->assertMissing('plugins-sync/owner.json');
});

it('still diffs and dispatches with a fallback category when plugins.json fetch fails', function () {
    Storage::fake('local');
    Http::swap(new Factory);
    Http::fake([
        PLUGINS_JSON_URL => Http::response('server error', 500),
        OWNER_PACKAGES_URL => Http::response(['someowner/unlisted-repo'], 200),
        COLLABORATOR_PACKAGES_URL => Http::response([], 200),
    ]);
    Bus::fake();

    (new SyncPluginsJsonJob)->handle();

    Bus::assertDispatched(ImportGithubRepoJob::class, fn (ImportGithubRepoJob $job) => $job->githubUrl === 'https://github.com/someowner/unlisted-repo' && $job->fallbackCategory === 'awesome_list');
});

it('still diffs and dispatches with a fallback category when plugins.json does not decode to an array', function () {
    Storage::fake('local');
    Http::swap(new Factory);
    Http::fake([
        PLUGINS_JSON_URL => Http::response('not json', 200),
        OWNER_PACKAGES_URL => Http::response(['someowner/unlisted-repo'], 200),
        COLLABORATOR_PACKAGES_URL => Http::response([], 200),
    ]);
    Bus::fake();

    (new SyncPluginsJsonJob)->handle();

    Bus::assertDispatched(ImportGithubRepoJob::class, fn (ImportGithubRepoJob $job) => $job->githubUrl === 'https://github.com/someowner/unlisted-repo' && $job->fallbackCategory === 'awesome_list');
});

it('ignores a malformed non-array group in plugins.json instead of throwing', function () {
    Storage::fake('local');
    fakePluginsSyncSources(
        owner: ['jeffersongoncalves/filament-ban'],
        collaborator: [],
        pluginsJson: ['startkit' => 'this-should-be-an-object-not-a-string'],
    );
    Bus::fake();

    (new SyncPluginsJsonJob)->handle();

    Bus::assertDispatched(ImportGithubRepoJob::class, fn (ImportGithubRepoJob $job) => $job->githubUrl === 'https://github.com/jeffersongoncalves/filament-ban' && $job->fallbackCategory === 'awesome_list');
});

it('persists the fetched lists to storage for the next run\'s diff', function () {
    Storage::fake('local');
    fakePluginsSyncSources(
        owner: ['jeffersongoncalves/filament-ban'],
        collaborator: ['rmsramos/activitylog'],
    );
    Bus::fake();

    (new SyncPluginsJsonJob)->handle();

    Storage::disk('local')->assertExists('plugins-sync/owner.json');
    Storage::disk('local')->assertExists('plugins-sync/collaborator.json');
    expect(json_decode(Storage::disk('local')->get('plugins-sync/owner.json'), true))
        ->toBe(['jeffersongoncalves/filament-ban']);
    expect(json_decode(Storage::disk('local')->get('plugins-sync/collaborator.json'), true))
        ->toBe(['rmsramos/activitylog']);
});
