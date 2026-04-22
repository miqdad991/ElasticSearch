<?php

namespace App\Services\OpenSearch\Indices;

use App\Services\OpenSearch\IndexManager;
use Illuminate\Support\Facades\DB;

class WorkOrderIndex
{
    public const ENTITY = 'work_orders';

    public function __construct(private IndexManager $im) {}

    public function mapping(): array
    {
        return [
            'properties' => [
                'wo_id'                  => ['type' => 'long'],
                'wo_number'              => ['type' => 'keyword'],
                'project_user_id'        => ['type' => 'long'],
                'project_user_name'      => ['type' => 'keyword'],
                'project_ids'            => ['type' => 'long'],
                'service_provider_id'    => ['type' => 'long'],
                'service_provider_name'  => ['type' => 'keyword'],
                'supervisor_id'          => ['type' => 'long'],
                'supervisor_name'        => ['type' => 'keyword'],
                'property_id'            => ['type' => 'long'],
                'building_name'          => ['type' => 'keyword'],
                'unit_id'                => ['type' => 'integer'],
                'asset_category_id'      => ['type' => 'long'],
                'asset_category'         => ['type' => 'keyword'],
                'asset_name_id'          => ['type' => 'long'],
                'asset_name'             => ['type' => 'keyword'],
                'priority_id'            => ['type' => 'long'],
                'priority_level'         => ['type' => 'keyword'],
                'contract_id'            => ['type' => 'long'],
                'contract_type'          => ['type' => 'keyword'],
                'maintenance_request_id' => ['type' => 'integer'],
                'work_order_type'        => ['type' => 'keyword'],
                'service_type'           => ['type' => 'keyword'],
                'workorder_journey'      => ['type' => 'keyword'],
                'status_code'            => ['type' => 'short'],
                'status_label'           => ['type' => 'keyword'],
                'cost'                   => ['type' => 'scaled_float', 'scaling_factor' => 100],
                'score'                  => ['type' => 'float'],
                'pass_fail'              => ['type' => 'keyword'],
                'sla_response_time'      => ['type' => 'float'],
                'response_time_type'     => ['type' => 'keyword'],
                'sla_service_window'     => ['type' => 'integer'],
                'service_window_type'    => ['type' => 'keyword'],
                'start_date'             => ['type' => 'date', 'format' => 'yyyy-MM-dd'],
                'end_date'               => ['type' => 'date', 'format' => 'yyyy-MM-dd'],
                'target_at'              => ['type' => 'date'],
                'job_started_at'         => ['type' => 'date'],
                'job_submitted_at'       => ['type' => 'date'],
                'job_completion_at'      => ['type' => 'date'],
                'created_at'             => ['type' => 'date'],
                'created_year_month'     => ['type' => 'keyword'],
                'search_text'            => ['type' => 'text'],
            ],
        ];
    }

    /**
     * @param  string|null  $since  ISO timestamp; null means full reindex
     */
    public function reindex(?string $since = null, int $chunk = 1000): array
    {
        $newIndex = $since
            ? $this->im->aliasName(self::ENTITY) // partial: write into current alias target
            : $this->im->createVersionedIndex(self::ENTITY, $this->mapping());

        $sql = <<<SQL
            SELECT
                f.wo_id, f.wo_number,
                f.project_user_id,
                u.full_name                               AS project_user_name,
                COALESCE(
                    (SELECT array_agg(bup.project_id)
                       FROM marts.bridge_user_project bup
                      WHERE bup.user_id = f.project_user_id),
                    '{}'::int[]
                )                                         AS project_ids,
                f.service_provider_id, sp.name            AS service_provider_name,
                f.supervisor_id,      sv.full_name        AS supervisor_name,
                f.property_id, b.building_name,
                f.unit_id,
                f.asset_category_id, ac.asset_category,
                f.asset_name_id,     an.asset_name,
                f.priority_id,       pr.priority_level,
                f.contract_id, f.contract_type, f.maintenance_request_id,
                f.work_order_type, f.service_type, f.workorder_journey,
                f.status_code, f.status_label,
                f.cost, f.score, f.pass_fail,
                f.sla_response_time, f.response_time_type,
                f.sla_service_window, f.service_window_type,
                f.start_date, f.end_date,
                f.target_at, f.job_started_at, f.job_submitted_at, f.job_completion_at,
                f.created_at,
                to_char(f.created_at, 'YYYY-MM')          AS created_year_month
            FROM marts.fact_work_order f
            LEFT JOIN marts.dim_user             u  ON u.user_id            = f.project_user_id
            LEFT JOIN marts.dim_service_provider sp ON sp.sp_id             = f.service_provider_id
            LEFT JOIN marts.dim_user             sv ON sv.user_id           = f.supervisor_id
            LEFT JOIN marts.dim_property_building b ON b.building_id        = f.property_id
            LEFT JOIN marts.dim_asset_category   ac ON ac.asset_category_id = f.asset_category_id
            LEFT JOIN marts.dim_asset_name       an ON an.asset_name_id     = f.asset_name_id
            LEFT JOIN marts.dim_priority         pr ON pr.priority_id       = f.priority_id
        SQL;

        $bindings = [];
        if ($since) {
            $sql .= ' WHERE f.source_updated_at > ?';
            $bindings[] = $since;
        }
        $sql .= ' ORDER BY f.wo_id';

        $total  = 0;
        $buffer = [];
        $tsFields = ['target_at', 'job_started_at', 'job_submitted_at', 'job_completion_at', 'created_at'];
        foreach (DB::cursor($sql, $bindings) as $row) {
            $doc = (array) $row;
            $doc['project_ids'] = $this->parsePgIntArray($doc['project_ids'] ?? '{}');
            foreach ($tsFields as $f) {
                if (!empty($doc[$f])) {
                    $doc[$f] = \Carbon\Carbon::parse($doc[$f])->utc()->format('Y-m-d\TH:i:s\Z');
                }
            }
            $doc['search_text'] = trim(implode(' ', array_filter([
                $doc['wo_number'] ?? null,
                $doc['service_provider_name'] ?? null,
                $doc['building_name'] ?? null,
                $doc['asset_category'] ?? null,
                $doc['asset_name'] ?? null,
            ])));

            $buffer[] = ['index' => ['_index' => $newIndex, '_id' => $doc['wo_id']]];
            $buffer[] = $doc;
            $total++;

            if (count($buffer) >= $chunk * 2) {
                $this->im->bulk($buffer);
                $buffer = [];
            }
        }
        $this->im->bulk($buffer);

        if (!$since) {
            $this->im->swapAlias(self::ENTITY, $newIndex);
        }

        return ['index' => $newIndex, 'docs' => $total];
    }

    private function parsePgIntArray(string|array|null $raw): array
    {
        if (is_array($raw)) return $raw;
        if (!$raw || $raw === '{}') return [];
        return array_map('intval', explode(',', trim($raw, '{}')));
    }
}
