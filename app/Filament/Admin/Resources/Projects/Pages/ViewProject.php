<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Projects\Pages;

use App\Enums\ProjectStatus;
use App\Filament\Admin\Resources\Projects\ProjectResource;
use App\Models\Project;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewProject extends ViewRecord
{
    protected static string $resource = ProjectResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('viewOnSite')
                ->label(__('View on site'))
                ->icon(Heroicon::ArrowTopRightOnSquare)
                ->color('gray')
                ->url(fn (Project $record): string => $record->publicUrl())
                ->openUrlInNewTab()
                // Only published projects exist on the public site; a draft URL
                // would 404.
                ->visible(fn (Project $record): bool => $record->status === ProjectStatus::Published),
            EditAction::make(),
            DeleteAction::make(),
        ];
    }
}
