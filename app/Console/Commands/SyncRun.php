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
        {--no-etl : Skip the raw → marts transform step}';

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

    /** Resource slug → OpenSearch index class to refresh after ETL. */
    private const OS_REINDEX_MAP = [
        'properties'         => PropertyIndex::class,
        'property-buildings' => PropertyIndex::class,
        'work-orders'        => WorkOrderIndex::class,
        'assets'             => AssetIndex::class,
        'users'                => UserIndex::class,
        'projects-details'     => ProjectIndex::class,
        'user-projects'        => ProjectIndex::class,
        'commercial-contracts' => CommercialContractIndex::class,
        'payment-details'      => InstallmentIndex::class,
        'contracts'            => ContractIndex::class,
        'contract-months'      => ContractIndex::class,
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

        if (isset(self::OS_REINDEX_MAP[$resource])) {
            $idxClass = self::OS_REINDEX_MAP[$resource];
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

        return self::SUCCESS;
    }
}
