<?php

namespace App\Filament\Admin\Resources\Projects\Tables;

use App\Enums\ProjectCategory;
use App\Enums\ProjectStatus;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

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
                    ->options(ProjectCategory::class),
                SelectFilter::make('status')
                    ->label(__('admin.fields.status'))
                    ->options(ProjectStatus::class),
                TernaryFilter::make('featured')
                    ->label(__('admin.fields.featured')),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
