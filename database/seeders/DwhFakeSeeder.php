<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DwhFakeSeeder extends Seeder
{
    public function run(): void
    {
        $faker = \Faker\Factory::create();

        DB::transaction(function () use ($faker) {
            // ---- dim_date: 2024-01-01 .. 2027-12-31
            $start = Carbon::create(2024, 1, 1);
            $end   = Carbon::create(2027, 12, 31);
            $rows  = [];
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
                    $rows = [];
                }
            }
            DB::table('marts.dim_date')->insertOrIgnore($rows);

            // ---- dim_region / dim_city
            DB::table('marts.dim_region')->insert([
                ['region_id' => 1, 'name' => 'Riyadh Region', 'code' => 'RI', 'country_id' => 1, 'status' => 1],
                ['region_id' => 2, 'name' => 'Makkah Region', 'code' => 'MK', 'country_id' => 1, 'status' => 1],
            ]);
            DB::table('marts.dim_city')->insert([
                ['city_id' => 12, 'name_en' => 'Riyadh', 'name_ar' => 'الرياض', 'region_id' => 1, 'country_id' => 1, 'status' => 1],
                ['city_id' => 13, 'name_en' => 'Jeddah', 'name_ar' => 'جدة',     'region_id' => 2, 'country_id' => 1, 'status' => 1],
            ]);

            // ---- dim_user: 1 admin + 5 building managers
            $users = [];
            $users[] = [
                'user_id' => 1, 'email' => 'admin@example.com', 'full_name' => 'Platform Admin',
                'user_type' => 'admin', 'status' => 1, 'city_id' => null,
                'created_at' => '2024-01-01 00:00:00+00',
            ];
            for ($i = 2; $i <= 6; $i++) {
                $users[] = [
                    'user_id'   => $i,
                    'email'     => "user{$i}@example.com",
                    'full_name' => $faker->name(),
                    'user_type' => 'building_manager',
                    'status'    => 1,
                    'city_id'   => $faker->randomElement([12, 13]),
                    'created_at' => $faker->dateTimeBetween('-2 years')->format('Y-m-d H:i:sP'),
                ];
            }
            DB::table('marts.dim_user')->insert($users);

            // ---- dim_project + bridge_user_project
            DB::table('marts.dim_project')->insert([
                ['project_id' => 67, 'owner_user_id' => 1, 'project_name' => 'Riyadh Portfolio',
                 'industry_type' => 'Commercial Real Estate', 'contract_status' => 'active',
                 'is_active' => true, 'is_deleted' => false, 'created_at' => '2024-01-05 08:00:00+00'],
                ['project_id' => 68, 'owner_user_id' => 1, 'project_name' => 'Jeddah Portfolio',
                 'industry_type' => 'Hospitality', 'contract_status' => 'active',
                 'is_active' => true, 'is_deleted' => false, 'created_at' => '2024-02-10 08:00:00+00'],
            ]);
            $bridge = [];
            foreach ([1, 2, 3, 4] as $uid) $bridge[] = ['user_id' => $uid, 'project_id' => 67];
            foreach ([1, 5, 6]       as $uid) $bridge[] = ['user_id' => $uid, 'project_id' => 68];
            DB::table('marts.bridge_user_project')->insert($bridge);

            // ---- dim_property + dim_property_building
            $props = [];
            for ($i = 0; $i < 8; $i++) {
                $pid = 2000 + $i;
                $props[] = [
                    'property_id'   => $pid,
                    'owner_user_id' => $faker->randomElement([2, 3, 4, 5, 6]),
                    'property_name' => $faker->company() . ' Tower',
                    'property_type' => $faker->randomElement(['building', 'complex']),
                    'location_type' => 'single_location',
                    'region_id'     => $faker->randomElement([1, 2]),
                    'city_id'       => $faker->randomElement([12, 13]),
                    'buildings_count' => $faker->numberBetween(1, 4),
                    'status'        => 1,
                    'created_at'    => $faker->dateTimeBetween('-3 years')->format('Y-m-d H:i:sP'),
                ];
            }
            DB::table('marts.dim_property')->insert($props);

            $buildings = [];
            $bid = 5000;
            foreach ($props as $p) {
                for ($k = 0; $k < $p['buildings_count']; $k++) {
                    $buildings[] = [
                        'building_id'   => $bid++,
                        'property_id'   => $p['property_id'],
                        'building_name' => 'Tower ' . chr(65 + $k),
                        'rooms_count'   => $faker->numberBetween(5, 30),
                        'created_at'    => $p['created_at'],
                    ];
                }
            }
            DB::table('marts.dim_property_building')->insert($buildings);

            // ---- dim_service_provider, dim_asset_category, dim_asset_name, dim_priority
            $sps = [];
            for ($i = 1; $i <= 6; $i++) {
                $sps[] = ['sp_id' => $i, 'name' => $faker->company() . ' Services', 'status' => 1];
            }
            DB::table('marts.dim_service_provider')->insert($sps);

            $cats = [
                ['asset_category_id' => 1, 'asset_category' => 'HVAC',         'service_type' => 'hard'],
                ['asset_category_id' => 2, 'asset_category' => 'Plumbing',     'service_type' => 'hard'],
                ['asset_category_id' => 3, 'asset_category' => 'Electrical',   'service_type' => 'hard'],
                ['asset_category_id' => 4, 'asset_category' => 'Cleaning',     'service_type' => 'soft'],
                ['asset_category_id' => 5, 'asset_category' => 'Landscaping',  'service_type' => 'soft'],
            ];
            DB::table('marts.dim_asset_category')->insert($cats);

            $names = [];
            for ($i = 1; $i <= 12; $i++) {
                $names[] = ['asset_name_id' => $i, 'asset_name' => $faker->word() . ' Unit ' . $i];
            }
            DB::table('marts.dim_asset_name')->insert($names);

            $prios = [
                ['priority_id' => 1, 'priority_level' => 'Low',      'service_window' => 72, 'service_window_type' => 'hours', 'response_time' => 24, 'response_time_type' => 'hours'],
                ['priority_id' => 2, 'priority_level' => 'Medium',   'service_window' => 48, 'service_window_type' => 'hours', 'response_time' => 8,  'response_time_type' => 'hours'],
                ['priority_id' => 3, 'priority_level' => 'High',     'service_window' => 24, 'service_window_type' => 'hours', 'response_time' => 4,  'response_time_type' => 'hours'],
                ['priority_id' => 4, 'priority_level' => 'Critical', 'service_window' => 4,  'service_window_type' => 'hours', 'response_time' => 1,  'response_time_type' => 'hours'],
            ];
            DB::table('marts.dim_priority')->insert($prios);

            // ---- fact_work_order: 500 rows across 2024-2026
            $statusLabels = [1 => 'Open', 2 => 'In Progress', 3 => 'On Hold', 4 => 'Closed', 5 => 'Deleted', 6 => 'Re-open', 7 => 'Warranty', 8 => 'Scheduled'];
            $journeys     = ['submitted', 'job_execution', 'job_evaluation', 'job_approval', 'finished'];
            $buildingIds  = array_column($buildings, 'building_id');

            $wos = [];
            for ($i = 1; $i <= 500; $i++) {
                $createdAt = $faker->dateTimeBetween('-2 years', 'now');
                $status    = $faker->numberBetween(1, 8);
                $wos[] = [
                    'wo_id'                  => $i,
                    'wo_number'              => sprintf('WO-%06d', $i),
                    'project_user_id'        => $faker->randomElement([2, 3, 4, 5, 6]),
                    'service_provider_id'    => $faker->randomElement([1, 2, 3, 4, 5, 6]),
                    'property_id'            => $faker->randomElement($buildingIds),
                    'unit_id'                => $faker->numberBetween(1, 50),
                    'asset_category_id'      => $faker->randomElement([1, 2, 3, 4, 5]),
                    'asset_name_id'          => $faker->numberBetween(1, 12),
                    'priority_id'            => $faker->numberBetween(1, 4),
                    'contract_type'          => $faker->randomElement(['regular', 'warranty']),
                    'work_order_type'        => $faker->randomElement(['reactive', 'preventive']),
                    'service_type'           => $faker->randomElement(['soft', 'hard']),
                    'workorder_journey'      => $faker->randomElement($journeys),
                    'status_code'            => $status,
                    'status_label'           => $statusLabels[$status],
                    'cost'                   => $faker->randomFloat(2, 50, 5000),
                    'score'                  => $faker->randomFloat(2, 0, 100),
                    'pass_fail'              => $faker->randomElement(['pass', 'fail', 'pending']),
                    'sla_response_time'      => 4,
                    'response_time_type'     => 'hours',
                    'sla_service_window'     => 48,
                    'service_window_type'    => 'hours',
                    'created_at'             => $createdAt->format('Y-m-d H:i:sP'),
                    'source_updated_at'      => $createdAt->format('Y-m-d H:i:sP'),
                ];
                if (count($wos) >= 200) {
                    DB::table('marts.fact_work_order')->insert($wos);
                    $wos = [];
                }
            }
            DB::table('marts.fact_work_order')->insert($wos);
        });

        $this->command->info('DWH fake data seeded.');
    }
}
