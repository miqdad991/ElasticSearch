<?php

namespace App\Services\Depreciation;

use Illuminate\Support\Facades\DB;

/**
 * Computes asset depreciation against marts.fact_asset for a given reporting date.
 *
 * Formulas (per OS3 Assets Depreciation Dashboard):
 *   depreciable_base = purchase_price − residual_value
 *   annual_dep       = straight line: base / useful_life_years
 *                      declining:     base × rate%
 *   elapsed_years    = (reporting_date − depreciation_start) in years
 *   accumulated_dep  = min(base, annual_dep × elapsed_years)     (capped)
 *   nbv              = purchase_price − accumulated_dep
 *   remaining_life   = max(0, useful_life_years − elapsed_years)
 *
 * Accumulated/NBV are date-dependent, so nothing is pre-stored — recomputed per call.
 * Per-asset maintenance columns are emitted as NULL pending OS3-3963 (WO↔asset link).
 */
class DepreciationQuery
{
    private string $reportingDate;
    private array $filters;

    public function __construct(array $filters = [])
    {
        $this->reportingDate = $filters['reporting_date'] ?? date('Y-m-d');
        unset($filters['reporting_date']);
        $this->filters = $filters;
    }

    public function reportingDate(): string
    {
        return $this->reportingDate;
    }

    /** KPI roll-up: total purchase value, total NBV, total accumulated dep, average useful life. */
    public function kpis(): array
    {
        [$where, $bindings] = $this->whereParts();
        $sql = $this->cte() . "
            SELECT
                count(*)                                                         AS n,
                COALESCE(sum(d.purchase_price), 0)                               AS total_purchase,
                COALESCE(sum(d.nbv), 0)                                          AS total_nbv,
                COALESCE(sum(d.accumulated_dep), 0)                             AS total_accum,
                COALESCE(sum(CASE WHEN d.useful_life_years > 0 THEN d.useful_life_years END), 0) AS sum_ul,
                count(*) FILTER (WHERE d.useful_life_years > 0)                  AS cnt_ul
            FROM dep d {$where}";

        $r = DB::selectOne($sql, array_merge([$this->reportingDate], $bindings));

        return [
            'count'           => (int) ($r->n ?? 0),
            'total_purchase'  => round((float) ($r->total_purchase ?? 0), 2),
            'total_nbv'       => round((float) ($r->total_nbv ?? 0), 2),
            'total_accum'     => round((float) ($r->total_accum ?? 0), 2),
            'avg_useful_life' => ($r->cnt_ul ?? 0) > 0 ? round($r->sum_ul / $r->cnt_ul, 1) : 0,
        ];
    }

    /** Detail rows for the depreciation table (and export). */
    public function rows(int $limit = 500): array
    {
        [$where, $bindings] = $this->whereParts();
        $sql = $this->cte() . "
            SELECT d.* FROM dep d {$where}
            ORDER BY d.property_name NULLS LAST, d.asset_id
            LIMIT {$limit}";

        return DB::select($sql, array_merge([$this->reportingDate], $bindings));
    }

    /** Bar chart: current-year vs accumulated depreciation per property. */
    public function byProperty(int $limit = 15): array
    {
        [$where, $bindings] = $this->whereParts();
        $sql = $this->cte() . "
            SELECT
                COALESCE(d.property_name, '—')        AS label,
                COALESCE(sum(d.annual_dep), 0)        AS current_year,
                COALESCE(sum(d.accumulated_dep), 0)   AS accumulated,
                count(*)                              AS cnt
            FROM dep d {$where}
            GROUP BY COALESCE(d.property_name, '—')
            ORDER BY accumulated DESC NULLS LAST
            LIMIT {$limit}";

        return array_map(fn ($r) => [
            'label'       => $r->label,
            'current'     => round((float) $r->current_year, 2),
            'accumulated' => round((float) $r->accumulated, 2),
            'count'       => (int) $r->cnt,
        ], DB::select($sql, array_merge([$this->reportingDate], $bindings)));
    }

    /** Bubble chart: asset counts grouped by remaining useful-life bucket. */
    public function usefulLifeBuckets(): array
    {
        [$where, $bindings] = $this->whereParts();
        $sql = $this->cte() . "
            SELECT
                CASE
                    WHEN d.remaining_useful_life < 3 THEN '0-2'
                    WHEN d.remaining_useful_life < 6 THEN '3-5'
                    WHEN d.remaining_useful_life < 9 THEN '6-8'
                    ELSE '9-10+'
                END  AS bucket,
                count(*) AS cnt
            FROM dep d {$where}
            GROUP BY 1";

        $raw = collect(DB::select($sql, array_merge([$this->reportingDate], $bindings)))
            ->keyBy('bucket');
        $total = $raw->sum('cnt') ?: 1;

        // Fixed bucket order, always present (zero-filled).
        return collect(['0-2', '3-5', '6-8', '9-10+'])->map(fn ($b) => [
            'label'   => $b,
            'count'   => (int) ($raw[$b]->cnt ?? 0),
            'percent' => round((($raw[$b]->cnt ?? 0) / $total) * 100, 1),
        ])->all();
    }

    /**
     * Grouped depreciation totals by a whitelisted dimension — purchase, NBV,
     * accumulated dep, current-year dep and asset count per group, ordered by NBV.
     * The grouping expression is chosen from a fixed map (never user input).
     */
    public function breakdown(string $dimension, int $limit = 12): array
    {
        $columns = [
            'supplier'     => "COALESCE(d.supplier_name, '—')",
            'category'     => "COALESCE(d.asset_category, '—')",
            'method'       => "COALESCE(NULLIF(d.depreciation_method, ''), '—')",
            'service_type' => "COALESCE(d.service_type, '—')",
            'zone'         => "COALESCE(d.zone_name, '—')",
        ];
        $expr = $columns[$dimension] ?? $columns['category'];

        [$where, $bindings] = $this->whereParts();
        $sql = $this->cte() . "
            SELECT
                {$expr}                              AS label,
                COALESCE(sum(d.purchase_price), 0)   AS purchase,
                COALESCE(sum(d.nbv), 0)              AS nbv,
                COALESCE(sum(d.accumulated_dep), 0)  AS accumulated,
                COALESCE(sum(d.annual_dep), 0)       AS current_year,
                count(*)                             AS cnt
            FROM dep d {$where}
            GROUP BY {$expr}
            ORDER BY nbv DESC NULLS LAST
            LIMIT {$limit}";

        return array_map(fn ($r) => [
            'label'       => $r->label,
            'purchase'    => round((float) $r->purchase, 2),
            'nbv'         => round((float) $r->nbv, 2),
            'accumulated' => round((float) $r->accumulated, 2),
            'current'     => round((float) $r->current_year, 2),
            'count'       => (int) $r->cnt,
        ], DB::select($sql, array_merge([$this->reportingDate], $bindings)));
    }

    /** Distinct option lists for the filter controls. */
    public static function filterOptions(): array
    {
        return [
            'properties' => DB::table('marts.dim_property')->where('is_deleted', false)
                ->select('property_id', 'property_name')->orderBy('property_name')->get(),
            'zones'      => DB::table('marts.dim_region')->where('is_deleted', false)
                ->select('region_id', 'name')->orderBy('name')->get(),
            'statuses'   => DB::table('marts.dim_asset_status')->where('is_deleted', false)
                ->select('asset_status_id', 'name')->orderBy('name')->get(),
            'suppliers'  => DB::table('marts.fact_asset as a')
                ->leftJoin('marts.dim_service_provider as sp', 'sp.sp_id', '=', 'a.supplier_id')
                ->selectRaw('DISTINCT COALESCE(a.supplier_name, sp.name) AS s')
                ->whereRaw('COALESCE(a.supplier_name, sp.name) IS NOT NULL')
                ->orderBy('s')->pluck('s'),
            'methods'    => DB::table('marts.fact_asset')->whereNotNull('depreciation_method')
                ->where('depreciation_method', '<>', '')->distinct()
                ->orderBy('depreciation_method')->pluck('depreciation_method'),
        ];
    }

    /** WHERE fragment + bindings from the active filters (columns exist on `dep`). */
    private function whereParts(): array
    {
        $f = $this->filters;
        $w = [];
        $b = [];

        $eq = function (string $key, string $col, bool $int = false) use ($f, &$w, &$b) {
            if (isset($f[$key]) && $f[$key] !== '' && $f[$key] !== null) {
                $w[] = "d.{$col} = ?";
                $b[] = $int ? (int) $f[$key] : $f[$key];
            }
        };

        $eq('property_id', 'property_id', true);
        $eq('region_id', 'region_id', true);
        $eq('unit_id', 'unit_id', true);
        $eq('supplier', 'supplier_name');
        $eq('service_type', 'service_type');
        $eq('depreciation_method', 'depreciation_method');
        $eq('asset_status_id', 'asset_status_id', true);
        $eq('asset_category_id', 'asset_category_id', true);
        $eq('building_id', 'building_id', true);

        if (! empty($f['project_id'])) {
            $w[] = 'd.owner_user_id IN (SELECT user_id FROM marts.bridge_user_project WHERE project_id = ?)';
            $b[] = (int) $f['project_id'];
        }

        return [$w ? ' WHERE ' . implode(' AND ', $w) : '', $b];
    }

    /** Reusable CTE chain ending in `dep` with all computed columns. One `?` (reporting date). */
    private function cte(): string
    {
        return <<<'SQL'
        WITH params AS (SELECT ?::date AS rdate),
        base AS (
            SELECT
                a.asset_id, a.asset_tag, a.asset_number, a.asset_symbol,
                a.owner_user_id, a.property_id, a.building_id, a.unit_id,
                a.asset_category_id, a.asset_status_id,
                a.purchase_amount AS purchase_price,
                a.installation_date, a.depreciation_start_date,
                a.useful_life_years, a.depreciation_method, a.depreciation_rate,
                COALESCE(a.residual_value, 0) AS residual_value,
                COALESCE(a.depreciation_start_date, a.installation_date, a.purchase_date) AS dep_start,
                p.property_name, p.region_id,
                r.name  AS zone_name,
                an.asset_name,
                ac.asset_category, ac.service_type,
                ast.name AS asset_status_name,
                COALESCE(a.supplier_name, sp.name) AS supplier_name,
                (SELECT rdate FROM params) AS rdate
            FROM marts.fact_asset a
            LEFT JOIN marts.dim_property         p   ON p.property_id      = a.property_id
            LEFT JOIN marts.dim_region           r   ON r.region_id        = p.region_id
            LEFT JOIN marts.dim_asset_name       an  ON an.asset_name_id   = a.asset_name_id
            LEFT JOIN marts.dim_asset_category   ac  ON ac.asset_category_id = a.asset_category_id
            LEFT JOIN marts.dim_asset_status     ast ON ast.asset_status_id  = a.asset_status_id
            LEFT JOIN marts.dim_service_provider sp  ON sp.sp_id           = a.supplier_id
        ),
        c1 AS (
            SELECT b.*,
                GREATEST(0, COALESCE((b.rdate - b.dep_start)::numeric / 365.25, 0)) AS elapsed_years,
                GREATEST(0, b.purchase_price - b.residual_value)                    AS depreciable_base
            FROM base b
        ),
        c2 AS (
            SELECT c1.*,
                CASE
                    WHEN lower(COALESCE(c1.depreciation_method, '')) ~ 'declin|reduc'
                        THEN c1.depreciable_base * COALESCE(c1.depreciation_rate, 0) / 100.0
                    WHEN COALESCE(c1.useful_life_years, 0) > 0
                        THEN c1.depreciable_base / c1.useful_life_years
                    ELSE 0
                END AS annual_dep
            FROM c1
        ),
        dep AS (
            SELECT c2.*,
                LEAST(c2.depreciable_base, c2.annual_dep * c2.elapsed_years)            AS accumulated_dep,
                c2.purchase_price - LEAST(c2.depreciable_base, c2.annual_dep * c2.elapsed_years) AS nbv,
                GREATEST(0, COALESCE(c2.useful_life_years, 0) - c2.elapsed_years)       AS remaining_useful_life,
                CASE WHEN c2.depreciation_start_date IS NOT NULL AND COALESCE(c2.useful_life_years, 0) > 0
                     THEN (c2.depreciation_start_date + (c2.useful_life_years::double precision * interval '1 year'))::date
                END AS depreciation_end_date,
                NULL::date    AS last_maintenance_date,
                NULL::numeric AS maintenance_cost_ytd,
                NULL::int     AS wo_count
            FROM c2
        )
        SQL;
    }
}
