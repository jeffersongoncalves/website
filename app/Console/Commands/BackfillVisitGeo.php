<?php

declare(strict_types=1);

namespace App\Console\Commands;

use GeoIp2\Database\Reader;
use Illuminate\Console\Command;
use JeffersonGoncalves\LaravelShortUrl\Models\Visit;
use Throwable;

/**
 * One-off backfill: visits recorded before the MaxMind GeoLite2-City database
 * was installed (or while visitor-fingerprint.geoip.driver was still
 * "headers") have no city/region/lat/lng. Walks every such row and resolves
 * it from the now-local database.
 *
 * Looks up `ip_anonymized`, not the original IP — only the anonymized
 * address survives on old rows (LGPD; see short-url.compliance). It's the
 * last IPv4 octet zeroed / IPv6 truncated to a /48 network address, i.e. a
 * network prefix rather than a host — MaxMind's city blocks are essentially
 * never finer than /24, so this resolves to the same city a live lookup on
 * the original IP would have, just without ISP/ASN precision.
 */
class BackfillVisitGeo extends Command
{
    protected $signature = 'short-url:backfill-geo {--chunk=500}';

    protected $description = 'Backfill country/region/city/coordinates on existing visits using the local MaxMind GeoLite2-City database';

    public function handle(): int
    {
        $path = (string) config('visitor-fingerprint.geoip.maxmind_database_path');

        if (! is_file($path)) {
            $this->error("GeoLite2-City database not found at {$path}. Run `php artisan geoip:update` first.");

            return self::FAILURE;
        }

        $query = Visit::query()->whereNull('city')->whereNotNull('ip_anonymized');
        $total = (clone $query)->count();

        if ($total === 0) {
            $this->info('Nothing to backfill — every visit already has a city or has no stored IP.');

            return self::SUCCESS;
        }

        // Only opened once there's confirmed work — a plain "nothing to do"
        // run never pays for parsing the MMDB file.
        $reader = $this->makeReader($path);

        $bar = $this->output->createProgressBar($total);
        $resolved = 0;

        $query->orderBy('id')->chunkById((int) $this->option('chunk'), function ($visits) use ($reader, $bar, &$resolved): void {
            foreach ($visits as $visit) {
                $bar->advance();

                try {
                    $record = $reader->city($visit->ip_anonymized);
                } catch (Throwable) {
                    // Not in the database — private/reserved range, or an
                    // address GeoLite2 has no block for. Leave the row null.
                    continue;
                }

                $visit->forceFill([
                    'country' => $record->country->name,
                    'country_code' => $record->country->isoCode,
                    'region' => $record->mostSpecificSubdivision->name,
                    'city' => $record->city->name,
                    'latitude' => $record->location->latitude,
                    'longitude' => $record->location->longitude,
                    'timezone' => $record->location->timeZone,
                ])->save();

                if ($record->city->name !== null) {
                    $resolved++;
                }
            }
        });

        $bar->finish();
        $this->newLine();
        $this->info("Resolved a city for {$resolved} of {$total} visits.");

        return self::SUCCESS;
    }

    /**
     * Seam for tests: a real Reader validates the file's MMDB metadata on
     * construction, so a unit test swaps this for a mock instead of needing
     * a real (binary, non-trivial to fixture) GeoLite2 database.
     */
    protected function makeReader(string $path): Reader
    {
        return new Reader($path);
    }
}
