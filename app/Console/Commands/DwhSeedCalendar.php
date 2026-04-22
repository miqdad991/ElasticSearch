<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DwhSeedCalendar extends Command
{
    protected $signature   = 'dwh:seed-calendar {--from=2015-01-01} {--to=2035-12-31}';
    protected $description = 'Populate marts.dim_date with one row per day in range.';

    public function handle(): int
    {
        $start = Carbon::parse($this->option('from'));
        $end   = Carbon::parse($this->option('to'));
        $this->info("Seeding dim_date {$start->toDateString()} .. {$end->toDateString()}");

        $rows = []; $total = 0;
        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            $rows[] = [
                'date_key'       => $d->toDateString(),
                'year'           => $d->year,
                'quarter'        => $d->quarter,
                'month'          => $d->month,
                'month_name'     => $d->format('F'),
                'week'           => (int) $d->isoWeek(),
                'day_of_month'   => $d->day,
                'day_of_week'    => $d->dayOfWeekIso,
                'is_weekend'     => in_array($d->dayOfWeekIso, [6, 7], true),
                'iso_year_month' => $d->format('Y-m'),
            ];
            if (count($rows) >= 500) {
                DB::table('marts.dim_date')->insertOrIgnore($rows);
                $total += count($rows);
                $rows = [];
            }
        }
        if ($rows) { DB::table('marts.dim_date')->insertOrIgnore($rows); $total += count($rows); }

        $this->info("✓ inserted/skipped {$total} rows");
        return self::SUCCESS;
    }
}
