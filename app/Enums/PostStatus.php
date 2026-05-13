<?php

namespace App\Enums;

use BackedEnum;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

enum PostStatus: string implements HasColor, HasIcon, HasLabel
{
    case Draft = 'draft';
    case Published = 'published';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draft     => __('Draft'),
            self::Published => __('Published'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Draft     => 'gray',
            self::Published => 'success',
        };
    }

    public function getIcon(): BackedEnum
    {
        return match ($this) {
            self::Draft     => Heroicon::PencilSquare,
            self::Published => Heroicon::CheckBadge,
        };
    }
}
