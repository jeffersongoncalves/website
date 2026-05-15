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
                        TextEntry::make('name'),
                        TextEntry::make('slug')->copyable(),
                        TextEntry::make('repo')->placeholder('—'),
                        TextEntry::make('category')->badge(),
                    ]),

                Section::make(__('admin.sections.publication'))
                    ->columnSpan(1)
                    ->schema([
                        TextEntry::make('status')->badge(),
                        IconEntry::make('featured')->boolean(),
                        TextEntry::make('sort_order'),
                        TextEntry::make('published_at')->dateTime(),
                    ]),

                Section::make(__('admin.sections.description'))
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('description')
                            ->getStateUsing(fn ($record) => $record->getTranslation('description', LocaleSupport::short(), false))
                            ->html(),
                    ]),

                Section::make(__('admin.sections.stack'))
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        TextEntry::make('versions')->badge()->separator(','),
                        TextEntry::make('stack')->badge()->separator(','),
                    ]),

                Section::make(__('admin.sections.metrics'))
                    ->columnSpan(2)
                    ->columns(4)
                    ->schema([
                        TextEntry::make('stars')->numeric(),
                        TextEntry::make('downloads')->numeric(),
                        TextEntry::make('downloads_label')->placeholder('—'),
                        TextEntry::make('license'),
                    ]),

                Section::make(__('admin.sections.cover'))
                    ->columnSpan(1)
                    ->schema([
                        ImageEntry::make('cover_image')->placeholder('—'),
                    ]),

                Section::make(__('admin.sections.links'))
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        TextEntry::make('github_url')->url(fn ($state) => $state)->placeholder('—'),
                        TextEntry::make('packagist_url')->url(fn ($state) => $state)->placeholder('—'),
                        TextEntry::make('docs_url')->url(fn ($state) => $state)->placeholder('—'),
                        TextEntry::make('demo_url')->url(fn ($state) => $state)->placeholder('—'),
                    ]),

                AdditionalInformation::make([
                    'created_at',
                    'updated_at',
                    'last_synced_at',
                ]),
            ]);
    }
}
