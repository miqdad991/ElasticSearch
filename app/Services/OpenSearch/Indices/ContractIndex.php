<?php

namespace App\Services\OpenSearch\Indices;

use App\Services\OpenSearch\IndexManager;
use Illuminate\Support\Facades\DB;

class ContractIndex
{
    public const ENTITY = 'contracts';

    public function __construct(private IndexManager $im) {}

    public function mapping(): array
    {
        return [
            'properties' => [
                'contract_id'          => ['type' => 'long'],
                'contract_number'      => ['type' => 'keyword'],
                'parent_contract_id'   => ['type' => 'long'],
                'is_subcontract'       => ['type' => 'boolean'],
                'owner_user_id'        => ['type' => 'long'],
                'project_ids'          => ['type' => 'integer'],
                'service_provider_id'  => ['type' => 'long'],
                'service_provider_name'=> ['type' => 'keyword'],
                'contract_type_id'     => ['type' => 'long'],
                'contract_type_name'   => ['type' => 'keyword'],
                'start_date'           => ['type' => 'date', 'format' => 'yyyy-MM-dd'],
                'end_date'             => ['type' => 'date', 'format' => 'yyyy-MM-dd'],
                'is_expired'           => ['type' => 'boolean'],
                'contract_value'       => ['type' => 'scaled_float', 'scaling_factor' => 100],
                'retention_percent'    => ['type' => 'float'],
                'discount_percent'     => ['type' => 'float'],
                'workers_count'        => ['type' => 'integer'],
                'supervisor_count'     => ['type' => 'integer'],
                'status'               => ['type' => 'short'],
                'is_active'            => ['type' => 'boolean'],
                'is_deleted'           => ['type' => 'boolean'],
                'scheduled_total'      => ['type' => 'scaled_float', 'scaling_factor' => 100],
                'paid_total'           => ['type' => 'scaled_float', 'scaling_factor' => 100],
                'pending_total'        => ['type' => 'scaled_float', 'scaling_factor' => 100],
                'overdue_total'        => ['type' => 'scaled_float', 'scaling_factor' => 100],
                'overdue_months'       => ['type' => 'integer'],
                'wo_total_cost'        => ['type' => 'scaled_float', 'scaling_factor' => 100],
                'closed_wo_count'      => ['type' => 'integer'],
            ],
        ];
    }

    public function reindex(?string $since = null, int $chunk = 1000): array
    {
        $newIndex = $since ? $this->im->aliasName(self::ENTITY)
                           : $this->im->createVersionedIndex(self::ENTITY, $this->mapping());

        $sql = <<<SQL
            WITH month_agg AS (
                SELECT
                    contract_id,
                    COALESCE(SUM(amount),0)                                                       AS scheduled_total,
                    COALESCE(SUM(amount) FILTER (WHERE is_paid),0)                                AS paid_total,
                    COALESCE(SUM(amount) FILTER (WHERE NOT is_paid),0)                            AS pending_total,
                    COALESCE(SUM(amount) FILTER (WHERE NOT is_paid AND month < CURRENT_DATE),0)   AS overdue_total,
                    COUNT(*) FILTER (WHERE NOT is_paid AND month < CURRENT_DATE)                  AS overdue_months
                FROM marts.fact_contract_month
                GROUP BY contract_id
            ),
            wo_agg AS (
                SELECT contract_id,
                       COUNT(*) FILTER (WHERE status_code = 4) AS closed_wo_count,
                       COALESCE(SUM(cost),0)                   AS wo_total_cost
                FROM marts.fact_work_order
                WHERE contract_id IS NOT NULL
                GROUP BY contract_id
            )
            SELECT
                dc.contract_id, dc.contract_number, dc.parent_contract_id,
                (dc.parent_contract_id IS NOT NULL) AS is_subcontract,
                dc.owner_user_id,
                dc.service_provider_id, sp.name AS service_provider_name,
                dc.contract_type_id, ct.name AS contract_type_name,
                dc.start_date, dc.end_date,
                (dc.end_date IS NOT NULL AND dc.end_date < CURRENT_DATE) AS is_expired,
                dc.contract_value, dc.retention_percent, dc.discount_percent,
                dc.workers_count, dc.supervisor_count,
                dc.status, dc.is_active, dc.is_deleted,
                COALESCE(ma.scheduled_total,0) AS scheduled_total,
                COALESCE(ma.paid_total,0)      AS paid_total,
                COALESCE(ma.pending_total,0)   AS pending_total,
                COALESCE(ma.overdue_total,0)   AS overdue_total,
                COALESCE(ma.overdue_months,0)  AS overdue_months,
                COALESCE(wa.wo_total_cost,0)   AS wo_total_cost,
                COALESCE(wa.closed_wo_count,0) AS closed_wo_count,
                COALESCE(
                    (SELECT array_agg(bup.project_id) FROM marts.bridge_user_project bup WHERE bup.user_id = dc.owner_user_id),
                    '{}'::int[]
                ) AS project_ids
            FROM marts.dim_contract dc
            LEFT JOIN marts.dim_service_provider sp ON sp.sp_id = dc.service_provider_id
            LEFT JOIN marts.dim_contract_type    ct ON ct.contract_type_id = dc.contract_type_id
            LEFT JOIN month_agg ma ON ma.contract_id = dc.contract_id
            LEFT JOIN wo_agg    wa ON wa.contract_id = dc.contract_id
            WHERE dc.is_current AND NOT dc.is_deleted
        SQL;

        $total = 0; $buffer = [];
        foreach (DB::cursor($sql) as $row) {
            $doc = (array) $row;
            $raw = $doc['project_ids'] ?? '{}';
            $doc['project_ids'] = is_array($raw) ? $raw
                : (($raw === '{}' || !$raw) ? [] : array_map('intval', explode(',', trim($raw,'{}'))));
            $buffer[] = ['index' => ['_index' => $newIndex, '_id' => $doc['contract_id']]];
            $buffer[] = $doc;
            $total++;
            if (count($buffer) >= $chunk * 2) { $this->im->bulk($buffer); $buffer = []; }
        }
        $this->im->bulk($buffer);
        if (!$since) $this->im->swapAlias(self::ENTITY, $newIndex);
        return ['index' => $newIndex, 'docs' => $total];
    }
}
