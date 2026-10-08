<?php

declare(strict_types=1);

namespace App\Filament\Admin\Pages\Auth;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use JeffersonGoncalves\Filament\Admin\Pages\Auth\Login as BaseLogin;

/**
 * The status = true credential check comes from filament-admin's Login;
 * this only restyles the page.
 */
class Login extends BaseLogin
{
    protected string $view = 'filament-editorial-theme::auth.login';

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
}
