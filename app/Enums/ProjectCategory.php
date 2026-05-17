<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ProjectCategory: string implements HasColor, HasLabel
{
    case FilamentPlugin = 'filament_plugin';
    case LaravelPackage = 'laravel_package';
    case Framework = 'framework';
    case StarterKit = 'starter_kit';
    case Saas = 'saas';
    case Tool = 'tool';

    public function getLabel(): string
    {
        return match ($this) {
            self::FilamentPlugin => __('admin.enums.category.filament_plugin'),
            self::LaravelPackage => __('admin.enums.category.laravel_package'),
            self::Framework => __('admin.enums.category.framework'),
            self::StarterKit => __('admin.enums.category.starter_kit'),
            self::Saas => __('admin.enums.category.saas'),
            self::Tool => __('admin.enums.category.tool'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::FilamentPlugin => 'warning',
            self::LaravelPackage => 'danger',
            self::Framework => 'primary',
            self::StarterKit => 'success',
            self::Saas => 'info',
            self::Tool => 'gray',
        };
    }
}
