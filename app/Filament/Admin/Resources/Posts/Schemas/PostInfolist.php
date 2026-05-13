<?php

namespace App\Filament\Admin\Resources\Posts\Schemas;

use App\Filament\Schemas\Components\AdditionalInformation;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PostInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Section::make(__('Identity'))
                    ->columnSpan(2)
                    ->columns(2)
                    ->schema([
                        TextEntry::make('slug')->copyable(),
                        TextEntry::make('reading_time')->suffix(' min'),
                        TextEntry::make('views')->numeric(),
                    ]),

                Section::make(__('Publication'))
                    ->columnSpan(1)
                    ->schema([
                        TextEntry::make('status')->badge(),
                        TextEntry::make('published_at')->dateTime(),
                    ]),

                Section::make(__('Title'))
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('title')
                            ->getStateUsing(fn ($record) => $record->getTranslation('title', app()->getLocale(), false)),
                    ]),

                Section::make(__('Excerpt'))
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('excerpt')
                            ->getStateUsing(fn ($record) => $record->getTranslation('excerpt', app()->getLocale(), false))
                            ->html(),
                    ]),

                Section::make(__('Tags'))
                    ->columnSpan(2)
                    ->schema([
                        TextEntry::make('tags')->badge()->separator(','),
                    ]),

                Section::make(__('Cover'))
                    ->columnSpan(1)
                    ->schema([
                        ImageEntry::make('cover_image')->placeholder('—'),
                    ]),

                AdditionalInformation::make([
                    'created_at',
                    'updated_at',
                ]),
            ]);
    }
}
