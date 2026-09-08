<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;

Schedule::command('horizon:snapshot')
    ->everyFiveMinutes();

Schedule::command('projects:sync-stars')
    ->dailyAt('03:00')
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command('projects:sync-metrics')
    ->dailyAt('03:30')
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command('sitemap:generate')
    ->dailyAt('04:00')
    ->withoutOverlapping()
    ->runInBackground();

// MaxMind reissues GeoLite2 roughly twice a week; weekly keeps visitor city
// data fresh without hammering the download endpoint.
Schedule::command('geoip:update')
    ->weeklyOn(1, '02:00')
    ->withoutOverlapping()
    ->runInBackground();
