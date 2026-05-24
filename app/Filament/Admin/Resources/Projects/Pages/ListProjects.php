<?php

namespace App\Filament\Admin\Resources\Projects\Pages;

use App\Enums\ProjectStatus;
use App\Filament\Admin\Resources\Projects\ProjectResource;
use App\Models\Project;
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
