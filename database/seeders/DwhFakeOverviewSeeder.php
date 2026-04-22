<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DwhFakeOverviewSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $packages = [
                ['package_id' => 1, 'name' => 'Starter',         'pricing_model' => 'monthly', 'price' => 299,   'discount' => 0,  'status' => 'active',   'most_popular' => false, 'created_at' => '2024-03-01 00:00:00+00'],
                ['package_id' => 2, 'name' => 'Professional',    'pricing_model' => 'monthly', 'price' => 799,   'discount' => 10, 'status' => 'active',   'most_popular' => true,  'created_at' => '2024-03-01 00:00:00+00'],
                ['package_id' => 3, 'name' => 'Enterprise',      'pricing_model' => 'yearly',  'price' => 12000, 'discount' => 15, 'status' => 'active',   'most_popular' => false, 'created_at' => '2024-03-15 00:00:00+00'],
                ['package_id' => 4, 'name' => 'Enterprise Plus', 'pricing_model' => 'yearly',  'price' => 24000, 'discount' => 20, 'status' => 'active',   'most_popular' => false, 'created_at' => '2024-06-01 00:00:00+00'],
                ['package_id' => 5, 'name' => 'Legacy Basic',    'pricing_model' => 'monthly', 'price' => 149,   'discount' => 0,  'status' => 'inactive', 'most_popular' => false, 'created_at' => '2024-01-01 00:00:00+00'],
            ];
            DB::table('marts.dim_subscription_package')->insertOrIgnore($packages);

            // sp ↔ project mapping: every SP tied to 1 or 2 projects
            $mapping = [
                ['service_provider_id' => 1, 'project_id' => 67],
                ['service_provider_id' => 2, 'project_id' => 67],
                ['service_provider_id' => 3, 'project_id' => 67],
                ['service_provider_id' => 3, 'project_id' => 68],
                ['service_provider_id' => 4, 'project_id' => 68],
                ['service_provider_id' => 5, 'project_id' => 67],
                ['service_provider_id' => 5, 'project_id' => 68],
                ['service_provider_id' => 6, 'project_id' => 68],
            ];
            DB::table('marts.bridge_sp_project')->insertOrIgnore($mapping);
        });

        DB::statement('REFRESH MATERIALIZED VIEW reports.mv_overview_totals');
        DB::statement('REFRESH MATERIALIZED VIEW reports.mv_project_rollup');

        $this->command->info('Subscriptions + sp↔project mapping seeded. Overview MVs refreshed.');
    }
}
