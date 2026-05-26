<?php

namespace App\Filament\Admin\Pages;

use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;

class HorizonDashboard extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQueueList;

    protected string $view = 'filament.admin.pages.horizon-dashboard';

    protected static ?string $slug = 'horizon';

    protected static ?int $navigationSort = 90;

    protected Width|string|null $maxContentWidth = Width::Full;

    public static function getNavigationLabel(): string
    {
        return __('Horizon');
    }

    public function getTitle(): string
    {
        return __('Horizon');
    }

    public function getHeading(): string
    {
        return '';
    }
}
