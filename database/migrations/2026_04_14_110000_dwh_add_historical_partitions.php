<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Extend partitions backward to 2015 on the three partitioned fact tables
 * so historical source data (Osool-B2G has 2018+ work orders) can load.
 */
return new class extends Migration {
    public function up(): void
    {
        $years = range(2015, 2023);

        foreach ($years as $y) {
            $start = "{$y}-01-01";
            $end   = ($y + 1) . "-01-01";

            foreach ([
                'fact_work_order'    => 'created_at',
                'fact_installment'   => 'payment_due_date',
                'fact_contract_month'=> 'month',
            ] as $parent => $_col) {
                $child = "{$parent}_y{$y}";
                $exists = DB::selectOne("SELECT to_regclass(:rel) AS r", ['rel' => "marts.{$child}"])->r;
                if (!$exists) {
                    DB::unprepared("CREATE TABLE marts.{$child} PARTITION OF marts.{$parent} FOR VALUES FROM ('{$start}') TO ('{$end}');");
                }
            }
        }
    }

    public function down(): void
    {
        $years = range(2015, 2023);
        foreach ($years as $y) {
            foreach (['fact_work_order','fact_installment','fact_contract_month'] as $parent) {
                DB::unprepared("DROP TABLE IF EXISTS marts.{$parent}_y{$y};");
            }
        }
    }
};
