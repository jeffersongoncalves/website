<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Admins\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use JeffersonGoncalves\Filament\AdditionalInformation\AdditionalInformation;

class AdminInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make()
                    ->schema([
                        TextEntry::make('id'),
                        IconEntry::make('status')
                            ->label(__('admin.fields.status'))
                            ->boolean(),
                        TextEntry::make('name')
                            ->label(__('admin.fields.name')),
                        TextEntry::make('email')
                            ->label(__('admin.fields.email'))
                            ->copyable()
                            ->copyMessage('Email copied successfully!')
                            ->copyMessageDuration(1500),
                    ]),
                AdditionalInformation::make([
                    'created_at',
                    'updated_at',
                ]),
            ]);
    }
}
