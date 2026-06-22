<?php

namespace App\Console\Commands;

use App\Services\Sync\Etl\AssetCategoryEtl;
use App\Services\Sync\Etl\AssetEtl;
use App\Services\Sync\Etl\AssetNameEtl;
use App\Services\Sync\Etl\AssetStatusEtl;
use App\Services\Sync\Etl\CityEtl;
use App\Services\Sync\Etl\CommercialContractEtl;
use App\Services\Sync\Etl\ContractEtl;
use App\Services\Sync\Etl\ContractMonthEtl;
use App\Services\Sync\Etl\ContractTypeEtl;
use App\Services\Sync\Etl\PackageEtl;
use App\Services\Sync\Etl\PaymentDetailEtl;
use App\Services\Sync\Etl\PriorityEtl;
use App\Services\Sync\Etl\ProjectEtl;
use App\Services\Sync\Etl\PropertyBuildingEtl;
use App\Services\Sync\Etl\PropertyEtl;
use App\Services\Sync\Etl\RegionEtl;
use App\Services\Sync\Etl\ServiceProviderEtl;
use App\Services\Sync\Etl\TableEtl;
use App\Services\Sync\Etl\UserEtl;
use App\Services\Sync\Etl\UserProjectEtl;
use App\Services\Sync\Etl\WorkOrderEtl;
use App\Services\OpenSearch\Indices\AssetIndex;
use App\Services\OpenSearch\Indices\CommercialContractIndex;
use App\Services\OpenSearch\Indices\ContractIndex;
use App\Services\OpenSearch\Indices\InstallmentIndex;
use App\Services\OpenSearch\Indices\ProjectIndex;
use App\Services\OpenSearch\Indices\PropertyIndex;
use App\Services\OpenSearch\Indices\UserIndex;
use App\Services\OpenSearch\Indices\WorkOrderIndex;
use App\Services\Sync\OsoolClient;
use App\Services\Sync\TableSync;
use Illuminate\Console\Command;

class SyncRun extends Command
{
    protected $signature = 'sync:run
        {resource=service-providers : Resource slug on the Osool API (kebab-case)}
        {--raw-table= : Override raw.<table> name (default: underscored resource)}
        {--no-etl : Skip the raw → marts transform step}
        {--no-reindex : Skip the OpenSearch reindex step (sync:cycle uses this and reindexes once at the end)}';

    /** Resource slug → ETL class. */
    private const ETL_MAP = [
        'service-providers'  => ServiceProviderEtl::class,
        'users'              => UserEtl::class,
        'projects-details'   => ProjectEtl::class,
        'user-projects'      => UserProjectEtl::class,
        'regions'            => RegionEtl::class,
        'cities'             => CityEtl::class,
        'properties'         => PropertyEtl::class,
        'property-buildings' => PropertyBuildingEtl::class,
        'asset-categories'   => AssetCategoryEtl::class,
        'asset-names'        => AssetNameEtl::class,
        'priorities'         => PriorityEtl::class,
        'work-orders'        => WorkOrderEtl::class,
        'asset-statuses'       => AssetStatusEtl::class,
        'assets'               => AssetEtl::class,
        'commercial-contracts' => CommercialContractEtl::class,
        'payment-details'      => PaymentDetailEtl::class,
        'contract-types'       => ContractTypeEtl::class,
        'contracts'            => ContractEtl::class,
        'contract-months'      => ContractMonthEtl::class,
        'packages'             => PackageEtl::class,
    ];

    /**
     * Resource slug → every OpenSearch index that must be rebuilt after this
     * resource changes. An index appears under a resource when its reindex SQL
     * denormalizes that resource's data (a baked-in label, a bridge array, or an
     * aggregate). Changing the resource without rebuilding these indices leaves
     * stale labels/counts in OpenSearch — see each index class's reindex() joins.
     */
    public const OS_REINDEX_MAP = [
        // Fact resources — own index, plus any index that aggregates them.
        'work-orders'          => [WorkOrderIndex::class, ContractIndex::class],      // ContractIndex aggregates fact_work_order (closed_wo_count, wo_total_cost)
        'assets'               => [AssetIndex::class],
        'commercial-contracts' => [CommercialContractIndex::class, InstallmentIndex::class, ProjectIndex::class], // InstallmentIndex joins fact_commercial_contract; ProjectIndex rolls up its lease money
        'payment-details'      => [InstallmentIndex::class],
        'contracts'            => [ContractIndex::class, ProjectIndex::class, PropertyIndex::class], // ProjectIndex rolls up dim_contract value; PropertyIndex counts maintenance contracts per property
        'contract-months'      => [ContractIndex::class],                             // ContractIndex aggregates fact_contract_month
        'properties'           => [PropertyIndex::class, AssetIndex::class, CommercialContractIndex::class, ProjectIndex::class, WorkOrderIndex::class], // property_name denormalized; ProjectIndex counts dim_property; WorkOrderIndex bakes city/region from property

        // Dimension / lookup resources — refresh every index that bakes in their labels.
        'users'                => [UserIndex::class, WorkOrderIndex::class, AssetIndex::class, PropertyIndex::class, CommercialContractIndex::class, InstallmentIndex::class, ProjectIndex::class], // full_name (incl. ProjectIndex owner_name)
        'user-projects'        => [ProjectIndex::class, UserIndex::class, WorkOrderIndex::class, AssetIndex::class, PropertyIndex::class, ContractIndex::class], // bridge_user_project → project_ids
        'projects-details'     => [ProjectIndex::class],
        'property-buildings'   => [PropertyIndex::class, WorkOrderIndex::class, AssetIndex::class], // building_name
        'regions'              => [PropertyIndex::class, WorkOrderIndex::class],      // region_name
        'cities'               => [PropertyIndex::class, UserIndex::class, WorkOrderIndex::class], // city_name
        'service-providers'    => [WorkOrderIndex::class, ContractIndex::class],      // service_provider_name
        'asset-categories'     => [WorkOrderIndex::class, AssetIndex::class],         // asset_category
        'asset-names'          => [WorkOrderIndex::class, AssetIndex::class],         // asset_name
        'priorities'           => [WorkOrderIndex::class],                            // priority_level
        'asset-statuses'       => [AssetIndex::class],                                // asset_status_name
        'contract-types'       => [ContractIndex::class],                             // contract_type_name
        // packages / lease-contract-details feed marts only — no OpenSearch index.
    ];

    /**
     * Snapshot-mode resources. Only set composite_pk for true bridge tables
     * (those whose raw.<table> has no `id`+`payload` shape).
     */
    private const SNAPSHOT_MAP = [
        'user-projects'  => ['composite_pk' => ['user_id', 'project_id']],
        'regions'        => [],
        'cities'         => [],
        'asset-statuses' => [],
        'contract-types' => [],
    ];

    protected $description = 'Pull one table from Osool-B2G into raw.<table> using HMAC auth.';

    public function handle(): int
    {
        // Backfills of big tables (work_orders ≈ 90k rows × 100+ columns) need more headroom.
        @ini_set('memory_limit', '2G');

        $resource = (string) $this->argument('resource');
        $rawTable = (string) ($this->option('raw-table') ?: str_replace('-', '_', $resource));

        $this->info("Sync » {$resource} → raw.{$rawTable}");

        // Health check first — cheapest way to validate the HMAC channel
        try {
            $client = OsoolClient::fromConfig();
            $health = $client->get('/api/dwh/health');
            $this->line('  · health: ' . json_encode($health));
        } catch (\Throwable $e) {
            $this->error('Health check failed: ' . $e->getMessage());
            return self::FAILURE;
        }

        $opts = isset(self::SNAPSHOT_MAP[$resource])
            ? array_merge(['mode' => 'snapshot'], self::SNAPSHOT_MAP[$resource])
            : [];

        $start = microtime(true);
        try {
            $sync = new TableSync($client);
            $sync->onProgress = fn (string $m) => $this->line($m);
            $result = $sync->run($resource, $rawTable, $opts);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\DB::table('dwh.sync_state')->updateOrInsert(
                ['table_name' => $rawTable],
                ['last_status' => 'error', 'last_error' => $e->getMessage(), 'last_run_at' => now(), 'updated_at' => now()]
            );
            $this->error('Sync failed: ' . $e->getMessage());
            return self::FAILURE;
        }
        $secs = round(microtime(true) - $start, 2);

        $this->info(sprintf(
            '✓ raw: %d pages, %d upserted, %d deleted in %ss (cursor: %s)',
            $result['pages'], $result['rows'], $result['deleted'], $secs, $result['next_cursor'] ?? '—'
        ));

        if (!$this->option('no-etl') && isset(self::ETL_MAP[$resource])) {
            $etlClass = self::ETL_MAP[$resource];
            $this->line("  · running ETL: {$etlClass}");
            try {
                /** @var TableEtl $etl */
                $etl = app($etlClass);
                $t0  = microtime(true);
                $out = $etl->transform();
                $this->info(sprintf(
                    '✓ marts: %d upserted, %d deleted in %ss',
                    $out['upserted'], $out['deleted'], round(microtime(true) - $t0, 2)
                ));
            } catch (\Throwable $e) {
                $this->error('ETL failed: ' . $e->getMessage());
                return self::FAILURE;
            }
        } elseif (!isset(self::ETL_MAP[$resource])) {
            $this->warn("  · no ETL registered for '{$resource}' — raw only.");
        }

        if (!$this->option('no-reindex') && isset(self::OS_REINDEX_MAP[$resource])) {
            foreach (array_unique(self::OS_REINDEX_MAP[$resource]) as $idxClass) {
                $this->line("  · reindexing OpenSearch: {$idxClass}");
                try {
                    $t0   = microtime(true);
                    $info = app($idxClass)->reindex();
                    $this->info(sprintf('✓ os: %d docs into %s in %ss', $info['docs'], $info['index'], round(microtime(true) - $t0, 2)));
                } catch (\Throwable $e) {
                    $this->error('Reindex failed: ' . $e->getMessage());
                    return self::FAILURE;
                }
            }
        }

        return self::SUCCESS;
    }
}
