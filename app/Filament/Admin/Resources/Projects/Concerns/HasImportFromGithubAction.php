<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Projects\Concerns;

use App\Exceptions\GithubRateLimitException;
use App\Support\ProjectImporter;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;

trait HasImportFromGithubAction
{
    /**
     * All import flows grouped under a single "Importação" dropdown — one
     * entry per source type (GitHub, npm, generic URL).
     */
    protected function importActionGroup(): ActionGroup
    {
        return ActionGroup::make([
            $this->importFromGithubAction(),
            $this->importFromNpmAction(),
            $this->importFromYoutubeAction(),
            $this->importFromArticleAction(),
            $this->importFromUrlAction(),
        ])
            ->label(__('admin.actions.import_group'))
            ->icon('heroicon-o-arrow-down-tray')
            ->color('warning')
            ->button();
    }

    protected function importFromGithubAction(): Action
    {
        return Action::make('importFromGithub')
            ->label(__('admin.actions.import_from_github'))
            ->icon('heroicon-o-arrow-down-tray')
            ->color('warning')
            ->modalHeading(__('admin.actions.import_from_github'))
            ->modalDescription(__('admin.actions.import_from_github_help'))
            ->modalSubmitActionLabel(__('admin.actions.import'))
            ->schema([
                TextInput::make('github_url')
                    ->label(__('admin.fields.github_url'))
                    ->placeholder('https://github.com/owner/repo')
                    ->url()
                    ->required(),
            ])
            ->action(function (array $data): void {
                $this->runImport(fn () => ProjectImporter::fromGithub($data['github_url']));
            });
    }

    protected function importFromNpmAction(): Action
    {
        return Action::make('importFromNpm')
            ->label(__('admin.actions.import_from_npm'))
            ->icon('heroicon-o-cube')
            ->color('danger')
            ->modalHeading(__('admin.actions.import_from_npm'))
            ->modalDescription(__('admin.actions.import_from_npm_help'))
            ->modalSubmitActionLabel(__('admin.actions.import'))
            ->schema([
                TextInput::make('npm_url')
                    ->label(__('admin.fields.npm_url'))
                    ->placeholder('https://www.npmjs.com/package/@scope/name')
                    ->url()
                    ->required(),
            ])
            ->action(function (array $data): void {
                $this->runImport(fn () => ProjectImporter::fromNpm($data['npm_url']));
            });
    }

    protected function importFromYoutubeAction(): Action
    {
        return Action::make('importFromYoutube')
            ->label(__('admin.actions.import_from_youtube'))
            ->icon('heroicon-o-play-circle')
            ->color('danger')
            ->modalHeading(__('admin.actions.import_from_youtube'))
            ->modalDescription(__('admin.actions.import_from_youtube_help'))
            ->modalSubmitActionLabel(__('admin.actions.import'))
            ->schema([
                TextInput::make('youtube_url')
                    ->label(__('admin.fields.youtube_url'))
                    ->placeholder('https://www.youtube.com/@handle')
                    ->url()
                    ->required(),
            ])
            ->action(function (array $data): void {
                $this->runImport(fn () => ProjectImporter::fromYoutube($data['youtube_url']));
            });
    }

    protected function importFromArticleAction(): Action
    {
        return Action::make('importFromArticle')
            ->label(__('admin.actions.import_from_article'))
            ->icon('heroicon-o-document-text')
            ->color('info')
            ->modalHeading(__('admin.actions.import_from_article'))
            ->modalDescription(__('admin.actions.import_from_article_help'))
            ->modalSubmitActionLabel(__('admin.actions.import'))
            ->schema([
                TextInput::make('article_url')
                    ->label(__('admin.fields.article_url'))
                    ->placeholder('https://example.com/blog/post-title')
                    ->url()
                    ->required(),
            ])
            ->action(function (array $data): void {
                $this->runImport(fn () => ProjectImporter::fromArticle($data['article_url']));
            });
    }

    protected function importFromUrlAction(): Action
    {
        return Action::make('importFromUrl')
            ->label(__('admin.actions.import_from_url'))
            ->icon('heroicon-o-globe-alt')
            ->color('info')
            ->modalHeading(__('admin.actions.import_from_url'))
            ->modalDescription(__('admin.actions.import_from_url_help'))
            ->modalSubmitActionLabel(__('admin.actions.import'))
            ->schema([
                TextInput::make('docs_url')
                    ->label(__('admin.fields.docs_url'))
                    ->placeholder('https://example.com')
                    ->url()
                    ->required(),
            ])
            ->action(function (array $data): void {
                $this->runImport(fn () => ProjectImporter::fromUrl($data['docs_url']));
            });
    }

    /**
     * Fields derived from the repo metadata itself — always overwrite, even
     * if the form already has a value. Otherwise the form's create-time
     * defaults (e.g. package_type = Composer) hide whatever the importer
     * detected.
     */
    private const OVERWRITE_KEYS = ['category', 'package_type'];

    /**
     * Run an importer call and feed its result to applyImporterResult. A
     * GitHub rate-limit surfaces as the regular `rate_limited` error notice
     * (the importer throws rather than returning incomplete data), so the
     * synchronous admin action degrades gracefully instead of 500-ing.
     *
     * @param  callable():array{fields?: array<string, mixed>, warnings?: list<string>, error?: string}  $importer
     */
    private function runImport(callable $importer): void
    {
        try {
            $result = $importer();
        } catch (GithubRateLimitException) {
            $result = ['error' => 'rate_limited'];
        }

        $this->applyImporterResult($result);
    }

    /**
     * @param  array{fields?: array<string, mixed>, warnings?: list<string>, error?: string}  $result
     */
    private function applyImporterResult(array $result): void
    {
        if (isset($result['error'])) {
            Notification::make()
                ->title(__('admin.import.error.'.$result['error']))
                ->danger()
                ->send();

            return;
        }

        $state = $this->data;
        $applied = [];
        $skipped = [];

        foreach ($result['fields'] ?? [] as $key => $value) {
            if (self::isImportValueEmpty($value)) {
                continue;
            }

            if (in_array($key, self::OVERWRITE_KEYS, true) || self::isImportValueEmpty(data_get($state, $key))) {
                data_set($state, $key, $value);
                $applied[] = $key;
            } else {
                $skipped[] = $key;
            }
        }

        $this->data = $state;
        $this->form->fill($this->data);

        $lines = [];
        if (count($skipped) > 0) {
            $lines[] = __('admin.import.skipped', ['count' => count($skipped)]);
        }
        foreach ($result['warnings'] ?? [] as $warning) {
            $lines[] = __('admin.import.warning.'.$warning);
        }

        Notification::make()
            ->title(__('admin.import.success', ['count' => count($applied)]))
            ->body($lines === [] ? null : implode("\n", $lines))
            ->success()
            ->send();
    }

    private static function isImportValueEmpty(mixed $value): bool
    {
        return $value === null || $value === '' || $value === [];
    }
}
