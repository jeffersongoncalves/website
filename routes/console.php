<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Schedule::command('horizon:snapshot')
    ->everyFiveMinutes();

Schedule::command('projects:sync-metrics')
    ->dailyAt('03:30')
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command('sitemap:generate')
    ->dailyAt('04:00')
    ->withoutOverlapping()
    ->runInBackground();
