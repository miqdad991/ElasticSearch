<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
        ALTER TABLE marts.fact_work_order
            ADD COLUMN IF NOT EXISTS supervisor_id BIGINT;

        CREATE INDEX IF NOT EXISTS ix_fwo_supervisor ON marts.fact_work_order(supervisor_id);
        SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
        DROP INDEX IF EXISTS marts.ix_fwo_supervisor;
        ALTER TABLE marts.fact_work_order DROP COLUMN IF EXISTS supervisor_id;
        SQL);
    }
};
