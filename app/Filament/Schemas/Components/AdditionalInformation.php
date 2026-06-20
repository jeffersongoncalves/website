<?php

declare(strict_types=1);

namespace App\Filament\Schemas\Components;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;

class AdditionalInformation
{
    /**
     * @param  array<int, string>  $dates
     */
    public static function make(array $dates = ['created_at', 'updated_at']): Section
    {
        $dates = collect($dates)->map(fn (string $date): TextEntry => TextEntry::make($date)->dateTime())->toArray();

        return Section::make(__('ADDITIONAL INFORMATION'))
            ->description(__('Information on the date of registration and date of modification.'))
            ->columns()
            ->schema($dates);
    }
}
