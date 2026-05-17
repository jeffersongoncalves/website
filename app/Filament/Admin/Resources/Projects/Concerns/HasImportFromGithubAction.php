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

                    if (self::isImportValueEmpty(data_get($state, $key))) {
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
            });
    }

    private static function isImportValueEmpty(mixed $value): bool
    {
        return $value === null || $value === '' || $value === [];
    }
}
