<?php

namespace App\Filament\Admin\Resources\Projects\Pages;

use App\Enums\ProjectStatus;
use App\Filament\Admin\Resources\Projects\ProjectResource;
use App\Models\Project;
use App\Support\GithubReadme;
use App\Support\ProjectImporter;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListProjects extends ListRecords
{
    protected static string $resource = ProjectResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->quickCreateAction(),
            CreateAction::make(),
        ];
    }

    /**
     * One-shot modal: pick source (GitHub / npm / URL), paste URL, set status
     * + daily-driver flag, persist. Skips the full create form for the common
     * case where importer fields are good enough as-is.
     */
    protected function quickCreateAction(): Action
    {
        return Action::make('quickCreate')
            ->label(__('admin.actions.quick_create'))
            ->icon('heroicon-o-bolt')
            ->color('success')
            ->modalHeading(__('admin.actions.quick_create'))
            ->modalDescription(__('admin.actions.quick_create_help'))
            ->modalSubmitActionLabel(__('admin.actions.import'))
            ->schema([
                Select::make('source')
                    ->label(__('admin.fields.import_source'))
                    ->options([
                        'github' => 'GitHub',
                        'npm' => 'npm',
                        'youtube' => 'YouTube',
                        'url' => 'URL',
                    ])
                    ->default('github')
                    ->required(),
                TextInput::make('url')
                    ->label(__('admin.fields.import_url'))
                    ->placeholder('https://github.com/owner/repo')
                    ->url()
                    ->required(),
                Select::make('status')
                    ->label(__('admin.fields.status'))
                    ->options(ProjectStatus::class)
                    ->default(ProjectStatus::Draft)
                    ->required(),
                Toggle::make('is_daily_driver')
                    ->label(__('admin.fields.is_daily_driver'))
                    ->helperText(__('admin.helpers.is_daily_driver')),
            ])
            ->action(function (array $data): void {
                $result = match ($data['source']) {
                    'github' => ProjectImporter::fromGithub($data['url']),
                    'npm' => ProjectImporter::fromNpm($data['url']),
                    'youtube' => ProjectImporter::fromYoutube($data['url']),
                    default => ProjectImporter::fromUrl($data['url']),
                };

                if (isset($result['error'])) {
                    Notification::make()
                        ->title(__('admin.import.error.'.$result['error']))
                        ->danger()
                        ->send();

                    return;
                }

                $fields = $result['fields'] ?? [];
                $attributes = self::normalizeImportedFields($fields);
                // Defer to HasSlug — drop the importer's slug so duplicates
                // resolve with `-1`/`-2` instead of hitting a unique
                // constraint mid-create.
                unset($attributes['slug']);

                $existing = self::findExistingProject($data['source'], $attributes);

                if ($existing !== null) {
                    $changes = self::fillMissingAttributes($existing, $attributes);
                    $existing->save();

                    $lines = [__('admin.import.updated_changes', ['count' => count($changes)])];
                    foreach ($result['warnings'] ?? [] as $warning) {
                        $lines[] = __('admin.import.warning.'.$warning);
                    }

                    Notification::make()
                        ->title(__('admin.import.updated', ['name' => $existing->name]))
                        ->body(implode("\n", $lines))
                        ->success()
                        ->send();

                    return;
                }

                $attributes['status'] = $data['status'];
                $attributes['is_daily_driver'] = (bool) ($data['is_daily_driver'] ?? false);

                $project = Project::create($attributes);

                $lines = [];
                foreach ($result['warnings'] ?? [] as $warning) {
                    $lines[] = __('admin.import.warning.'.$warning);
                }

                Notification::make()
                    ->title(__('admin.import.created', ['name' => $project->name]))
                    ->body($lines === [] ? null : implode("\n", $lines))
                    ->success()
                    ->send();
            });
    }

    /**
     * Locate a project that already represents the source being imported, so a
     * second pass over the same repo/package updates the row instead of
     * creating a duplicate (and a `-1` slug suffix).
     *
     * @param  array<string, mixed>  $attrs
     */
    private static function findExistingProject(string $source, array $attrs): ?Project
    {
        if ($source === 'github') {
            return self::findByGithubUrl(self::stringOrNull($attrs['github_url'] ?? null));
        }

        if ($source === 'npm') {
            $npmUrl = self::stringOrNull($attrs['npm_url'] ?? null);
            if ($npmUrl !== null) {
                $byNpm = Project::query()->where('npm_url', $npmUrl)->first();
                if ($byNpm !== null) {
                    return $byNpm;
                }
            }

            // Fallback: npm import for a repo already cadastrado via GitHub.
            // Only match against root repos — a `/tree/branch/dir` URL means
            // the npm package is a monorepo subtree and is a distinct project
            // from the repo root.
            $githubUrl = self::stringOrNull($attrs['github_url'] ?? null);
            if ($githubUrl !== null && ! str_contains($githubUrl, '/tree/')) {
                return self::findByGithubUrl($githubUrl);
            }

            return null;
        }

        // youtube + url imports both populate docs_url with the canonical link.
        $docsUrl = self::stringOrNull($attrs['docs_url'] ?? null);
        if ($docsUrl !== null) {
            return Project::query()->where('docs_url', $docsUrl)->first();
        }

        return null;
    }

    private static function findByGithubUrl(?string $url): ?Project
    {
        $slug = GithubReadme::repoFromUrl($url);

        if ($slug === null) {
            return null;
        }

        return Project::query()
            ->whereNotNull('github_url')
            ->get()
            ->first(fn (Project $p) => GithubReadme::repoFromUrl($p->github_url) === $slug);
    }

    /**
     * Copy importer attributes onto an existing project, but only for fields
     * the editor hasn't already populated. Mirrors HasImportFromGithubAction's
     * "fill empty, skip the rest" rule so manual edits survive a re-import.
     *
     * @param  array<string, mixed>  $attrs
     * @return list<string>
     */
    private static function fillMissingAttributes(Project $project, array $attrs): array
    {
        $changes = [];

        foreach ($attrs as $key => $value) {
            if ($key === 'slug') {
                continue;
            }

            $current = $project->getAttribute($key);

            if (! self::isEmptyValue($current)) {
                continue;
            }

            $project->setAttribute($key, $value);
            $changes[] = $key;
        }

        return $changes;
    }

    private static function isEmptyValue(mixed $value): bool
    {
        return $value === null || $value === '' || $value === [];
    }

    private static function stringOrNull(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * Importer returns translatable fields as dotted keys (`title.pt`,
     * `title.en`, ...). Mass assignment to a HasTranslations model expects the
     * translatable column as a nested array, so collapse the dotted keys back
     * into one before handing off to Project::create.
     *
     * @param  array<string, mixed>  $fields
     * @return array<string, mixed>
     */
    private static function normalizeImportedFields(array $fields): array
    {
        $attributes = [];

        foreach ($fields as $key => $value) {
            if ($value === null || $value === '' || $value === []) {
                continue;
            }

            if (str_contains($key, '.')) {
                [$column, $locale] = explode('.', $key, 2);
                $attributes[$column][$locale] = $value;

                continue;
            }

            $attributes[$key] = $value;
        }

        return $attributes;
    }
}
