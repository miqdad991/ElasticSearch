<?php

namespace App\Services\Property;

use Illuminate\Support\Facades\DB;

/**
 * Per-property portfolio breakdown, computed against the DWH marts.
 *
 * One row per property with cross-entity rollups:
 *   - assets:    COUNT + Σ purchase_amount            (marts.fact_asset, direct property_id)
 *   - contracts: active / total + Σ amount            (reports.mv_property_contract_rollup)
 *   - work:      count / open / Σ cost                (marts.fact_work_order via the building bridge —
 *                fact_work_order.property_id is actually a building_id → dim_property_building.property_id)
 *   - billing:   collected vs billed → collection %   (marts.fact_installment → commercial_contract.property_id)
 *
 * Columns the source data cannot yet supply (rent/sale/vacant units, occupancy rate,
 * assigned workers, services-per-property) are intentionally omitted — they require new
 * source fields (see OS3 assets/property backlog).
 */
class PropertyPortfolioQuery
{
    private array $filters;

    public function __construct(array $filters = [])
    {
        $this->filters = $filters;
    }

    /** Portfolio rows, ranked by economic weight (asset value + contract value). */
    public function rows(int $limit = 50): array
    {
        [$where, $bindings] = $this->whereParts();

        $sql = "
            SELECT
                p.property_id,
                p.property_name,
                p.property_type::text                 AS property_type,
                r.name                                AS region_name,
                c.name_en                             AS city_name,
                COALESCE(p.total_units, 0)            AS total_units,
                COALESCE(a.asset_count, 0)           AS asset_count,
                COALESCE(a.asset_value, 0)           AS asset_value,
                COALESCE(rl.active_contracts, 0)     AS active_contracts,
                COALESCE(rl.contract_count, 0)       AS contract_count,
                COALESCE(rl.total_budget, 0)         AS annual_income,
                COALESCE(w.wo_count, 0)              AS wo_count,
                COALESCE(w.open_wo, 0)               AS open_wo,
                COALESCE(w.wo_cost, 0)               AS total_expense,
                COALESCE(pay.collected, 0)           AS collected,
                COALESCE(pay.billed, 0)              AS billed
            FROM marts.dim_property p
            LEFT JOIN marts.dim_region r ON r.region_id = p.region_id
            LEFT JOIN marts.dim_city   c ON c.city_id   = p.city_id
            LEFT JOIN reports.mv_property_contract_rollup rl ON rl.property_id = p.property_id
            LEFT JOIN (
                SELECT property_id,
                       COUNT(*)                        AS asset_count,
                       COALESCE(SUM(purchase_amount), 0) AS asset_value
                FROM marts.fact_asset
                WHERE property_id IS NOT NULL
                GROUP BY property_id
            ) a ON a.property_id = p.property_id
            LEFT JOIN (
                SELECT pb.property_id,
                       COUNT(*)                                                   AS wo_count,
                       COUNT(*) FILTER (WHERE wo.status_code <> 4 OR wo.status_code IS NULL) AS open_wo,
                       COALESCE(SUM(wo.cost), 0)                                   AS wo_cost
                FROM marts.fact_work_order wo
                JOIN marts.dim_property_building pb ON pb.building_id = wo.property_id
                GROUP BY pb.property_id
            ) w ON w.property_id = p.property_id
            LEFT JOIN (
                SELECT cc.property_id,
                       COALESCE(SUM(i.amount) FILTER (WHERE i.is_paid), 0) AS collected,
                       COALESCE(SUM(i.amount), 0)                          AS billed
                FROM marts.fact_installment i
                JOIN marts.fact_commercial_contract cc ON cc.commercial_contract_id = i.commercial_contract_id
                WHERE NOT cc.is_deleted AND cc.property_id IS NOT NULL
                GROUP BY cc.property_id
            ) pay ON pay.property_id = p.property_id
            WHERE NOT p.is_deleted {$where}
            ORDER BY (COALESCE(a.asset_value, 0) + COALESCE(rl.total_budget, 0)) DESC, p.property_name
            LIMIT {$limit}";

        return array_map(function ($r) {
            $billed = (float) $r->billed;
            return [
                'property_name'    => $r->property_name,
                'property_type'    => $r->property_type,
                'region_name'      => $r->region_name,
                'city_name'        => $r->city_name,
                'total_units'      => (int) $r->total_units,
                'asset_count'      => (int) $r->asset_count,
                'asset_value'      => round((float) $r->asset_value, 2),
                'active_contracts' => (int) $r->active_contracts,
                'contract_count'   => (int) $r->contract_count,
                'annual_income'    => round((float) $r->annual_income, 2),
                'wo_count'         => (int) $r->wo_count,
                'open_wo'          => (int) $r->open_wo,
                'total_expense'    => round((float) $r->total_expense, 2),
                'collection_rate'  => $billed > 0 ? round((float) $r->collected / $billed * 100, 1) : null,
            ];
        }, DB::select($sql, $bindings));
    }

    /** WHERE fragment + bindings from the active dashboard filters (columns on dim_property). */
    private function whereParts(): array
    {
        $f = $this->filters;
        $w = [];
        $b = [];

        $eq = function (string $key, string $expr, bool $int = false) use ($f, &$w, &$b) {
            if (isset($f[$key]) && $f[$key] !== '' && $f[$key] !== null) {
                $w[] = "{$expr} = ?";
                $b[] = $int ? (int) $f[$key] : $f[$key];
            }
        };

        $eq('property_type', 'p.property_type::text');
        $eq('location_type', 'p.location_type::text');
        $eq('region_id', 'p.region_id', true);
        $eq('city_id', 'p.city_id', true);
        $eq('status', 'p.status', true);

        if (! empty($f['project_id'])) {
            $w[] = 'p.owner_user_id IN (SELECT user_id FROM marts.bridge_user_project WHERE project_id = ?)';
            $b[] = (int) $f['project_id'];
        }

        return [$w ? ' AND ' . implode(' AND ', $w) : '', $b];
    }
}
