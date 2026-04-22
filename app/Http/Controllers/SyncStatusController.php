<?php

namespace App\Http\Controllers;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SyncStatusController extends Controller
{
    public function index()
    {
        $rows = DB::table('dwh.sync_state')
            ->orderBy('table_name')
            ->get();

        // Annotate each row with age/freshness
        $now = Carbon::now();
        $rows = $rows->map(function ($r) use ($now) {
            $lastRun = $r->last_run_at ? Carbon::parse($r->last_run_at) : null;
            $ageMin  = $lastRun ? (int) $lastRun->diffInMinutes($now) : null;
            $r->age_minutes = $ageMin;
            // Freshness traffic light: ok if <45 min, warn 45-90, stale > 90.
            $r->freshness = $ageMin === null ? 'never' : ($ageMin < 45 ? 'ok' : ($ageMin < 90 ? 'warn' : 'stale'));
            return $r;
        });

        // Latest raw log tail (last 30 lines) for quick debugging
        $logFile = storage_path('logs/sync-cycle.log');
        $logTail = null;
        if (is_file($logFile)) {
            $lines = @file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
            $logTail = array_slice($lines, -30);
        }

        return view('admin.sync-status', [
            'rows'    => $rows,
            'logTail' => $logTail,
        ]);
    }
}
