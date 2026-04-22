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
