<?php

namespace App\Filament\Admin\Resources\Posts\Schemas;

use App\Enums\PostStatus;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;

class PostForm
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
                        TextInput::make('slug')
                            ->maxLength(255)
                            ->unique('posts', 'slug', ignoreRecord: true)
                            ->helperText(__('Leave empty to auto-generate.')),
                        TextInput::make('reading_time')
                            ->integer()
                            ->suffix(__('min'))
                            ->default(0),
                    ]),

                Section::make(__('Publication'))
                    ->columnSpan(1)
                    ->schema([
                        Select::make('status')
                            ->options(PostStatus::class)
                            ->default(PostStatus::Draft)
                            ->required(),
                        DateTimePicker::make('published_at'),
                    ]),

                Section::make(__('Content'))
                    ->columnSpanFull()
                    ->schema([
                        Tabs::make()
                            ->tabs([
                                Tab::make('PT')->schema(self::translatableFields('pt')),
                                Tab::make('EN')->schema(self::translatableFields('en')),
                            ]),
                    ]),

                Section::make(__('Tags & cover'))
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        TagsInput::make('tags')
                            ->placeholder(__('Filament, Laravel, PHP')),
                        FileUpload::make('cover_image')
                            ->image()
                            ->directory('posts/covers')
                            ->imageEditor(),
                    ]),
            ]);
    }

    private static function translatableFields(string $locale): array
    {
        return [
            TextInput::make("title.$locale")
                ->label(__('Title'))
                ->required($locale === 'pt')
                ->maxLength(255),
            Textarea::make("excerpt.$locale")
                ->label(__('Excerpt'))
                ->rows(3)
                ->maxLength(500),
            Textarea::make("body.$locale")
                ->label(__('Body (Markdown)'))
                ->rows(20)
                ->required($locale === 'pt'),
        ];
    }
}
