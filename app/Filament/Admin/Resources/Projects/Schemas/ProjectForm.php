<?php

namespace App\Filament\Admin\Resources\Projects\Schemas;

use App\Enums\ProjectCategory;
use App\Enums\ProjectStatus;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;

class ProjectForm
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
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->autofocus(),
                        TextInput::make('slug')
                            ->maxLength(255)
                            ->unique('projects', 'slug', ignoreRecord: true)
                            ->helperText(__('Leave empty to auto-generate from name.')),
                        TextInput::make('repo')
                            ->maxLength(255)
                            ->helperText(__('GitHub repo name (defaults to slug).')),
                        Select::make('category')
                            ->options(ProjectCategory::class)
                            ->required()
                            ->live(),
                    ]),

                Section::make(__('Publication'))
                    ->columnSpan(1)
                    ->schema([
                        Select::make('status')
                            ->options(ProjectStatus::class)
                            ->default(ProjectStatus::Draft)
                            ->required(),
                        Toggle::make('featured')
                            ->helperText(__('Show on home page.')),
                        TextInput::make('sort_order')
                            ->integer()
                            ->default(0),
                        DateTimePicker::make('published_at'),
                    ]),

                Section::make(__('Content'))
                    ->columnSpanFull()
                    ->schema([
                        Tabs::make()
                            ->tabs([
                                Tab::make('PT')->schema(self::translatableFields('pt')),
                                Tab::make('EN')->schema(self::translatableFields('en')),
                                Tab::make('ES')->schema(self::translatableFields('es')),
                            ]),
                    ]),

                Section::make(__('Stack & versions'))
                    ->columnSpan(2)
                    ->columns(2)
                    ->schema([
                        CheckboxList::make('versions')
                            ->options([
                                'v3' => 'Filament v3',
                                'v4' => 'Filament v4',
                                'v5' => 'Filament v5',
                            ])
                            ->columns(3)
                            ->helperText(__('Default branch maps by index: lowest version = 1.x, next = 2.x, etc. Override per version below.'))
                            ->visible(fn ($get) => $get('category') === ProjectCategory::FilamentPlugin->value),
                        TagsInput::make('versions')
                            ->placeholder(__('Laravel 10/11/12, Filament v5'))
                            ->visible(fn ($get) => $get('category') !== ProjectCategory::FilamentPlugin->value),
                        TagsInput::make('stack')
                            ->placeholder(__('Laravel, Filament, Livewire')),
                        KeyValue::make('version_branches')
                            ->keyLabel(__('Version'))
                            ->valueLabel(__('GitHub branch'))
                            ->keyPlaceholder('v4')
                            ->valuePlaceholder('2.x')
                            ->helperText(__('Override the auto-mapped branch for each version. Leave blank to use default index mapping.'))
                            ->columnSpanFull()
                            ->visible(fn ($get) => $get('category') === ProjectCategory::FilamentPlugin->value),
                        TextInput::make('readme_branch')
                            ->maxLength(255)
                            ->placeholder('main')
                            ->helperText(__('GitHub branch used to fetch the README. Leave blank to use the repository default branch.'))
                            ->visible(fn ($get) => $get('category') !== ProjectCategory::FilamentPlugin->value),
                    ]),

                Section::make(__('Metrics'))
                    ->columnSpan(1)
                    ->schema([
                        TextInput::make('stars')
                            ->integer()
                            ->default(0),
                        TextInput::make('downloads')
                            ->integer()
                            ->default(0),
                        TextInput::make('downloads_label')
                            ->maxLength(32)
                            ->helperText(__('Display value: 21k, 1.2M, —')),
                        TextInput::make('license')
                            ->default('MIT')
                            ->maxLength(64),
                    ]),

                Section::make(__('Links'))
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        TextInput::make('github_url')
                            ->url()
                            ->maxLength(500),
                        TextInput::make('packagist_url')
                            ->url()
                            ->maxLength(500),
                        TextInput::make('docs_url')
                            ->url()
                            ->maxLength(500),
                        TextInput::make('demo_url')
                            ->url()
                            ->maxLength(500),
                    ]),

                Section::make(__('Cover'))
                    ->columnSpanFull()
                    ->schema([
                        FileUpload::make('cover_image')
                            ->image()
                            ->directory('projects/covers')
                            ->imageEditor(),
                    ]),
            ]);
    }

    private static function translatableFields(string $locale): array
    {
        return [
            TextInput::make("title.$locale")
                ->label(__('Title'))
                ->maxLength(255),
            Textarea::make("description.$locale")
                ->label(__('Description'))
                ->rows(3)
                ->required($locale === 'pt')
                ->maxLength(1000),
            Textarea::make("content.$locale")
                ->label(__('Content (Markdown)'))
                ->rows(10),
        ];
    }
}
