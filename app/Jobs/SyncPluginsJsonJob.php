<?php

declare(strict_types=1);

namespace App\Jobs;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Re-scans jeffersongoncalves/jeffersongoncalves's plugins.json (the catalog
 * driving that repo's profile README) and fans out one ImportGithubRepoJob
 * per entry — dispatched by POST /api/plugins-sync, which the source repo's
 * notify-site-plugins-sync workflow calls whenever plugins.json changes.
 *
 * Does no diffing itself: ImportGithubRepoJob already no-ops on a repo that's
 * already cadastrado (a cheap DB pre-check, no GitHub call), so dispatching
 * for every entry on every run is the simplest correct way to "find what's
 * new" — only the actually-new rows do any real work.
 *
 * See DebouncedJob for how the debounce itself works (a source push firing
 * the webhook twice in quick succession collapses to one scan).
 */
class SyncPluginsJsonJob extends DebouncedJob
{
    protected int $expireAfter = 180;

    public function __construct()
    {
        $this->onQueue('github');
    }

    public function uniqueId(): string
    {
        return 'plugins-json:sync';
    }

    public function handle(): void
    {
        $url = (string) config('services.plugins_sync.source_url');

        $response = Http::timeout(15)->get($url);

        if (! $response->successful()) {
            Log::warning('SyncPluginsJsonJob: failed to fetch plugins.json', [
                'url' => $url,
                'status' => $response->status(),
            ]);

            return;
        }

        $data = $response->json();

        if (! is_array($data)) {
            Log::warning('SyncPluginsJsonJob: plugins.json did not decode to an array', ['url' => $url]);

            return;
        }

        foreach ($this->flatten($data) as [$slug, $category, $isMaintainer]) {
            ImportGithubRepoJob::enqueue("https://github.com/{$slug}", $category, $isMaintainer);
        }
    }

    /**
     * Walk every category in plugins.json (including the nested
     * startkit.legacy.{v3,v4,...} and filament.{plugins,collaborator} groups)
     * and yield [owner/repo, fallback category, is_maintainer] tuples. `repo`
     * overrides `package` when a listing's Composer vendor differs from its
     * GitHub owner (e.g. the CakePHP packages, published under
     * jeffersonsimaogoncalves but hosted under jeffersongoncalves).
     *
     * @param  array<string, mixed>  $data
     * @return list<array{0: string, 1: string, 2: bool}>
     */
    private function flatten(array $data): array
    {
        $categoryFallbacks = [
            'startkit' => 'starter_kit',
            'filament' => 'filament_plugin',
            'laravel' => 'laravel_package',
            'laravelZero' => 'laravel_zero_cli',
            'cli' => 'tool',
            'cliPython' => 'tool',
            'jetbrains' => 'ide_plugin',
            'vscode' => 'ide_plugin',
            'browserExtensions' => 'tool',
            'obsidianPlugins' => 'tool',
            'claudeCodePlugins' => 'tool',
            'cakephp' => 'cakephp_package',
        ];

        $out = [];

        foreach ($data as $topKey => $value) {
            $fallback = $categoryFallbacks[$topKey] ?? 'tool';
            $out = [...$out, ...$this->collectEntries($value, $fallback)];
        }

        return $out;
    }

    /**
     * `$isMaintainer` starts false and flips true the moment recursion steps
     * into a node keyed "collaborator" (currently only filament.collaborator)
     * — every entry under it is a repo Jefferson actively maintains but
     * doesn't own, as opposed to the sibling "plugins" group he owns
     * outright. Generic on the key name rather than special-cased to
     * "filament" so a future `collaborator` group anywhere else in
     * plugins.json is picked up the same way without a code change here.
     *
     * @return list<array{0: string, 1: string, 2: bool}>
     */
    private function collectEntries(mixed $node, string $fallback, bool $isMaintainer = false): array
    {
        if (! is_array($node)) {
            return [];
        }

        // A plugin/package entry: {"title": ..., "package": "vendor/repo"[, "repo": "owner/repo"]}.
        if (isset($node['package']) && is_string($node['package'])) {
            $slug = is_string($node['repo'] ?? null) ? $node['repo'] : $node['package'];

            return [[$slug, $fallback, $isMaintainer]];
        }

        // A list of entries, or a nested group (legacy.v3/v4, plugins/collaborator, …).
        $out = [];
        foreach ($node as $key => $child) {
            $childIsMaintainer = $key === 'collaborator' ? true : $isMaintainer;
            $out = [...$out, ...$this->collectEntries($child, $fallback, $childIsMaintainer)];
        }

        return $out;
    }
}
