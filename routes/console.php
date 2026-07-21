<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Data pipeline schedule. Every entry below has exactly one implementation
// (see app/Console/Commands) — there is no second, parallel brief pipeline
// to silently go stale the way ai:refresh-syntheses did in the original build.

Schedule::command('quotes:refresh')
    ->everyMinute()
    ->withoutOverlapping(2);

Schedule::command('news:ingest')
    ->everyThirtyMinutes()
    ->withoutOverlapping(10);

Schedule::command('ohlc:daily-refresh')
    ->dailyAt('00:15')
    ->withoutOverlapping(30);

Schedule::command('analytics:refresh-briefs --tier=one')
    ->hourly()
    ->withoutOverlapping(30);

Schedule::command('analytics:refresh-briefs --tier=standard')
    ->everyFourHours()
    ->withoutOverlapping(30);
