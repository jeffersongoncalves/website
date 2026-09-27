<?php

declare(strict_types=1);

namespace App\Models;

use JeffersonGoncalves\Filament\User\Models\User as BaseUser;

/**
 * Columns, casts, factory, avatar, the users_count observer and the
 * status-aware panel gate (admin panel denied) come from
 * jeffersongoncalves/filament-user — see config filament-user.panel_access.
 */
class User extends BaseUser {}
