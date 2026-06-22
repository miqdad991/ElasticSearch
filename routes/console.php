<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// 30-minute sync cycle from Osool-B2G. Runs serially (no overlap) and logs to storage/logs.
Schedule::command('sync:cycle')
    ->everyThirtyMinutes()
    ->withoutOverlapping(60)
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/sync-cycle.log'));

// Self-heal: rebuild any OpenSearch index whose alias has gone missing, so a
// dropped index recovers within minutes instead of 404-ing until the next time
// its source data changes. Cheap when nothing is missing (one getAlias/entity).
Schedule::command('os:ensure')
    ->everyFiveMinutes()
    ->withoutOverlapping(15)
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/os-ensure.log'));
