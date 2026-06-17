<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SyncCycle extends Command
{
    protected $signature = 'sync:cycle
        {--only= : Comma-separated slugs to run (overrides stages)}
        {--skip= : Comma-separated slugs to skip}
        {--stop-on-error : Abort on first failure}';

    protected $description = 'Run every registered resource in dependency-stage order.';

    /**
     * Stages must run sequentially. Within a stage, resources are independent.
     * Mirrors the ingest order from docs/api/source-tables-per-dashboard.md.
     *
     * Note: 'lease-contract-details' is a raw-only feeder (no ETL of its own) —
     * it must land before 'commercial-contracts', whose ETL enriches from
     * raw.lease_contract_details. 'packages' has an ETL but no OpenSearch index.
     */
    private const STAGES = [
        1 => ['regions'],
        2 => ['cities', 'service-providers', 'users', 'projects-details', 'asset-statuses', 'contract-types'],
        3 => ['user-projects'],
        4 => ['properties'],
        5 => ['property-buildings', 'asset-categories', 'asset-names', 'priorities'],
        6 => ['work-orders', 'assets', 'lease-contract-details', 'commercial-contracts', 'contracts'],
        7 => ['payment-details', 'contract-months', 'packages'],
    ];

    public function handle(): int
    {
        $cycleId = (string) Str::uuid();
        $only    = $this->option('only') ? explode(',', (string) $this->option('only')) : null;
        $skip    = $this->option('skip') ? explode(',', (string) $this->option('skip')) : [];
        $stop    = (bool) $this->option('stop-on-error');

        Log::info('sync:cycle start', ['cycle_id' => $cycleId]);
        $this->info("sync:cycle {$cycleId}");

        $ok = 0; $failed = 0;
        $dirtyIndices = [];   // OpenSearch index classes to rebuild once, after all marts are loaded
        $start = microtime(true);

        foreach (self::STAGES as $stage => $resources) {
            foreach ($resources as $slug) {
                if ($only && !in_array($slug, $only, true)) continue;
                if (in_array($slug, $skip, true)) continue;

                $this->line("\n—— stage {$stage} · {$slug} ——");
                $t0 = microtime(true);

                // Defer reindexing: a single cycle touches many resources that share
                // indices (e.g. work_orders is denormalized into 8 of them), so we
                // rebuild each affected index ONCE at the end from the final marts.
                $exit = Artisan::call('sync:run', ['resource' => $slug, '--no-reindex' => true], $this->output);
                $dur  = round(microtime(true) - $t0, 2);

                if ($exit === self::SUCCESS) {
                    $ok++;
                    foreach (SyncRun::OS_REINDEX_MAP[$slug] ?? [] as $idxClass) {
                        $dirtyIndices[$idxClass] = true;
                    }
                    Log::info('sync:cycle resource ok', ['cycle_id' => $cycleId, 'stage' => $stage, 'resource' => $slug, 'duration_s' => $dur]);
                } else {
                    $failed++;
                    Log::warning('sync:cycle resource failed', ['cycle_id' => $cycleId, 'stage' => $stage, 'resource' => $slug, 'duration_s' => $dur]);
                    if ($stop) {
                        $this->error("Stopping cycle on first failure ({$slug}).");
                        return self::FAILURE;
                    }
                }
            }
        }

        // Single reindex pass — every index touched this cycle, rebuilt once from complete marts.
        foreach (array_keys($dirtyIndices) as $idxClass) {
            $this->line("\n—— reindex · {$idxClass} ——");
            $t0 = microtime(true);
            try {
                $info = app($idxClass)->reindex();
                $dur  = round(microtime(true) - $t0, 2);
                $this->info(sprintf('✓ os: %d docs into %s in %ss', $info['docs'], $info['index'], $dur));
                Log::info('sync:cycle reindex ok', ['cycle_id' => $cycleId, 'index' => $idxClass, 'docs' => $info['docs'], 'duration_s' => $dur]);
            } catch (\Throwable $e) {
                $failed++;
                $this->error("Reindex failed ({$idxClass}): " . $e->getMessage());
                Log::warning('sync:cycle reindex failed', ['cycle_id' => $cycleId, 'index' => $idxClass, 'error' => $e->getMessage()]);
                if ($stop) {
                    return self::FAILURE;
                }
            }
        }

        $total = round(microtime(true) - $start, 2);
        $this->info("\n✓ cycle done: {$ok} ok, {$failed} failed in {$total}s");
        Log::info('sync:cycle end', ['cycle_id' => $cycleId, 'ok' => $ok, 'failed' => $failed, 'duration_s' => $total]);

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
