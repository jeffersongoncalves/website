<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Projects\Pages;

use App\Enums\ProjectStatus;
use App\Filament\Admin\Resources\Projects\ProjectResource;
use App\Models\Project;
use App\Support\ProjectAttributes;
use App\Support\ProjectImporter;
use App\Support\ProjectMatcher;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use JeffersonGoncalves\GitHubClient\Exceptions\GitHubRateLimitException;

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
                        'article' => __('admin.enums.category.article'),
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
                try {
                    $result = match ($data['source']) {
                        'github' => ProjectImporter::fromGithub($data['url']),
                        'npm' => ProjectImporter::fromNpm($data['url']),
                        'youtube' => ProjectImporter::fromYoutube($data['url']),
                        'article' => ProjectImporter::fromArticle($data['url']),
                        default => ProjectImporter::fromUrl($data['url']),
                    };
                } catch (GitHubRateLimitException) {
                    $result = ['error' => 'rate_limited'];
                }

                if (isset($result['error'])) {
                    Notification::make()
                        ->title(__('admin.import.error.'.$result['error']))
                        ->danger()
                        ->send();

                    return;
                }

                $fields = $result['fields'] ?? [];
                $attributes = ProjectAttributes::normalize($fields);
                // Defer to HasSlug — drop the importer's slug so duplicates
                // resolve with `-1`/`-2` instead of hitting a unique
                // constraint mid-create.
                unset($attributes['slug']);

                $existing = ProjectMatcher::findExisting($data['source'], $attributes);

                if ($existing !== null) {
                    $changes = ProjectMatcher::fillMissing($existing, $attributes);
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
}
