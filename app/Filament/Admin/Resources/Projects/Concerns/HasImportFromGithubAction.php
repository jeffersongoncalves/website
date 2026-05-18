<?php

namespace App\Filament\Admin\Resources\Projects\Concerns;

use App\Support\ProjectImporter;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;

trait HasImportFromGithubAction
{
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
                $result = ProjectImporter::fromGithub($data['github_url']);
                $this->applyImporterResult($result);
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
                $result = ProjectImporter::fromUrl($data['docs_url']);
                $this->applyImporterResult($result);
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

        Notification::make()
            ->title(__('admin.import.success', ['count' => count($applied)]))
            ->body(count($skipped) > 0
                ? __('admin.import.skipped', ['count' => count($skipped)])
                : null)
            ->success()
            ->send();
    }

    private static function isImportValueEmpty(mixed $value): bool
    {
        return $value === null || $value === '' || $value === [];
    }
}
