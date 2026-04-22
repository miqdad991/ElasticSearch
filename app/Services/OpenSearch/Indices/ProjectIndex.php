<?php

namespace App\Services\OpenSearch\Indices;

use App\Services\OpenSearch\IndexManager;
use Illuminate\Support\Facades\DB;

class ProjectIndex
{
    public const ENTITY = 'projects';

    public function __construct(private IndexManager $im) {}

    public function mapping(): array
    {
        return [
            'properties' => [
                'project_id'         => ['type' => 'integer'],
                'project_name'       => ['type' => 'text', 'fields' => ['raw' => ['type' => 'keyword']]],
                'industry_type'      => ['type' => 'keyword'],
                'contract_status'    => ['type' => 'keyword'],
                'contract_start_date'=> ['type' => 'date', 'format' => 'yyyy-MM-dd'],
                'contract_end_date'  => ['type' => 'date', 'format' => 'yyyy-MM-dd'],
                'is_active'          => ['type' => 'boolean'],
                'is_deleted'         => ['type' => 'boolean'],
                'owner_name'         => ['type' => 'keyword'],
                'use_erp_module'     => ['type' => 'boolean'],
                'use_crm_module'     => ['type' => 'boolean'],
                'use_tenant_module'  => ['type' => 'boolean'],
                'use_beneficiary_module' => ['type' => 'boolean'],
                'property_count'     => ['type' => 'integer'],
                'sp_count'           => ['type' => 'integer'],
                'contract_value'     => ['type' => 'scaled_float', 'scaling_factor' => 100],
                'payment_due'        => ['type' => 'scaled_float', 'scaling_factor' => 100],
                'payment_overdue'    => ['type' => 'scaled_float', 'scaling_factor' => 100],
                'lease_value'        => ['type' => 'scaled_float', 'scaling_factor' => 100],
                'created_at'         => ['type' => 'date'],
            ],
        ];
    }

    public function reindex(?string $since = null, int $chunk = 1000): array
    {
        $newIndex = $since ? $this->im->aliasName(self::ENTITY)
                           : $this->im->createVersionedIndex(self::ENTITY, $this->mapping());

        // Refresh the source MV so we see the latest ETL output.
        DB::statement('REFRESH MATERIALIZED VIEW reports.mv_project_rollup');

        $sql = 'SELECT * FROM reports.mv_project_rollup';
        $total = 0; $buffer = [];
        foreach (DB::cursor($sql) as $row) {
            $doc = (array) $row;
            foreach (['created_at'] as $f) {
                if (!empty($doc[$f])) $doc[$f] = \Carbon\Carbon::parse($doc[$f])->utc()->format('Y-m-d\TH:i:s\Z');
            }
            $buffer[] = ['index' => ['_index' => $newIndex, '_id' => $doc['project_id']]];
            $buffer[] = $doc;
            $total++;
            if (count($buffer) >= $chunk * 2) { $this->im->bulk($buffer); $buffer = []; }
        }
        $this->im->bulk($buffer);
        if (!$since) $this->im->swapAlias(self::ENTITY, $newIndex);
        return ['index' => $newIndex, 'docs' => $total];
    }
}
