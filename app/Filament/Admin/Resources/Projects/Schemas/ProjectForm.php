<?php

namespace App\Filament\Admin\Resources\Projects\Schemas;

use App\Enums\PackageType;
use App\Enums\ProjectCategory;
use App\Enums\ProjectLanguage;
use App\Enums\ProjectStatus;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
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
                Grid::make()
                    ->columnSpan(2)
                    ->schema([
                        Section::make(__('admin.sections.identity'))
                            ->columnSpanFull()
                            ->columns(2)
                            ->schema([
                                TextInput::make('name')
                                    ->label(__('admin.fields.name'))
                                    ->required()
                                    ->maxLength(255)
                                    ->autofocus(),
                                TextInput::make('slug')
                                    ->label(__('admin.fields.slug'))
                                    ->maxLength(255)
                                    ->unique('projects', 'slug', ignoreRecord: true)
                                    ->disabledOn('edit')
                                    ->helperText(__('admin.helpers.slug')),
                                TextInput::make('repo')
                                    ->label(__('admin.fields.repo'))
                                    ->maxLength(255)
                                    ->disabledOn('edit')
                                    ->helperText(__('admin.helpers.repo')),
                                Select::make('category')
                                    ->label(__('admin.fields.category'))
                                    ->options(ProjectCategory::class)
                                    ->required()
                                    ->live(),
                                Select::make('package_type')
                                    ->label(__('admin.fields.package_type'))
                                    ->options(PackageType::class)
                                    ->default(PackageType::Composer)
                                    ->helperText(__('admin.helpers.package_type')),
                                Select::make('language')
                                    ->label(__('admin.fields.language'))
                                    ->options(ProjectLanguage::class)
                                    ->helperText(__('admin.helpers.language')),
                            ]),
                        Section::make(__('admin.sections.title'))
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
                            ->columnSpanFull()
                            ->columns(2)
                            ->schema([
                                Toggle::make('has_branches')
                                    ->label(__('admin.fields.has_branches'))
                                    ->helperText(__('admin.helpers.has_branches'))
                                    ->live()
                                    ->columnSpanFull()
                                    // A paid plugin is private — no public branches to track.
                                    ->visible(fn (Get $get) => enum_equals($get('category'), ProjectCategory::FilamentPlugin)
                                        && ! $get('is_paid')),
                                CheckboxList::make('versions')
                                    ->label(__('admin.fields.versions'))
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
                                    ->visible(fn (Get $get) => enum_equals($get('category'), ProjectCategory::FilamentPlugin)
                                        && ! $get('is_paid')
                                        && $get('has_branches')),
                                TagsInput::make('versions')
                                    ->label(__('admin.fields.versions'))
                                    ->placeholder(__('admin.placeholders.versions_free'))
                                    ->visible(fn (Get $get) => ! enum_equals($get('category'), ProjectCategory::FilamentPlugin)),
                                TagsInput::make('stack')
                                    ->label(__('admin.fields.stack'))
                                    ->placeholder(__('admin.placeholders.stack')),
                                TagsInput::make('topics')
                                    ->label(__('admin.fields.topics'))
                                    ->helperText(__('admin.helpers.topics'))
                                    ->columnSpanFull(),
                                KeyValue::make('branch_overrides')
                                    ->label(__('admin.fields.branch_overrides'))
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
                                    ->visible(fn (Get $get) => enum_equals($get('category'), ProjectCategory::FilamentPlugin)
                                        && ! $get('is_paid')
                                        && $get('has_branches')),
                                TextInput::make('readme_branch')
                                    ->label(__('admin.fields.readme_branch'))
                                    ->maxLength(255)
                                    ->placeholder('main')
                                    ->helperText(__('admin.helpers.readme_branch'))
                                    ->visible(fn (Get $get) => ! enum_equals($get('category'), ProjectCategory::FilamentPlugin)),
                            ]),
                    ]),
                Grid::make()
                    ->columnSpan(1)
                    ->schema([
                        Section::make(__('admin.sections.publication'))
                            ->columnSpanFull()
                            ->schema([
                                Select::make('status')
                                    ->label(__('admin.fields.status'))
                                    ->options(ProjectStatus::class)
                                    ->default(ProjectStatus::Draft)
                                    ->required(),
                                Toggle::make('featured')
                                    ->label(__('admin.fields.featured'))
                                    ->helperText(__('admin.helpers.featured')),
                                Toggle::make('is_maintainer')
                                    ->label(__('admin.fields.is_maintainer'))
                                    ->helperText(__('admin.helpers.is_maintainer')),
                                Toggle::make('is_daily_driver')
                                    ->label(__('admin.fields.is_daily_driver'))
                                    ->helperText(__('admin.helpers.is_daily_driver')),
                                Toggle::make('is_paid')
                                    ->label(__('admin.fields.is_paid'))
                                    ->helperText(__('admin.helpers.is_paid'))
                                    // Drives the visibility of the plugin branch-tracking fields.
                                    ->live(),
                            ]),
                        Section::make(__('admin.sections.links'))
                            ->columnSpanFull()
                            ->schema([
                                TextInput::make('github_url')
                                    ->label(__('admin.fields.github_url'))
                                    ->url()
                                    ->maxLength(500),
                                TextInput::make('packagist_url')
                                    ->label(__('admin.fields.packagist_url'))
                                    ->url()
                                    ->maxLength(500),
                                TextInput::make('npm_url')
                                    ->label(__('admin.fields.npm_url'))
                                    ->url()
                                    ->maxLength(500),
                                TextInput::make('docker_url')
                                    ->label(__('admin.fields.docker_url'))
                                    ->helperText(__('admin.fields.docker_url_hint'))
                                    ->url()
                                    ->maxLength(500),
                                TextInput::make('docs_url')
                                    ->label(__('admin.fields.docs_url'))
                                    ->url()
                                    ->maxLength(500),
                                TextInput::make('demo_url')
                                    ->label(__('admin.fields.demo_url'))
                                    ->url()
                                    ->maxLength(500),
                            ]),
                    ]),
            ]);
    }

    private static function translatableFields(string $locale): array
    {
        return [
            TextInput::make("title.$locale")
                ->label(__('admin.fields.title')),
        ];
    }
}
