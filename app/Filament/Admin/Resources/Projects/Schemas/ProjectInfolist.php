<?php

namespace App\Filament\Admin\Resources\Projects\Schemas;

use App\Filament\Schemas\Components\AdditionalInformation;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ProjectInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Grid::make()
                    ->columnSpan(2)
                    ->schema([
                        Section::make(__('admin.sections.identity'))
                            ->columnSpanFull()
                            ->columns(2)
                            ->schema([
                                TextEntry::make('name')->label(__('admin.fields.name')),
                                TextEntry::make('slug')->label(__('admin.fields.slug'))->copyable(),
                                TextEntry::make('repo')->label(__('admin.fields.repo'))->placeholder('—'),
                                TextEntry::make('category')->label(__('admin.fields.category'))->badge(),
                                TextEntry::make('package_type')->label(__('admin.fields.package_type'))->badge()->placeholder('—'),
                            ]),
                        Grid::make()
                            ->columnSpan(1)
                            ->schema([
                                Section::make(__('admin.sections.stack'))
                                    ->columnSpanFull()
                                    ->columns(2)
                                    ->schema([
                                        TextEntry::make('versions')->label(__('admin.fields.versions'))->badge()->separator(','),
                                        TextEntry::make('stack')->label(__('admin.fields.stack'))->badge()->separator(','),
                                    ]),
                            ]),
                        Grid::make()
                            ->columnSpan(1)
                            ->schema([
                                Section::make(__('admin.sections.metrics'))
                                    ->columnSpanFull()
                                    ->columns(4)
                                    ->schema([
                                        TextEntry::make('stars')->label(__('admin.fields.stars'))->numeric(),
                                        TextEntry::make('downloads')->label(__('admin.fields.downloads'))->numeric(),
                                        TextEntry::make('downloads_label')->label(__('admin.fields.downloads_label'))->placeholder('—'),
                                        TextEntry::make('license')->label(__('admin.fields.license')),
                                    ]),
                            ]),
                        AdditionalInformation::make([
                            'created_at',
                            'updated_at',
                            'last_synced_at',
                        ])
                            ->columnSpanFull()
                            ->columns(3),
                    ]),
                Grid::make()
                    ->columnSpan(1)
                    ->schema([
                        Section::make(__('admin.sections.publication'))
                            ->columnSpanFull()
                            ->schema([
                                TextEntry::make('status')->label(__('admin.fields.status'))->badge(),
                                IconEntry::make('featured')->label(__('admin.fields.featured'))->boolean(),
                                TextEntry::make('published_at')->label(__('admin.fields.published_at'))->dateTime(),
                            ]),
                        Section::make(__('admin.sections.links'))
                            ->columnSpanFull()
                            ->schema([
                                TextEntry::make('github_url')->label(__('admin.fields.github_url'))->url(fn ($state) => $state)->hidden(fn ($state) => blank($state)),
                                TextEntry::make('packagist_url')->label(__('admin.fields.packagist_url'))->url(fn ($state) => $state)->hidden(fn ($state) => blank($state)),
                                TextEntry::make('npm_url')->label(__('admin.fields.npm_url'))->url(fn ($state) => $state)->hidden(fn ($state) => blank($state)),
                                TextEntry::make('docker_url')->label(__('admin.fields.docker_url'))->url(fn ($state) => $state)->hidden(fn ($state) => blank($state)),
                                TextEntry::make('docs_url')->label(__('admin.fields.docs_url'))->url(fn ($state) => $state)->hidden(fn ($state) => blank($state)),
                                TextEntry::make('demo_url')->label(__('admin.fields.demo_url'))->url(fn ($state) => $state)->hidden(fn ($state) => blank($state)),
                            ]),
                    ]),
            ]);
    }
}
