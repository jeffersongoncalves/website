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
    case Framework = 'framework';
    case StarterKit = 'starter_kit';
    case Saas = 'saas';
    case Tool = 'tool';
    case Docker = 'docker';
    case Database = 'database';

    public function getLabel(): string
    {
        return match ($this) {
            self::FilamentPlugin => __('admin.enums.category.filament_plugin'),
            self::LaravelPackage => __('admin.enums.category.laravel_package'),
            self::LivewirePackage => __('admin.enums.category.livewire_package'),
            self::CakePhpPackage => __('admin.enums.category.cakephp_package'),
            self::LaravelZeroCli => __('admin.enums.category.laravel_zero_cli'),
            self::IdePlugin => __('admin.enums.category.ide_plugin'),
            self::Framework => __('admin.enums.category.framework'),
            self::StarterKit => __('admin.enums.category.starter_kit'),
            self::Saas => __('admin.enums.category.saas'),
            self::Tool => __('admin.enums.category.tool'),
            self::Docker => __('admin.enums.category.docker'),
            self::Database => __('admin.enums.category.database'),
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
            self::Framework => 'primary',
            self::StarterKit => 'success',
            self::Saas => 'info',
            self::Tool => 'gray',
            self::Docker => 'info',
            self::Database => 'success',
        };
    }
}
