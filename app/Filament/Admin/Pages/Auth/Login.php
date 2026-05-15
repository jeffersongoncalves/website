<?php

namespace App\Filament\Admin\Pages\Auth;

class Login extends \Filament\Auth\Pages\Login
{
    protected string $view = 'filament.admin.pages.auth.login';

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
