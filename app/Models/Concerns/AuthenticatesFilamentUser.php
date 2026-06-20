<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use Illuminate\Auth\Authenticatable;
use Illuminate\Auth\MustVerifyEmail;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Foundation\Auth\Access\Authorizable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;

/**
 * Shared structure for the auth models (App\Models\Admin and App\Models\User):
 * fillable/hidden columns, casts, the Filament avatar accessor and the bundled
 * Laravel auth traits. The divergent panel-access rules (canAccessPanel /
 * canImpersonate) stay on each model.
 */
trait AuthenticatesFilamentUser
{
    use Authenticatable;
    use Authorizable;
    use CanResetPassword;
    use MustVerifyEmail;
    use Notifiable;

    public function initializeAuthenticatesFilamentUser(): void
    {
        $this->mergeFillable([
            'status',
            'name',
            'email',
            'password',
            'avatar_url',
            'custom_fields',
            'locale',
            'theme_color',
        ]);

        $this->mergeHidden([
            'password',
            'remember_token',
        ]);
    }

    public function getFilamentAvatarUrl(): ?string
    {
        $avatarColumn = config('filament-edit-profile.avatar_column', 'avatar_url');

        return $this->$avatarColumn ? Storage::url($this->$avatarColumn) : null;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'status' => 'boolean',
            'custom_fields' => 'array',
        ];
    }
}
