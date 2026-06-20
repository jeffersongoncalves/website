<?php

declare(strict_types=1);

namespace App\Enums;

use BackedEnum;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

enum ProjectStatus: string implements HasColor, HasIcon, HasLabel
{
    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draft => __('admin.enums.status.draft'),
            self::Published => __('admin.enums.status.published'),
            self::Archived => __('admin.enums.status.archived'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Published => 'success',
            self::Archived => 'warning',
        };
    }

    public function getIcon(): BackedEnum
    {
        return match ($this) {
            self::Draft => Heroicon::PencilSquare,
            self::Published => Heroicon::CheckBadge,
            self::Archived => Heroicon::ArchiveBox,
        };
    }
}
