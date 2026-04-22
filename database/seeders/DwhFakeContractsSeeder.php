<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DwhFakeContractsSeeder extends Seeder
{
    public function run(): void
    {
        $faker = \Faker\Factory::create();

        DB::transaction(function () use ($faker) {
            // ---- 5 tenants (dim_user with user_type='tenant', then dim_tenant)
            $tenants = [];
            for ($i = 100; $i <= 104; $i++) {
                $tenants[] = [
                    'user_id'   => $i,
                    'email'     => "tenant{$i}@example.com",
                    'full_name' => $faker->name(),
                    'user_type' => 'tenant',
                    'status'    => 1,
                    'city_id'   => $faker->randomElement([12, 13]),
                    'created_at' => $faker->dateTimeBetween('-2 years')->format('Y-m-d H:i:sP'),
                ];
            }
            DB::table('marts.dim_user')->insertOrIgnore($tenants);

            $tenantRows = [];
            foreach ($tenants as $t) {
                $tenantRows[] = [
                    'tenant_id'           => $t['user_id'],
                    'tenant_phone_number' => $faker->phoneNumber(),
                    'tenant_email'        => $t['email'],
                    'tenant_cr_id'        => 'CR-' . $faker->numerify('#####'),
                ];
            }
            DB::table('marts.dim_tenant')->insertOrIgnore($tenantRows);

            // ---- 3 landlords
            $landlords = [];
            for ($i = 0; $i < 3; $i++) {
                $landlords[] = [
                    'name'  => $faker->company() . ' Holdings',
                    'phone' => $faker->phoneNumber(),
                    'email' => $faker->companyEmail(),
                    'cr_id' => 'LL-' . $faker->numerify('#####'),
                ];
            }
            DB::table('marts.dim_landlord')->insertOrIgnore($landlords);
            $landlordIds = DB::table('marts.dim_landlord')->pluck('landlord_id')->all();

            // ---- 60 commercial contracts spread across the 8 properties + 12 buildings
            $propertyIds = DB::table('marts.dim_property')->pluck('property_id')->all();
            $buildingByProp = DB::table('marts.dim_property_building')
                ->select('building_id', 'property_id')->get()
                ->groupBy('property_id')->map(fn ($g) => $g->pluck('building_id')->all())->all();

            $tenantIds = array_column($tenants, 'user_id');
            $contracts = [];
            for ($i = 1; $i <= 60; $i++) {
                $pid       = $faker->randomElement($propertyIds);
                $bid       = $faker->randomElement($buildingByProp[$pid] ?? [null]);
                $start     = $faker->dateTimeBetween('-2 years', 'now');
                $endsIn    = $faker->numberBetween(180, 1095);
                $end       = (clone $start)->modify("+{$endsIn} days");
                $amount    = $faker->numberBetween(20000, 600000);
                $contracts[] = [
                    'commercial_contract_id'  => 5000 + $i,
                    'reference_number'        => sprintf('CC-%06d', $i),
                    'contract_name'           => 'Lease ' . $faker->bothify('??-####'),
                    'contract_type'           => $faker->randomElement(['rent', 'lease']),
                    'tenant_id'               => $faker->randomElement($tenantIds),
                    'landlord_id'             => $faker->randomElement($landlordIds),
                    'property_id'             => $pid,
                    'building_id'             => $bid,
                    'unit_id'                 => $faker->numberBetween(1, 80),
                    'project_id'              => $faker->randomElement([67, 68]),
                    'created_by'              => 1,
                    'ejar_sync_status'        => $faker->randomElement(['synced_successfully','pending_sync','failed_sync','not_synced']),
                    'calendar_type'           => 'gregorian',
                    'start_date'              => $start->format('Y-m-d'),
                    'end_date'                => $end->format('Y-m-d'),
                    'signing_date'            => $start->format('Y-m-d'),
                    'payment_interval'        => $faker->randomElement(['monthly','quarterly','yearly']),
                    'amount'                  => $amount,
                    'security_deposit_amount' => round($amount * 0.1, 2),
                    'late_fees_charge'        => $faker->randomFloat(2, 0, 800),
                    'brokerage_fee'           => $faker->randomFloat(2, 0, 5000),
                    'retainer_fee'            => $faker->randomFloat(2, 0, 2000),
                    'payment_due'             => round($amount * $faker->randomFloat(2, 0, 0.4), 2),
                    'payment_overdue'         => round($amount * $faker->randomFloat(2, 0, 0.15), 2),
                    'currency'                => 'SAR',
                    'status'                  => $faker->randomElement([0, 1, 1, 1]),
                    'auto_renewal'            => $faker->boolean(40),
                    'is_unit_applies'         => $faker->boolean(20),
                    'is_dynamic_rent_applies' => false,
                    'is_deleted'              => false,
                    'created_at'              => $start->format('Y-m-d H:i:sP'),
                ];
            }
            DB::table('marts.fact_commercial_contract')->insertOrIgnore($contracts);
        });

        // ---- refresh the rollup MV so the reindex picks up the new totals
        DB::statement('REFRESH MATERIALIZED VIEW reports.mv_property_contract_rollup');

        $this->command->info('Seeded 5 tenants, 3 landlords, 60 commercial contracts. Rollup MV refreshed.');
    }
}
