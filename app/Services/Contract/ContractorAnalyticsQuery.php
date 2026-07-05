<?php

namespace App\Services\Contract;

use Illuminate\Support\Facades\DB;

/**
 * Contractor (service-provider) analytics for the Execution Contracts dashboard,
 * computed against the DWH marts. Powers three views:
 *   - serviceCoverage() : contracts per named service/asset category
 *                         (bridge_contract_asset_category → dim_asset_category)
 *   - workforce()       : role headcount composition summed over contracts
 *                         (dim_contract.workers/supervisor/administrator/engineer_count)
 *   - portfolio()       : one row per contractor — contracts, value, managed properties,
 *                         completed/active jobs, on-time rate, avg score
 *
 * All three honour the dashboard filters (service provider, contract class, status)
 * plus project scope. Contractor city / specialization / stored rating do not exist
 * in the source and are omitted; on-time & avg-score are derived from work orders.
 */
class ContractorAnalyticsQuery
{
    private array $filters;

    public function __construct(array $filters = [])
    {
        $this->filters = $filters;
    }

    /** Contract distribution across named service/asset categories (for the coverage chart). */
    public function serviceCoverage(int $limit = 12): array
    {
        [$where, $bindings] = $this->whereParts();
        $sql = "
            SELECT ac.asset_category AS label, COUNT(DISTINCT dc.contract_id) AS cnt
            FROM marts.dim_contract dc
            LEFT JOIN marts.dim_contract_type ct ON ct.contract_type_id = dc.contract_type_id
            JOIN marts.bridge_contract_asset_category bca ON bca.contract_id = dc.contract_id
            JOIN marts.dim_asset_category ac ON ac.asset_category_id = bca.asset_category_id
            WHERE dc.is_current AND NOT dc.is_deleted {$where}
            GROUP BY ac.asset_category
            ORDER BY cnt DESC
            LIMIT {$limit}";

        return array_map(fn ($r) => [
            'label' => (string) $r->label,
            'count' => (int) $r->cnt,
        ], DB::select($sql, $bindings));
    }

    /** Workforce role headcount summed across the filtered contracts. */
    public function workforce(): array
    {
        [$where, $bindings] = $this->whereParts();
        $sql = "
            SELECT
                COALESCE(SUM(dc.workers_count), 0)       AS workers,
                COALESCE(SUM(dc.supervisor_count), 0)    AS supervisors,
                COALESCE(SUM(dc.administrator_count), 0) AS administrators,
                COALESCE(SUM(dc.engineer_count), 0)      AS engineers
            FROM marts.dim_contract dc
            LEFT JOIN marts.dim_contract_type ct ON ct.contract_type_id = dc.contract_type_id
            WHERE dc.is_current AND NOT dc.is_deleted {$where}";

        $r = DB::selectOne($sql, $bindings);

        return [
            'workers'        => (int) ($r->workers ?? 0),
            'supervisors'    => (int) ($r->supervisors ?? 0),
            'administrators' => (int) ($r->administrators ?? 0),
            'engineers'      => (int) ($r->engineers ?? 0),
        ];
    }

    /** Per-contractor portfolio rows, ranked by total contract value. */
    public function portfolio(int $limit = 30): array
    {
        [$where, $bindings] = $this->whereParts();
        $sql = "
            WITH sp_contracts AS (
                SELECT dc.service_provider_id AS sp_id,
                       COUNT(*)                              AS total_contracts,
                       COUNT(*) FILTER (WHERE dc.is_active)  AS active_contracts,
                       COALESCE(SUM(dc.contract_value), 0)   AS total_value
                FROM marts.dim_contract dc
                LEFT JOIN marts.dim_contract_type ct ON ct.contract_type_id = dc.contract_type_id
                WHERE dc.is_current AND NOT dc.is_deleted
                  AND dc.service_provider_id IS NOT NULL {$where}
                GROUP BY dc.service_provider_id
            ),
            sp_props AS (
                SELECT dc.service_provider_id AS sp_id,
                       COUNT(DISTINCT pb.property_id) AS managed_properties
                FROM marts.dim_contract dc
                JOIN marts.bridge_contract_property_building bcpb ON bcpb.contract_id = dc.contract_id
                JOIN marts.dim_property_building pb ON pb.building_id = bcpb.building_id
                WHERE dc.is_current AND NOT dc.is_deleted AND dc.service_provider_id IS NOT NULL
                GROUP BY dc.service_provider_id
            ),
            sp_wos AS (
                SELECT wo.service_provider_id AS sp_id,
                       COUNT(*) FILTER (WHERE wo.status_code = 4)                       AS completed_jobs,
                       COUNT(*) FILTER (WHERE wo.status_code <> 4 OR wo.status_code IS NULL) AS active_jobs,
                       AVG(CASE WHEN wo.status_code = 4 AND wo.target_at IS NOT NULL AND wo.job_completion_at IS NOT NULL
                                THEN CASE WHEN wo.job_completion_at <= wo.target_at THEN 1.0 ELSE 0.0 END END) AS on_time_ratio,
                       AVG(NULLIF(wo.score, 0)) AS avg_score
                FROM marts.fact_work_order wo
                WHERE wo.service_provider_id IS NOT NULL
                GROUP BY wo.service_provider_id
            )
            SELECT
                sp.name                              AS contractor,
                c.total_contracts,
                c.active_contracts,
                c.total_value,
                COALESCE(pr.managed_properties, 0)   AS managed_properties,
                COALESCE(w.completed_jobs, 0)        AS completed_jobs,
                COALESCE(w.active_jobs, 0)           AS active_jobs,
                w.on_time_ratio,
                w.avg_score
            FROM sp_contracts c
            JOIN marts.dim_service_provider sp ON sp.sp_id = c.sp_id AND NOT sp.is_deleted
            LEFT JOIN sp_props pr ON pr.sp_id = c.sp_id
            LEFT JOIN sp_wos   w  ON w.sp_id  = c.sp_id
            ORDER BY c.total_value DESC, sp.name
            LIMIT {$limit}";

        return array_map(fn ($r) => [
            'contractor'         => (string) $r->contractor,
            'total_contracts'    => (int) $r->total_contracts,
            'active_contracts'   => (int) $r->active_contracts,
            'total_value'        => round((float) $r->total_value, 2),
            'managed_properties' => (int) $r->managed_properties,
            'completed_jobs'     => (int) $r->completed_jobs,
            'active_jobs'        => (int) $r->active_jobs,
            'on_time_rate'       => $r->on_time_ratio === null ? null : round((float) $r->on_time_ratio * 100, 1),
            'rating'             => $r->avg_score === null ? null : round((float) $r->avg_score, 1),
        ], DB::select($sql, $bindings));
    }

    /**
     * WHERE fragment + bindings shared by all three queries. Conditions reference
     * `dc` (dim_contract) and `ct` (dim_contract_type), both aliased in every query.
     */
    private function whereParts(): array
    {
        $f = $this->filters;
        $w = [];
        $b = [];

        if (isset($f['service_provider_id']) && $f['service_provider_id'] !== '') {
            $w[] = 'dc.service_provider_id = ?';
            $b[] = (int) $f['service_provider_id'];
        }
        if (isset($f['status']) && $f['status'] !== '' && $f['status'] !== null) {
            $w[] = 'dc.status = ?';
            $b[] = (int) $f['status'];
        }
        // contract_class → dim_contract_type.is_advance (regular = false, advanced = true).
        // Only applied for the two valid values; the literal is not user text.
        if (in_array($f['contract_class'] ?? null, ['regular', 'advanced'], true)) {
            $w[] = 'ct.is_advance = ' . ($f['contract_class'] === 'advanced' ? 'true' : 'false');
        }
        if (! empty($f['project_id'])) {
            $w[] = 'dc.owner_user_id IN (SELECT user_id FROM marts.bridge_user_project WHERE project_id = ?)';
            $b[] = (int) $f['project_id'];
        }

        return [$w ? ' AND ' . implode(' AND ', $w) : '', $b];
    }
}
