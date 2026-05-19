<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum PackageType: string implements HasColor, HasLabel
{
    case Composer = 'composer';
    case Npm = 'npm';
    case JetBrains = 'jetbrains';
    case Docker = 'docker';
    case None = 'none';

    public function getLabel(): string
    {
        return match ($this) {
            self::Composer => __('admin.enums.package_type.composer'),
            self::Npm => __('admin.enums.package_type.npm'),
            self::JetBrains => __('admin.enums.package_type.jetbrains'),
            self::Docker => __('admin.enums.package_type.docker'),
            self::None => __('admin.enums.package_type.none'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Composer => 'warning',
            self::Npm => 'danger',
            self::JetBrains => 'info',
            self::Docker => 'info',
            self::None => 'gray',
        };
    }
}
