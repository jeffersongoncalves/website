<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ProjectCategory: string implements HasColor, HasLabel
{
    case FilamentPlugin = 'filament_plugin';
    case LaravelPackage = 'laravel_package';
    case LivewirePackage = 'livewire_package';
    case CakePhpPackage = 'cakephp_package';
    case LaravelZeroCli = 'laravel_zero_cli';
    case IdePlugin = 'ide_plugin';
    case PhpPackage = 'php_package';
    case JavascriptPackage = 'javascript_package';
    case Framework = 'framework';
    case CssFramework = 'css_framework';
    case StarterKit = 'starter_kit';
    case Saas = 'saas';
    case Tool = 'tool';
    case Application = 'application';
    case LearningResource = 'learning_resource';
    case AwesomeList = 'awesome_list';
    case MobileLibrary = 'mobile_library';
    case Docker = 'docker';
    case Database = 'database';
    case Website = 'website';
    case YoutubeChannel = 'youtube_channel';

    public function getLabel(): string
    {
        return match ($this) {
            self::FilamentPlugin => __('admin.enums.category.filament_plugin'),
            self::LaravelPackage => __('admin.enums.category.laravel_package'),
            self::LivewirePackage => __('admin.enums.category.livewire_package'),
            self::CakePhpPackage => __('admin.enums.category.cakephp_package'),
            self::LaravelZeroCli => __('admin.enums.category.laravel_zero_cli'),
            self::IdePlugin => __('admin.enums.category.ide_plugin'),
            self::PhpPackage => __('admin.enums.category.php_package'),
            self::JavascriptPackage => __('admin.enums.category.javascript_package'),
            self::Framework => __('admin.enums.category.framework'),
            self::CssFramework => __('admin.enums.category.css_framework'),
            self::StarterKit => __('admin.enums.category.starter_kit'),
            self::Saas => __('admin.enums.category.saas'),
            self::Tool => __('admin.enums.category.tool'),
            self::Application => __('admin.enums.category.application'),
            self::LearningResource => __('admin.enums.category.learning_resource'),
            self::AwesomeList => __('admin.enums.category.awesome_list'),
            self::MobileLibrary => __('admin.enums.category.mobile_library'),
            self::Docker => __('admin.enums.category.docker'),
            self::Database => __('admin.enums.category.database'),
            self::Website => __('admin.enums.category.website'),
            self::YoutubeChannel => __('admin.enums.category.youtube_channel'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::FilamentPlugin => 'warning',
            self::LaravelPackage => 'danger',
            self::LivewirePackage => 'success',
            self::CakePhpPackage => 'info',
            self::LaravelZeroCli => 'warning',
            self::IdePlugin => 'gray',
            self::PhpPackage => 'info',
            self::JavascriptPackage => 'warning',
            self::Framework => 'primary',
            self::CssFramework => 'info',
            self::StarterKit => 'success',
            self::Saas => 'info',
            self::Tool => 'gray',
            self::Application => 'gray',
            self::LearningResource => 'success',
            self::AwesomeList => 'primary',
            self::MobileLibrary => 'success',
            self::Docker => 'info',
            self::Database => 'success',
            self::Website => 'primary',
            self::YoutubeChannel => 'danger',
        };
    }
}
