<?php

namespace App\Filament\Admin\Resources\Projects\Schemas;

use App\Filament\Schemas\Components\AdditionalInformation;
use App\Support\LocaleSupport;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ProjectInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Section::make(__('admin.sections.identity'))
                    ->columnSpan(2)
                    ->columns(2)
                    ->schema([
                        TextEntry::make('name')->label(__('admin.fields.name')),
                        TextEntry::make('slug')->label(__('admin.fields.slug'))->copyable(),
                        TextEntry::make('repo')->label(__('admin.fields.repo'))->placeholder('—'),
                        TextEntry::make('category')->label(__('admin.fields.category'))->badge(),
                    ]),

                Section::make(__('admin.sections.publication'))
                    ->columnSpan(1)
                    ->schema([
                        TextEntry::make('status')->label(__('admin.fields.status'))->badge(),
                        IconEntry::make('featured')->label(__('admin.fields.featured'))->boolean(),
                        TextEntry::make('sort_order')->label(__('admin.fields.sort_order')),
                        TextEntry::make('published_at')->label(__('admin.fields.published_at'))->dateTime(),
                    ]),

                Section::make(__('admin.sections.description'))
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('description')
                            ->label(__('admin.fields.description'))
                            ->getStateUsing(fn ($record) => $record->getTranslation('description', LocaleSupport::short(), false))
                            ->html(),
                    ]),

                Section::make(__('admin.sections.stack'))
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        TextEntry::make('versions')->label(__('admin.fields.versions'))->badge()->separator(','),
                        TextEntry::make('stack')->label(__('admin.fields.stack'))->badge()->separator(','),
                    ]),

                Section::make(__('admin.sections.metrics'))
                    ->columnSpan(2)
                    ->columns(4)
                    ->schema([
                        TextEntry::make('stars')->label(__('admin.fields.stars'))->numeric(),
                        TextEntry::make('downloads')->label(__('admin.fields.downloads'))->numeric(),
                        TextEntry::make('downloads_label')->label(__('admin.fields.downloads_label'))->placeholder('—'),
                        TextEntry::make('license')->label(__('admin.fields.license')),
                    ]),

                Section::make(__('admin.sections.cover'))
                    ->columnSpan(1)
                    ->schema([
                        ImageEntry::make('cover_image')->label(__('admin.fields.cover_image'))->placeholder('—'),
                    ]),

                Section::make(__('admin.sections.links'))
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        TextEntry::make('github_url')->label(__('admin.fields.github_url'))->url(fn ($state) => $state)->placeholder('—'),
                        TextEntry::make('packagist_url')->label(__('admin.fields.packagist_url'))->url(fn ($state) => $state)->placeholder('—'),
                        TextEntry::make('docs_url')->label(__('admin.fields.docs_url'))->url(fn ($state) => $state)->placeholder('—'),
                        TextEntry::make('demo_url')->label(__('admin.fields.demo_url'))->url(fn ($state) => $state)->placeholder('—'),
                    ]),

                AdditionalInformation::make([
                    'created_at',
                    'updated_at',
                    'last_synced_at',
                ]),
            ]);
    }
}
