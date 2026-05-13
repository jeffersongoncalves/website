<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ProjectCategory: string implements HasColor, HasLabel
{
    case FilamentPlugin = 'filament_plugin';
    case LaravelPackage = 'laravel_package';
    case StarterKit = 'starter_kit';
    case Saas = 'saas';
    case Tool = 'tool';

    public function getLabel(): string
    {
        return match ($this) {
            self::FilamentPlugin => __('Filament Plugin'),
            self::LaravelPackage => __('Laravel Package'),
            self::StarterKit     => __('Starter Kit'),
            self::Saas           => __('SaaS'),
            self::Tool           => __('Tool'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::FilamentPlugin => 'warning',
            self::LaravelPackage => 'danger',
            self::StarterKit     => 'success',
            self::Saas           => 'info',
            self::Tool           => 'gray',
        };
    }
}
