<?php

namespace App\Console\Commands;

use App\Services\OpenSearch\Indices\AssetIndex;
use App\Services\OpenSearch\Indices\CommercialContractIndex;
use App\Services\OpenSearch\Indices\ContractIndex;
use App\Services\OpenSearch\Indices\InstallmentIndex;
use App\Services\OpenSearch\Indices\ProjectIndex;
use App\Services\OpenSearch\Indices\PropertyIndex;
use App\Services\OpenSearch\Indices\UserIndex;
use App\Services\OpenSearch\Indices\WorkOrderIndex;
use Illuminate\Console\Command;

class OsReindex extends Command
{
    protected $signature   = 'os:reindex {entity} {--since= : ISO timestamp for incremental} {--chunk=1000}';
    protected $description = 'Rebuild an OpenSearch index from Postgres';

    public function handle(): int
    {
        $entity = $this->argument('entity');
        $since  = $this->option('since');
        $chunk  = (int) $this->option('chunk');

        $start = microtime(true);

        $result = match ($entity) {
            'work_orders' => app(WorkOrderIndex::class)->reindex($since, $chunk),
            'properties'  => app(PropertyIndex::class)->reindex($since, $chunk),
            'assets'      => app(AssetIndex::class)->reindex($since, $chunk),
            'users'       => app(UserIndex::class)->reindex($since, $chunk),
            'commercial_contracts' => app(CommercialContractIndex::class)->reindex($since, $chunk),
            'installments'         => app(InstallmentIndex::class)->reindex($since, $chunk),
            'contracts'            => app(ContractIndex::class)->reindex($since, $chunk),
            'projects'             => app(ProjectIndex::class)->reindex($since, $chunk),
            default       => null,
        };

        if ($result === null) {
            $this->error("Unknown entity: {$entity}");
            return self::FAILURE;
        }

        $secs = round(microtime(true) - $start, 2);
        $this->info("Indexed {$result['docs']} docs into {$result['index']} in {$secs}s");
        return self::SUCCESS;
    }
}
