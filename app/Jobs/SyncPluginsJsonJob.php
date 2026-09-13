<?php

declare(strict_types=1);

namespace App\Jobs;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Fans out one ImportGithubRepoJob per newly-added entry in
 * jeffersongoncalves/jeffersongoncalves's plugin catalog — dispatched by
 * POST /api/plugins-sync, which the source repo's notify-site-plugins-sync
 * workflow calls whenever plugins.json changes.
 *
 * Diffs against a local copy kept in storage/app/plugins-sync/ (survives an
 * atomic deploy's release swap, unlike public/) rather than re-dispatching
 * all ~250+ catalog entries on every run: ImportGithubRepoJob already no-ops
 * on an already-cadastrado repo (a cheap DB pre-check), but each dispatch
 * still reserves GitHub-quota budget up front (GithubQuota::reserve in
 * ImportGithubRepoJob::make()), so the dispatch volume itself is the cost
 * worth avoiding, not just the DB check.
 *
 * The diff runs against plugins-packages-owner.json/
 * plugins-packages-collaborator.json — flat, sorted "owner/repo" string
 * lists the source repo generates (extract-plugin-packages.js, run from its
 * pre-commit hook) from the same nested plugins.json using identical
 * flatten/collectEntries logic. Diffing these instead of the full nested
 * plugins.json means a GitHub-side reviewer sees exactly which packages
 * were added/removed in a plain one-line-per-entry diff, and this job's own
 * diff is a trivial array_diff instead of re-deriving structure.
 *
 * plugins.json itself is still fetched once per run, purely to build a
 * slug => fallback-category lookup (the flat files don't carry category) —
 * see flatten()/collectEntries(), unchanged from before this diffing was
 * added. A category reclassification for an EXISTING project was never
 * applied by this job even before — ImportGithubRepoJob::handle() only
 * fills missing fields on an existing row, never overwrites `category` — so
 * skipping unchanged entries here doesn't drop any capability the
 * dispatch-everything approach actually had.
 *
 * See DebouncedJob for how the debounce itself works (a source push firing
 * the webhook twice in quick succession collapses to one scan).
 */
class SyncPluginsJsonJob extends DebouncedJob
{
    private const STORAGE_DIR = 'plugins-sync';

    protected int $expireAfter = 180;

    public function __construct()
    {
        $this->onQueue('github')->onConnection('redis-github');
    }

    public function uniqueId(): string
    {
        return 'plugins-json:sync';
    }

    public function handle(): void
    {
        $categoryBySlug = $this->fetchCategoryMap();

        $this->diffAndDispatch(
            url: (string) config('services.plugins_sync.owner_packages_url'),
            storageFile: 'owner.json',
            isMaintainer: false,
            categoryBySlug: $categoryBySlug,
        );

        $this->diffAndDispatch(
            url: (string) config('services.plugins_sync.collaborator_packages_url'),
            storageFile: 'collaborator.json',
            isMaintainer: true,
            categoryBySlug: $categoryBySlug,
        );
    }

    /**
     * @param  array<string, string>  $categoryBySlug
     */
    private function diffAndDispatch(string $url, string $storageFile, bool $isMaintainer, array $categoryBySlug): void
    {
        $current = $this->fetchSlugList($url);

        if ($current === null) {
            return;
        }

        $path = self::STORAGE_DIR.'/'.$storageFile;
        $previous = $this->readStoredList($path);

        $added = array_diff($current, $previous);
        $removed = array_diff($previous, $current);

        foreach ($added as $slug) {
            $category = $categoryBySlug[$slug] ?? 'awesome_list';
            ImportGithubRepoJob::enqueue("https://github.com/{$slug}", $category, $isMaintainer);
        }

        if ($removed !== []) {
            Log::info('SyncPluginsJsonJob: entries removed since last sync', [
                'file' => $storageFile,
                'slugs' => array_values($removed),
            ]);
        }

        Storage::put($path, json_encode($current, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    }

    /**
     * @return list<string>|null null on a fetch/decode failure (already logged)
     */
    private function fetchSlugList(string $url): ?array
    {
        $response = Http::timeout(15)->get($url);

        if (! $response->successful()) {
            Log::warning('SyncPluginsJsonJob: failed to fetch package list', [
                'url' => $url,
                'status' => $response->status(),
            ]);

            return null;
        }

        $data = $response->json();

        if (! is_array($data)) {
            Log::warning('SyncPluginsJsonJob: package list did not decode to an array', ['url' => $url]);

            return null;
        }

        return array_values(array_filter($data, is_string(...)));
    }

    /**
     * @return list<string>
     */
    private function readStoredList(string $path): array
    {
        if (! Storage::exists($path)) {
            return [];
        }

        $data = json_decode(Storage::get($path), true);

        return is_array($data) ? array_values(array_filter($data, is_string(...))) : [];
    }

    /**
     * Fetches the nested plugins.json purely to map slug => fallback
     * category — the flat owner/collaborator files used for the actual
     * added/removed diff don't carry category. Returns [] on any
     * fetch/decode failure (already logged by the same warnings as before)
     * rather than aborting the sync — a missing category just falls back to
     * ImportGithubRepoJob's own default for any newly-added slug.
     *
     * @return array<string, string>
     */
    private function fetchCategoryMap(): array
    {
        $url = (string) config('services.plugins_sync.source_url');

        $response = Http::timeout(15)->get($url);

        if (! $response->successful()) {
            Log::warning('SyncPluginsJsonJob: failed to fetch plugins.json', [
                'url' => $url,
                'status' => $response->status(),
            ]);

            return [];
        }

        $data = $response->json();

        if (! is_array($data)) {
            Log::warning('SyncPluginsJsonJob: plugins.json did not decode to an array', ['url' => $url]);

            return [];
        }

        $map = [];
        foreach ($this->flatten($data) as [$slug, $category]) {
            $map[$slug] = $category;
        }

        return $map;
    }

    /**
     * Walk every category in plugins.json (including the nested
     * startkit.legacy.{v3,v4,...} and filament.{plugins,collaborator} groups)
     * and yield [owner/repo, fallback category] tuples. `repo` overrides
     * `package` when a listing's Composer vendor differs from its GitHub
     * owner (e.g. the CakePHP packages, published under
     * jeffersonsimaogoncalves but hosted under jeffersongoncalves).
     *
     * @param  array<string, mixed>  $data
     * @return list<array{0: string, 1: string}>
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
     * @return list<array{0: string, 1: string}>
     */
    private function collectEntries(mixed $node, string $fallback): array
    {
        if (! is_array($node)) {
            return [];
        }

        // A plugin/package entry: {"title": ..., "package": "vendor/repo"[, "repo": "owner/repo"]}.
        if (isset($node['package']) && is_string($node['package'])) {
            $slug = is_string($node['repo'] ?? null) ? $node['repo'] : $node['package'];

            return [[$slug, $fallback]];
        }

        // A list of entries, or a nested group (legacy.v3/v4, plugins/collaborator, …).
        $out = [];
        foreach ($node as $child) {
            $out = [...$out, ...$this->collectEntries($child, $fallback)];
        }

        return $out;
    }
}
