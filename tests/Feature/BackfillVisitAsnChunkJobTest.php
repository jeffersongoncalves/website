<?php

declare(strict_types=1);

use App\Jobs\BackfillVisitAsnChunkJob;
use GeoIp2\Database\Reader;
use GeoIp2\Exception\AddressNotFoundException;
use GeoIp2\Model\Asn;
use JeffersonGoncalves\LaravelShortUrl\Models\Visit;

function fakeMaxmindAsn(int $number, string $org): Asn
{
    return new Asn([
        'autonomous_system_number' => $number,
        'autonomous_system_organization' => $org,
        'ip_address' => '203.0.113.0',
        'prefix_len' => 24,
    ]);
}

/**
 * Swaps BackfillVisitAsnChunkJob::makeReader() so the job never needs a real
 * (binary) GeoLite2-ASN file — the Reader class itself is mocked, but the
 * GeoIp2\Model\Asn it returns is real, built from a raw MaxMind-shaped array
 * exactly as the actual database would produce.
 */
function fakeAsnJob(string $modelClass, array $ids, Closure $asnFor): BackfillVisitAsnChunkJob
{
    return new class($modelClass, $ids, $asnFor) extends BackfillVisitAsnChunkJob
    {
        public function __construct(string $modelClass, array $ids, private Closure $asnFor)
        {
            parent::__construct($modelClass, $ids);
        }

        protected function makeReader(string $path): Reader
        {
            $reader = Mockery::mock(Reader::class);
            $reader->shouldReceive('asn')->andReturnUsing($this->asnFor);

            return $reader;
        }
    };
}

it('does nothing when the asn database is not configured', function () {
    config(['visitor-fingerprint.geoip.maxmind_asn_database_path' => '/does/not/exist.mmdb']);

    $visit = Visit::factory()->create(['ip_anonymized' => '203.0.113.0', 'isp' => null]);

    (new BackfillVisitAsnChunkJob(Visit::class, [$visit->id]))->handle();

    expect($visit->refresh()->isp)->toBeNull();
});

it('resolves and stores isp/asn for visits, skipping addresses the database has no block for', function () {
    config(['visitor-fingerprint.geoip.maxmind_asn_database_path' => __FILE__]); // any existing file

    $resolvable = Visit::factory()->create(['ip_anonymized' => '203.0.113.0', 'isp' => null]);
    $unresolvable = Visit::factory()->create(['ip_anonymized' => '10.0.0.0', 'isp' => null]);

    $job = fakeAsnJob(Visit::class, [$resolvable->id, $unresolvable->id], function (string $ip) use ($resolvable) {
        if ($ip === $resolvable->ip_anonymized) {
            return fakeMaxmindAsn(15169, 'Google LLC');
        }

        throw new AddressNotFoundException('not found');
    });

    $job->handle();

    expect($resolvable->refresh())
        ->isp->toBe('Google LLC')
        ->asn->toBe('AS15169');

    expect($unresolvable->refresh()->isp)->toBeNull();
});

it('skips a visit with no stored ip', function () {
    config(['visitor-fingerprint.geoip.maxmind_asn_database_path' => __FILE__]);

    $visit = Visit::factory()->create(['ip_anonymized' => null, 'isp' => null]);

    $job = fakeAsnJob(Visit::class, [$visit->id], fn () => fakeMaxmindAsn(15169, 'Google LLC'));

    $job->handle();

    expect($visit->refresh()->isp)->toBeNull();
});
