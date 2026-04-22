<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DwhFakeExecutionContractsSeeder extends Seeder
{
    public function run(): void
    {
        $faker = \Faker\Factory::create();

        DB::transaction(function () use ($faker) {
            // ---- contract types
            $types = [
                ['contract_type_id' => 1, 'name' => 'Maintenance',       'slug' => 'maintenance'],
                ['contract_type_id' => 2, 'name' => 'Hard Services',     'slug' => 'hard'],
                ['contract_type_id' => 3, 'name' => 'Soft Services',     'slug' => 'soft'],
                ['contract_type_id' => 4, 'name' => 'Security',          'slug' => 'security'],
                ['contract_type_id' => 5, 'name' => 'Consulting',        'slug' => 'consulting'],
                ['contract_type_id' => 6, 'name' => 'Advance Payment',   'slug' => 'advance'],   // excluded
                ['contract_type_id' => 7, 'name' => 'Advance Retainer',  'slug' => 'advance2'],  // excluded
                ['contract_type_id' => 8, 'name' => 'Warranty',          'slug' => 'warranty'],
            ];
            DB::table('marts.dim_contract_type')->insertOrIgnore($types);

            // ---- 40 execution contracts (current, SCD2 head)
            $contracts = [];
            for ($i = 1; $i <= 40; $i++) {
                $cid   = 1000 + $i;
                $start = $faker->dateTimeBetween('2024-02-01', '2025-10-01');
                $end   = (clone $start)->modify('+' . $faker->numberBetween(6, 24) . ' months');
                $isSub = $i > 30 ? $faker->randomElement([1020, 1021, 1022]) : null;
                $value = $faker->randomFloat(2, 60000, 2500000);

                $contracts[] = [
                    'contract_id'         => $cid,
                    'valid_from'          => $start->format('Y-m-d H:i:sP'),
                    'valid_to'            => 'infinity',
                    'is_current'          => true,
                    'contract_number'     => sprintf('CONT%05d', $cid),
                    'parent_contract_id'  => $isSub,
                    'owner_user_id'       => 1,
                    'service_provider_id' => $faker->randomElement([1, 2, 3, 4, 5, 6]),
                    'contract_type_id'    => $faker->randomElement([1, 2, 3, 4, 5, 8]),
                    'start_date'          => $start->format('Y-m-d'),
                    'end_date'            => $end->format('Y-m-d'),
                    'contract_value'      => $value,
                    'retention_percent'   => $faker->randomElement([0, 5, 10]),
                    'discount_percent'    => 0,
                    'spare_parts_included'=> $faker->boolean(60),
                    'allow_subcontract'   => $faker->boolean(40),
                    'workers_count'       => $faker->numberBetween(2, 40),
                    'supervisor_count'    => $faker->numberBetween(1, 5),
                    'administrator_count' => $faker->numberBetween(0, 2),
                    'engineer_count'      => $faker->numberBetween(0, 4),
                    'comment'             => $faker->sentence(6),
                    'status'              => $faker->randomElement([0, 1, 1, 1]),
                    'is_deleted'          => false,
                    'source_updated_at'   => $start->format('Y-m-d H:i:sP'),
                ];
            }
            DB::table('marts.dim_contract')->insertOrIgnore($contracts);

            // ---- monthly payment schedule per contract
            $months = [];
            $mid = 50000;
            foreach ($contracts as $c) {
                $start = Carbon::parse($c['start_date']);
                $end   = Carbon::parse($c['end_date']);
                $n     = max(1, $start->diffInMonths($end));
                $per   = round((float) $c['contract_value'] / $n, 2);

                for ($m = 0; $m < $n; $m++) {
                    $d = (clone $start)->addMonths($m)->startOfMonth();
                    if ($d->year < 2024 || $d->year > 2030) continue;

                    $isPaid = $d->lt(Carbon::now()) ? $faker->boolean(75) : false;

                    $months[] = [
                        'contract_month_id'   => ++$mid,
                        'contract_id'         => $c['contract_id'],
                        'user_id'             => 1,
                        'month'               => $d->toDateString(),
                        'amount'              => $per,
                        'is_paid'             => $isPaid,
                        'is_extended_contract'=> false,
                        'created_at'          => $start->format('Y-m-d H:i:sP'),
                        'source_updated_at'   => $d->format('Y-m-d H:i:sP'),
                    ];

                    if (count($months) >= 300) {
                        DB::table('marts.fact_contract_month')->insertOrIgnore($months);
                        $months = [];
                    }
                }
            }
            DB::table('marts.fact_contract_month')->insertOrIgnore($months);
        });

        $this->command->info('Execution contracts + monthly schedule seeded.');
    }
}
