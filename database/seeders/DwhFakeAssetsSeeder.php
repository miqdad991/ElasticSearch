<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DwhFakeAssetsSeeder extends Seeder
{
    public function run(): void
    {
        $faker = \Faker\Factory::create();

        DB::transaction(function () use ($faker) {
            // 4 asset statuses
            $statuses = [
                ['asset_status_id' => 1, 'name' => 'Operational', 'color' => '#22c55e', 'owner_user_id' => 1],
                ['asset_status_id' => 2, 'name' => 'Under Repair','color' => '#f59e0b', 'owner_user_id' => 1],
                ['asset_status_id' => 3, 'name' => 'Out of Order','color' => '#ef4444', 'owner_user_id' => 1],
                ['asset_status_id' => 4, 'name' => 'Retired',     'color' => '#94a3b8', 'owner_user_id' => 1],
            ];
            DB::table('marts.dim_asset_status')->insertOrIgnore($statuses);

            $buildings = DB::table('marts.dim_property_building')
                ->select('building_id','property_id')->get();
            $manufs = ['Carrier','LG','Samsung','Daikin','Hitachi','Honeywell','Siemens','ABB'];

            $assets = [];
            for ($i = 1; $i <= 400; $i++) {
                $b = $faker->randomElement($buildings);
                $purchaseDate = $faker->dateTimeBetween('2024-01-15', '-1 month');
                $warrantyMo   = $faker->randomElement([0, 12, 24, 36, 60]);
                $warrantyEnd  = $warrantyMo ? (clone $purchaseDate)->modify("+{$warrantyMo} months") : null;
                $createdAt    = $faker->dateTimeBetween($purchaseDate, 'now');
                $status       = $faker->optional(0.85)->randomElement([1,2,3,4]);

                $assets[] = [
                    'asset_id'                 => $i,
                    'asset_tag'                => sprintf('AST-%05d', $i),
                    'asset_number'             => 'A-' . $i,
                    'barcode_value'            => 'BC-' . $faker->numerify('########'),
                    'owner_user_id'            => $faker->randomElement([2,3,4,5,6]),
                    'property_id'              => $b->property_id,
                    'building_id'              => $b->building_id,
                    'unit_id'                  => $faker->numberBetween(1, 50),
                    'floor'                    => (string) $faker->numberBetween(1, 12),
                    'room'                     => (string) $faker->numberBetween(100, 999),
                    'asset_category_id'        => $faker->randomElement([1,2,3,4,5]),
                    'asset_name_id'            => $faker->numberBetween(1, 12),
                    'asset_status_id'          => $status,
                    'asset_status_raw'         => $status !== null ? (string) $status : null,
                    'model_number'             => 'M-' . $faker->bothify('??###'),
                    'manufacturer_name'        => $faker->randomElement($manufs),
                    'purchase_date'            => $purchaseDate->format('Y-m-d'),
                    'purchase_amount'          => $faker->randomFloat(2, 500, 25000),
                    'warranty_duration_months' => $warrantyMo ?: null,
                    'warranty_end_date'        => $warrantyEnd?->format('Y-m-d'),
                    'threshold_unit_value'     => $faker->randomElement(['days','hours']),
                    'hours_per_day'            => 8,
                    'days_per_week'            => 5,
                    'linked_wo'                => $faker->boolean(60),
                    'converted_assets'         => 0,
                    'related_to'               => 2,
                    'created_at'               => $createdAt->format('Y-m-d H:i:sP'),
                    'source_updated_at'        => $createdAt->format('Y-m-d H:i:sP'),
                ];

                if (count($assets) >= 200) {
                    DB::table('marts.fact_asset')->insertOrIgnore($assets);
                    $assets = [];
                }
            }
            DB::table('marts.fact_asset')->insertOrIgnore($assets);
        });

        $this->command->info('Seeded 4 statuses + 400 assets.');
    }
}
