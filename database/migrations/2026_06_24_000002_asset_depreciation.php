<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * OS3-xxxx: Assets Depreciation Dashboard — base inputs.
 * Adds the depreciation/supplier source fields introduced on the WorkDo Asset
 * Profile. NBV, annual & accumulated depreciation are NOT stored — they are
 * computed at query time (they depend on the selected reporting date).
 */
return new class extends Migration {
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
        ALTER TABLE marts.fact_asset
            ADD COLUMN IF NOT EXISTS useful_life_years       NUMERIC(6,2),   -- years
            ADD COLUMN IF NOT EXISTS depreciation_method     TEXT,           -- straight_line | declining_balance | ...
            ADD COLUMN IF NOT EXISTS depreciation_rate       NUMERIC(6,3),   -- percent, for declining balance
            ADD COLUMN IF NOT EXISTS residual_value          NUMERIC(15,2),  -- salvage value
            ADD COLUMN IF NOT EXISTS depreciation_start_date DATE,
            ADD COLUMN IF NOT EXISTS installation_date       DATE,
            ADD COLUMN IF NOT EXISTS supplier_id             BIGINT,
            ADD COLUMN IF NOT EXISTS supplier_name           TEXT;
        SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
        ALTER TABLE marts.fact_asset
            DROP COLUMN IF EXISTS useful_life_years,
            DROP COLUMN IF EXISTS depreciation_method,
            DROP COLUMN IF EXISTS depreciation_rate,
            DROP COLUMN IF EXISTS residual_value,
            DROP COLUMN IF EXISTS depreciation_start_date,
            DROP COLUMN IF EXISTS installation_date,
            DROP COLUMN IF EXISTS supplier_id,
            DROP COLUMN IF EXISTS supplier_name;
        SQL);
    }
};
