<?php

namespace App\Console\Commands;

use App\Services\OpenSearch\IndexManager;
use App\Services\OpenSearch\Indices\AssetIndex;
use App\Services\OpenSearch\Indices\CommercialContractIndex;
use App\Services\OpenSearch\Indices\ContractIndex;
use App\Services\OpenSearch\Indices\InstallmentIndex;
use App\Services\OpenSearch\Indices\ProjectIndex;
use App\Services\OpenSearch\Indices\PropertyIndex;
use App\Services\OpenSearch\Indices\UserIndex;
use App\Services\OpenSearch\Indices\WorkOrderIndex;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Self-heal: rebuild any OpenSearch index whose alias has gone missing.
 *
 * The 30-min sync:cycle rebuilds indices only when their source data changed,
 * so if an index is dropped out-of-band (OpenSearch restart with an unassigned
 * shard, disk flood-stage, a stray dwh:wipe, manual delete) the alias can stay
 * gone until the next time that entity happens to change — meanwhile the
 * dashboards 404 with "no such index". Scheduling this every few minutes keeps
 * the gap to minutes and removes the need to reindex by hand.
 */
class OsEnsure extends Command
{
    protected $signature   = 'os:ensure {entity? : Limit to one entity; default checks all} {--chunk=1000}';
    protected $description = 'Rebuild any OpenSearch index whose alias is missing (self-heal).';

    /** entity slug → Index class. Keep in sync with OsReindex. */
    private const INDEX_CLASSES = [
        'work_orders'          => WorkOrderIndex::class,
        'properties'           => PropertyIndex::class,
        'assets'               => AssetIndex::class,
        'users'                => UserIndex::class,
        'commercial_contracts' => CommercialContractIndex::class,
        'installments'         => InstallmentIndex::class,
        'contracts'            => ContractIndex::class,
        'projects'             => ProjectIndex::class,
    ];

    public function handle(IndexManager $im): int
    {
        @ini_set('memory_limit', '2G');

        $only  = $this->argument('entity');
        $chunk = (int) $this->option('chunk');

        if ($only !== null && !isset(self::INDEX_CLASSES[$only])) {
            $this->error("Unknown entity: {$only}. Known: " . implode(', ', array_keys(self::INDEX_CLASSES)));
            return self::FAILURE;
        }

        $entities = $only !== null ? [$only] : array_keys(self::INDEX_CLASSES);
        $healed = 0; $failed = 0; $ok = 0;

        foreach ($entities as $entity) {
            if ($im->aliasExists($entity)) {
                $ok++;
                continue;
            }

            $this->warn("• {$entity}: alias missing — rebuilding…");
            Log::warning('os:ensure rebuilding missing index', ['entity' => $entity]);

            try {
                $t0   = microtime(true);
                $info = app(self::INDEX_CLASSES[$entity])->reindex(null, $chunk);
                $secs = round(microtime(true) - $t0, 2);
                $this->info(sprintf('  ✓ %d docs into %s in %ss', $info['docs'], $info['index'], $secs));
                Log::info('os:ensure healed index', ['entity' => $entity, 'index' => $info['index'], 'docs' => $info['docs'], 'duration_s' => $secs]);
                $healed++;
            } catch (\Throwable $e) {
                $this->error("  ✗ {$entity}: " . $e->getMessage());
                Log::error('os:ensure rebuild failed', ['entity' => $entity, 'error' => $e->getMessage()]);
                $failed++;
            }
        }

        $this->line(sprintf("\nos:ensure — %d ok, %d healed, %d failed", $ok, $healed, $failed));
        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
