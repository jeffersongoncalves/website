<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        // Seed the production Google tag (gtag.js) measurement id. Local and
        // staging keep the null default so the tag only fires in production.
        // Admins can override the id at runtime via the Filament gtag settings
        // page (which also exposes enabled / anonymize_ip / additional_config).
        if (app()->environment('production')) {
            $this->migrator->update('gtag.gtag_id', fn (?string $current): string => 'G-EZTG2K8TFY');
        }
    }
};
