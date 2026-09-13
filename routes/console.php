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

Schedule::command('llms:generate')
    ->dailyAt('04:02')
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command('articles-feed:generate')
    ->dailyAt('04:04')
    ->withoutOverlapping()
    ->runInBackground();

// MaxMind reissues GeoLite2 roughly twice a week; weekly keeps visitor city
// data fresh without hammering the download endpoint.
Schedule::command('geoip:update')
    ->weeklyOn(1, '02:00')
    ->withoutOverlapping()
    ->runInBackground();

// Offsite (.env + database) backup to Google Drive — the native pg_dump@
// systemd timer on the server already covers local-disk DB backups; this
// covers full server loss.
Schedule::command('backup:run')
    ->dailyAt('01:00')
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command('backup:clean')
    ->dailyAt('01:30')
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command('backup:monitor')
    ->dailyAt('05:00')
    ->withoutOverlapping()
    ->runInBackground();

// Rolls yesterday's raw page_visits rows into daily_stats, then prunes rows
// past the retention window — keeps the table from growing unbounded.
// (short-url's equivalent command self-schedules at 02:00 from within the
// package — no entry needed here.)
Schedule::command('page-visits:aggregate-and-prune')
    ->dailyAt('02:30')
    ->withoutOverlapping()
    ->runInBackground();
