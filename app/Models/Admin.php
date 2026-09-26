<?php

declare(strict_types=1);

namespace App\Models;

use Filament\Panel;
use JeffersonGoncalves\Filament\Admin\Models\Admin as BaseAdmin;

/**
 * Columns, casts, factory, avatar and the admins_count observer come from
 * jeffersongoncalves/filament-admin; only the panel gate diverges here.
 */
class Admin extends BaseAdmin
{
    public function canAccessPanel(Panel $panel): bool
    {
        // Per-request gate (Filament re-runs this on every request, incl.
        // remember-me re-auth) — deactivating an admin locks them out of an
        // already-open session, not just at the login form.
        return $this->status === true;
    }
}
