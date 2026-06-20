<?php

declare(strict_types=1);

namespace App\Filament\Admin\Pages\Auth;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;

class Login extends \Filament\Auth\Pages\Login
{
    protected string $view = 'filament.admin.pages.auth.login';

    protected function getEmailFormComponent(): Component
    {
        return TextInput::make('email')
            ->label(__('filament-panels::auth/pages/login.form.email.label'))
            ->email()
            ->required()
            ->autocomplete();
    }

    public function getHeading(): string
    {
        return '';
    }

    public function getSubheading(): ?string
    {
        return null;
    }

    public function hasLogo(): bool
    {
        return false;
    }

    protected function getCredentialsFromFormData(array $data): array
    {
        return [
            'email' => $data['email'],
            'password' => $data['password'],
            'status' => true,
        ];
    }
}
