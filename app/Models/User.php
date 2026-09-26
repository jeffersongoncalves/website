<?php

declare(strict_types=1);

namespace App\Models;

use Filament\Panel;
use JeffersonGoncalves\Filament\User\Models\User as BaseUser;

/**
 * Columns, casts, factory, avatar and the users_count observer come from
 * jeffersongoncalves/filament-user; only the panel/impersonation rules diverge here.
 */
class User extends BaseUser
{
    public function canAccessPanel(Panel $panel): bool
    {
        if ($panel->getId() === 'admin') {
            return false;
        }

        // Status checked on every request (not only at login) so a deactivated
        // user is dropped from an existing session / remember-me too.
        return $this->status === true;
    }

    /**
     * The App panel (the impersonation target, /app) is disabled in this
     * project, so impersonating would land on a 404 — hide the action.
     */
    public function canBeImpersonated(): bool
    {
        return false;
    }
}
