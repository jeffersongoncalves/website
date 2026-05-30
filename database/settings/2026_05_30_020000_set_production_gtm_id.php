<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        // Restore the production GTM container (previously shipped hardcoded).
        // Local/staging keep the empty default seeded by the package migration
        // so Tag Manager only fires in production. Admins can override the id
        // at runtime via the Filament GTM settings page.
        if (app()->environment('production')) {
            $this->migrator->update('gtm.gtm_id', fn (string $current): string => 'GTM-MR8KB3FM');
        }
    }
};
