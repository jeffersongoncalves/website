<?php

declare(strict_types=1);

use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;

function buildFakeGeoLiteArchive(string $destination): void
{
    // Source content and the tar being built live in separate directories —
    // building the tar into the same tree it's reading would otherwise
    // recursively pick up its own (still-growing) output file.
    $srcDir = sys_get_temp_dir().'/geoip-src-'.uniqid();
    $folder = $srcDir.'/GeoLite2-City_20260101';
    mkdir($folder, 0755, recursive: true);
    file_put_contents($folder.'/GeoLite2-City.mmdb', 'fake-mmdb-bytes');

    $outDir = sys_get_temp_dir().'/geoip-out-'.uniqid();
    mkdir($outDir, 0755, recursive: true);
    $tarPath = $outDir.'/GeoLite2-City.tar';

    $tar = new PharData($tarPath);
    $tar->buildFromDirectory($srcDir);
    $tar->compress(Phar::GZ);
    unset($tar);

    rename($tarPath.'.gz', $destination);

    unlink($tarPath);
    unlink($folder.'/GeoLite2-City.mmdb');
    rmdir($folder);
    rmdir($srcDir);
    rmdir($outDir);
}

it('fails cleanly with no license key configured', function () {
    config(['services.maxmind.license_key' => null]);

    $this->artisan('geoip:update')->assertFailed();
});

it('downloads, extracts and installs the database, replacing any previous one', function () {
    $target = sys_get_temp_dir().'/geoip-test-'.uniqid().'/GeoLite2-City.mmdb';
    config([
        'services.maxmind.license_key' => 'test-key',
        'short-url.tracking.geoip.maxmind_database_path' => $target,
    ]);

    $fixture = sys_get_temp_dir().'/geoip-archive-'.uniqid().'.tar.gz';
    buildFakeGeoLiteArchive($fixture);

    Http::swap(new Factory);
    Http::fake([
        'download.maxmind.com/*' => Http::response(file_get_contents($fixture), 200),
    ]);

    $this->artisan('geoip:update')->assertSuccessful();

    expect(file_get_contents($target))->toBe('fake-mmdb-bytes');

    unlink($fixture);
});
