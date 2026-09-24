<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Projects\Tables;

use App\Enums\ProjectCategory;
use App\Enums\ProjectStatus;
use App\Models\Project;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class ProjectsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->label(__('admin.fields.name'))
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),
                TextColumn::make('category')
                    ->label(__('admin.fields.category'))
                    ->badge()
                    ->sortable(),
                TextColumn::make('status')
                    ->label(__('admin.fields.status'))
                    ->badge()
                    ->sortable(),
                TextColumn::make('language')
                    ->label(__('admin.fields.language'))
                    ->badge()
                    ->placeholder('—')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('topics')
                    ->label(__('admin.fields.topics'))
                    ->badge()
                    ->placeholder('—')
                    ->limitList(5)
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('featured')
                    ->label(__('admin.fields.featured'))
                    ->boolean()
                    ->sortable(),
                TextColumn::make('stars')
                    ->label(__('admin.fields.stars'))
                    ->numeric()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('downloads_label')
                    ->label(__('admin.fields.downloads'))
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('published_at')
                    ->label(__('admin.fields.published_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label(__('admin.fields.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('category')
                    ->label(__('admin.fields.category'))
                    ->options(ProjectCategory::class)
                    ->multiple(),
                SelectFilter::make('status')
                    ->label(__('admin.fields.status'))
                    ->options(ProjectStatus::class),
                Filter::make('authored')
                    ->label(__('admin.fields.authored'))
                    ->query(function (Builder $query): Builder {
                        /** @var Builder<Project> $query */
                        return $query->authored();
                    }),
                TernaryFilter::make('featured')
                    ->label(__('admin.fields.featured')),
                TernaryFilter::make('is_daily_driver')
                    ->label(__('admin.fields.is_daily_driver')),
                TernaryFilter::make('is_maintainer')
                    ->label(__('admin.fields.is_maintainer')),
                TernaryFilter::make('is_paid')
                    ->label(__('admin.fields.is_paid')),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('publish')
                        ->label(__('admin.actions.publish'))
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->chunkSelectedRecords(500)
                        ->requiresConfirmation()
                        ->deselectRecordsAfterCompletion()
                        ->action(function (Collection $records): void {
                            $count = 0;

                            foreach ($records as $record) {
                                if ($record->getAttribute('status') !== ProjectStatus::Published) {
                                    // Per-record update so the observer stamps
                                    // published_at and flushes the count/cards cache.
                                    $record->update(['status' => ProjectStatus::Published]);
                                    $count++;
                                }
                            }

                            Notification::make()
                                ->success()
                                ->title(__('admin.actions.publish_success', ['count' => $count]))
                                ->send();
                        }),
                    DeleteBulkAction::make()
                        ->chunkSelectedRecords(500),
                ]),
            ]);
    }
}
