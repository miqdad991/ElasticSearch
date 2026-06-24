<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * OS3-xxxx: Asset Dashboard — Uptime/Downtime tracking.
 * Adds the up/down-time fields introduced on the WorkDo Asset Profile → Overview
 * so they can flow through the warehouse into the Assets Dashboard.
 */
return new class extends Migration {
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
        ALTER TABLE marts.fact_asset
            ADD COLUMN IF NOT EXISTS total_uptime     NUMERIC,        -- cumulative uptime, seconds
            ADD COLUMN IF NOT EXISTS total_downtime   NUMERIC,        -- cumulative downtime, seconds
            ADD COLUMN IF NOT EXISTS uptime_pct       NUMERIC(5,2),   -- 0..100
            ADD COLUMN IF NOT EXISTS downtime_pct     NUMERIC(5,2),   -- 0..100
            ADD COLUMN IF NOT EXISTS last_downtime_at TIMESTAMPTZ;
        SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
        ALTER TABLE marts.fact_asset
            DROP COLUMN IF EXISTS total_uptime,
            DROP COLUMN IF EXISTS total_downtime,
            DROP COLUMN IF EXISTS uptime_pct,
            DROP COLUMN IF EXISTS downtime_pct,
            DROP COLUMN IF EXISTS last_downtime_at;
        SQL);
    }
};
