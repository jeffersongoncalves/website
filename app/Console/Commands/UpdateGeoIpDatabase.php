<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Downloads MaxMind's GeoLite2-City database and swaps it into place for
 * JeffersonGoncalves\VisitorFingerprint's MaxMind GeoIP driver (config
 * visitor-fingerprint.geoip.maxmind_database_path). MaxMind reissues
 * GeoLite2 roughly twice a week as IP ranges get reassigned — scheduled
 * weekly in routes/console.php.
 *
 * The existing database is only replaced after a full, verified download +
 * extraction — a failed run (missing license key, network error, corrupt
 * archive) leaves visitor geolocation on the last-known-good database
 * instead of breaking it.
 */
class UpdateGeoIpDatabase extends Command
{
    protected $signature = 'geoip:update';

    protected $description = 'Download and install the latest MaxMind GeoLite2-City database';

    public function handle(): int
    {
        $licenseKey = config('services.maxmind.license_key');

        if (! is_string($licenseKey) || $licenseKey === '') {
            $this->error('MAXMIND_LICENSE_KEY is not set — get a free key at https://www.maxmind.com/en/geolite2/signup-form');

            return self::FAILURE;
        }

        $targetPath = (string) config('visitor-fingerprint.geoip.maxmind_database_path');
        $workDir = dirname($targetPath).'/.tmp-'.uniqid();

        try {
            @mkdir($workDir, 0755, recursive: true);

            $archivePath = $workDir.'/GeoLite2-City.tar.gz';
            $this->download($licenseKey, $archivePath);

            $mmdbPath = $this->extractMmdb($archivePath, $workDir);

            @mkdir(dirname($targetPath), 0755, recursive: true);
            // rename() is atomic on the same filesystem — readers never see a
            // half-written database.
            if (! rename($mmdbPath, $targetPath)) {
                throw new RuntimeException("Could not move the extracted database to {$targetPath}.");
            }

            $this->info("GeoLite2-City database updated at {$targetPath}.");

            return self::SUCCESS;
        } catch (RuntimeException $e) {
            Log::error('geoip:update failed', ['error' => $e->getMessage()]);
            $this->error($e->getMessage());

            return self::FAILURE;
        } finally {
            $this->cleanUp($workDir);
        }
    }

    private function download(string $licenseKey, string $destination): void
    {
        $response = Http::timeout(60)->get('https://download.maxmind.com/app/geoip_download', [
            'edition_id' => 'GeoLite2-City',
            'license_key' => $licenseKey,
            'suffix' => 'tar.gz',
        ]);

        if (! $response->successful()) {
            throw new RuntimeException("MaxMind download failed with HTTP {$response->status()}.");
        }

        file_put_contents($destination, $response->body());
    }

    /**
     * MaxMind ships GeoLite2-City_<date>.tar.gz containing one
     * GeoLite2-City_<date>/GeoLite2-City.mmdb — the exact date-stamped
     * folder name isn't predictable, so the file is located by extension
     * after extracting.
     */
    private function extractMmdb(string $archivePath, string $workDir): string
    {
        $phar = new \PharData($archivePath);
        $phar->decompress(); // .tar.gz -> .tar, written alongside it.

        $tarPath = substr($archivePath, 0, -3); // strip ".gz"
        (new \PharData($tarPath))->extractTo($workDir);

        $matches = glob($workDir.'/*/GeoLite2-City.mmdb');

        if ($matches === false || $matches === []) {
            throw new RuntimeException('Extracted archive did not contain a GeoLite2-City.mmdb file.');
        }

        return $matches[0];
    }

    private function cleanUp(string $workDir): void
    {
        if (! is_dir($workDir)) {
            return;
        }

        foreach (glob($workDir.'/*') ?: [] as $entry) {
            is_dir($entry) ? $this->removeDirectory($entry) : @unlink($entry);
        }

        @rmdir($workDir);
    }

    private function removeDirectory(string $dir): void
    {
        foreach (glob($dir.'/*') ?: [] as $entry) {
            is_dir($entry) ? $this->removeDirectory($entry) : @unlink($entry);
        }

        @rmdir($dir);
    }
}
