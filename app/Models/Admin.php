<?php

declare(strict_types=1);

namespace App\Models;

use JeffersonGoncalves\Filament\Admin\Models\Admin as BaseAdmin;

/**
 * Columns, casts, factory, avatar, the admins_count observer and the
 * status-aware panel gate come from jeffersongoncalves/filament-admin.
 * Customise the gate with FilamentAdmin::canAccessPanelUsing(...).
 */
class Admin extends BaseAdmin {}
