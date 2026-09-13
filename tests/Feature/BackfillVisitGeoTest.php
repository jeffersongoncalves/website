<?php

declare(strict_types=1);

use App\Console\Commands\BackfillVisitGeo;
use GeoIp2\Database\Reader;
use GeoIp2\Exception\AddressNotFoundException;
use GeoIp2\Model\City;
use JeffersonGoncalves\LaravelShortUrl\Models\Visit;
use MaxMind\Db\Reader\InvalidDatabaseException;

function fakeMaxmindCity(): City
{
    return new City([
        'city' => ['names' => ['en' => 'São Paulo']],
        'country' => ['iso_code' => 'BR', 'names' => ['en' => 'Brazil']],
        'subdivisions' => [['names' => ['en' => 'São Paulo']]],
        'location' => ['latitude' => -23.5505, 'longitude' => -46.6333, 'time_zone' => 'America/Sao_Paulo'],
    ]);
}

/**
 * Swaps BackfillVisitGeo::makeReader() so the command never needs a real
 * (binary) GeoLite2-City file — the Reader class itself is mocked, but the
 * GeoIp2\Model\City it returns is real, built from a raw MaxMind-shaped
 * array exactly as the actual database would produce.
 */
function bindFakeGeoReader(Closure $cityFor): void
{
    app()->bind(BackfillVisitGeo::class, function () use ($cityFor) {
        return new class($cityFor) extends BackfillVisitGeo
        {
            public function __construct(private Closure $cityFor)
            {
                parent::__construct();
            }

            protected function makeReader(string $path): Reader
            {
                $reader = Mockery::mock(Reader::class);
                $reader->shouldReceive('city')->andReturnUsing($this->cityFor);

                return $reader;
            }
        };
    });
}

it('fails cleanly when the database file is missing', function () {
    config(['visitor-fingerprint.geoip.maxmind_database_path' => '/does/not/exist.mmdb']);

    $this->artisan('short-url:backfill-geo')->assertFailed();
});

it('does nothing when no visit is missing a city', function () {
    config(['visitor-fingerprint.geoip.maxmind_database_path' => __FILE__]); // any existing file

    $this->artisan('short-url:backfill-geo')->assertSuccessful();
});

it('resolves and stores geo data for visits missing a city, skipping addresses the database has no block for', function () {
    config(['visitor-fingerprint.geoip.maxmind_database_path' => __FILE__]);

    $resolvable = Visit::factory()->create(['ip_anonymized' => '203.0.113.0', 'city' => null]);
    $unresolvable = Visit::factory()->create(['ip_anonymized' => '10.0.0.0', 'city' => null]);
    Visit::factory()->create(['ip_anonymized' => null, 'city' => null]); // no IP — untouched
    Visit::factory()->create(['ip_anonymized' => '198.51.100.0', 'city' => 'Already known']); // already filled — untouched

    bindFakeGeoReader(function (string $ip) use ($resolvable) {
        if ($ip === $resolvable->ip_anonymized) {
            return fakeMaxmindCity();
        }

        throw new AddressNotFoundException('not found');
    });

    $this->artisan('short-url:backfill-geo')
        ->assertSuccessful()
        ->expectsOutputToContain('Resolved a city for 1 of 2 visits.');

    expect($resolvable->refresh())
        ->city->toBe('São Paulo')
        ->country->toBe('Brazil')
        ->country_code->toBe('BR')
        ->region->toBe('São Paulo')
        ->latitude->toBe(-23.5505)
        ->longitude->toBe(-46.6333)
        ->timezone->toBe('America/Sao_Paulo');

    expect($unresolvable->refresh()->city)->toBeNull();
});

it('makeReader() constructs a real GeoIp2 Reader from the configured path', function () {
    // Every other test overrides this seam (see bindFakeGeoReader) since a
    // real Reader needs a real binary MMDB file. Exercise the un-overridden
    // method directly: passing a non-MMDB file still executes the `new
    // Reader($path)` call, it just throws while validating the file's
    // metadata — which is enough to cover the line without needing a real
    // GeoLite2 database fixture.
    $command = new BackfillVisitGeo;
    $method = new ReflectionMethod($command, 'makeReader');
    $method->setAccessible(true);

    expect(fn () => $method->invoke($command, __FILE__))->toThrow(InvalidDatabaseException::class);
});
