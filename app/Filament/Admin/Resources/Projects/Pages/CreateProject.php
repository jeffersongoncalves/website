<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Projects\Pages;

use App\Filament\Admin\Resources\Projects\Concerns\HasImportFromGithubAction;
use App\Filament\Admin\Resources\Projects\ProjectResource;
use Filament\Resources\Pages\CreateRecord;

class CreateProject extends CreateRecord
{
    use HasImportFromGithubAction;

    protected static string $resource = ProjectResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->importActionGroup(),
        ];
    }
}
