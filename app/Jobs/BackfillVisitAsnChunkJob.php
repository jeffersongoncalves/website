<?php

declare(strict_types=1);

namespace App\Jobs;

use GeoIp2\Database\Reader;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Resolves isp/asn for a chunk of visit rows from the local MaxMind
 * GeoLite2-ASN database (jeffersongoncalves/laravel-visitor-fingerprint
 * 1.0.2+ only ever populated these from the City database, which has no
 * ISP/ASN fields at all — see MaxMindGeoIpDriver).
 *
 * Takes a model class + a plain array of IDs rather than serializing
 * Eloquent models, so this works identically for both
 * JeffersonGoncalves\LaravelPageVisits\Models\PageVisit and
 * JeffersonGoncalves\LaravelShortUrl\Models\Visit — same column shape,
 * same anonymized-IP convention, one job for both.
 *
 * Looks up `ip_anonymized`, not an original IP — only the anonymized address
 * survives on old rows (LGPD). It's a network prefix (last IPv4 octet
 * zeroed / IPv6 truncated to a /48), and ASN blocks are essentially never
 * finer than /24, so this resolves to the same organization a live lookup
 * on the original IP would have.
 */
class BackfillVisitAsnChunkJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /**
     * @param  class-string<Model>  $modelClass
     * @param  list<int>  $ids
     */
    public function __construct(public string $modelClass, public array $ids) {}

    public function handle(): void
    {
        $path = (string) config('visitor-fingerprint.geoip.maxmind_asn_database_path');

        if (! is_file($path)) {
            Log::warning('BackfillVisitAsnChunkJob: GeoLite2-ASN database not found, skipping chunk', [
                'model' => $this->modelClass,
                'path' => $path,
            ]);

            return;
        }

        $reader = $this->makeReader($path);
        $resolved = 0;

        $this->modelClass::query()->whereIn('id', $this->ids)->each(function (Model $visit) use ($reader, &$resolved): void {
            $ip = $visit->getAttribute('ip_anonymized');

            if (! is_string($ip) || $ip === '') {
                return;
            }

            try {
                $record = $reader->asn($ip);
            } catch (Throwable) {
                // Not in the database — private/reserved range, or an
                // address GeoLite2 has no block for. Leave the row null.
                return;
            }

            $visit->forceFill([
                'isp' => $record->autonomousSystemOrganization,
                'asn' => $record->autonomousSystemNumber !== null
                    ? "AS{$record->autonomousSystemNumber}"
                    : null,
            ])->save();

            if ($record->autonomousSystemOrganization !== null) {
                $resolved++;
            }
        });

        Log::info('BackfillVisitAsnChunkJob: chunk done', [
            'model' => $this->modelClass,
            'chunk_size' => count($this->ids),
            'resolved' => $resolved,
        ]);
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

    public function failed(?Throwable $e): void
    {
        Log::error('BackfillVisitAsnChunkJob failed', [
            'model' => $this->modelClass,
            'chunk_size' => count($this->ids),
            'error' => $e?->getMessage(),
        ]);
    }
}
