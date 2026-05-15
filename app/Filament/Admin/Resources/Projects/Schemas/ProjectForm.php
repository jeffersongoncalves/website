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
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

use function App\Support\enum_equals;

class ProjectForm
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
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->autofocus(),
                        TextInput::make('slug')
                            ->maxLength(255)
                            ->unique('projects', 'slug', ignoreRecord: true)
                            ->helperText(__('admin.helpers.slug')),
                        TextInput::make('repo')
                            ->maxLength(255)
                            ->helperText(__('admin.helpers.repo')),
                        Select::make('category')
                            ->options(ProjectCategory::class)
                            ->required()
                            ->live(),
                    ]),

                Section::make(__('admin.sections.publication'))
                    ->columnSpan(1)
                    ->schema([
                        Select::make('status')
                            ->options(ProjectStatus::class)
                            ->default(ProjectStatus::Draft)
                            ->required(),
                        Toggle::make('featured')
                            ->helperText(__('admin.helpers.featured')),
                        Toggle::make('is_maintainer')
                            ->label(__('admin.fields.is_maintainer'))
                            ->helperText(__('admin.helpers.is_maintainer'))
                            ->visible(fn (Get $get) => enum_equals($get('category'), ProjectCategory::FilamentPlugin)),
                        TextInput::make('sort_order')
                            ->integer()
                            ->default(0),
                        DateTimePicker::make('published_at'),
                    ]),

                Section::make(__('admin.sections.content'))
                    ->columnSpanFull()
                    ->schema([
                        Tabs::make()
                            ->tabs([
                                Tab::make('PT')->schema(self::translatableFields('pt')),
                                Tab::make('EN')->schema(self::translatableFields('en')),
                                Tab::make('ES')->schema(self::translatableFields('es')),
                            ]),
                    ]),

                Section::make(__('admin.sections.stack_versions'))
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
                            ->helperText(__('admin.helpers.versions'))
                            ->live()
                            ->afterStateUpdated(function ($state, Set $set, Get $get): void {
                                $current = (array) ($get('branch_overrides') ?? []);
                                $next = [];
                                foreach (array_values((array) $state) as $i => $_) {
                                    $branch = ($i + 1).'.x';
                                    $next[$branch] = $current[$branch] ?? $branch;
                                }
                                $set('branch_overrides', $next);
                            })
                            ->visible(fn (Get $get) => enum_equals($get('category'), ProjectCategory::FilamentPlugin)),
                        TagsInput::make('versions')
                            ->placeholder(__('admin.placeholders.versions_free'))
                            ->visible(fn (Get $get) => ! enum_equals($get('category'), ProjectCategory::FilamentPlugin)),
                        TagsInput::make('stack')
                            ->placeholder(__('admin.placeholders.stack')),
                        KeyValue::make('branch_overrides')
                            ->keyLabel(__('admin.fields.auto_branch'))
                            ->valueLabel(__('admin.fields.real_branch'))
                            ->keyPlaceholder('1.x')
                            ->valuePlaceholder('main')
                            ->helperText(__('admin.helpers.branch_overrides'))
                            ->columnSpanFull()
                            ->afterStateHydrated(function (KeyValue $component, $state, Get $get): void {
                                if (! empty($state)) {
                                    return;
                                }
                                $versions = $get('versions');
                                if (! is_array($versions) || $versions === []) {
                                    return;
                                }
                                $map = [];
                                foreach (array_values($versions) as $i => $_) {
                                    $branch = ($i + 1).'.x';
                                    $map[$branch] = $branch;
                                }
                                $component->state($map);
                            })
                            ->visible(fn (Get $get) => enum_equals($get('category'), ProjectCategory::FilamentPlugin)),
                        TextInput::make('readme_branch')
                            ->maxLength(255)
                            ->placeholder('main')
                            ->helperText(__('admin.helpers.readme_branch'))
                            ->visible(fn (Get $get) => ! enum_equals($get('category'), ProjectCategory::FilamentPlugin)),
                    ]),

                Section::make(__('admin.sections.metrics'))
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
                            ->helperText(__('admin.helpers.downloads_label')),
                        TextInput::make('license')
                            ->default('MIT')
                            ->maxLength(64),
                    ]),

                Section::make(__('admin.sections.links'))
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

                Section::make(__('admin.sections.cover'))
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
                ->label(__('admin.fields.title'))
                ->maxLength(255),
            Textarea::make("description.$locale")
                ->label(__('admin.fields.description'))
                ->rows(3)
                ->required($locale === 'pt')
                ->maxLength(1000),
            Textarea::make("content.$locale")
                ->label(__('admin.fields.content_markdown'))
                ->rows(10),
        ];
    }
}
