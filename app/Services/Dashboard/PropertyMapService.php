<?php

namespace App\Services\Dashboard;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Builds `property_map_data` — the structure consumed by the Google Maps
 * property overlay on the MC dashboards.
 *
 * Output: array of groups keyed by "lat_lng"; each group is a list of
 * buildings sharing that coordinate. Mirrors the grouping Osool-B2G uses.
 */
class PropertyMapService
{
    public function buildingNames($projectId = null, int $limit = 60): array
    {
        $query = DB::table('marts.dim_property_building as b')
            ->join('marts.dim_property as p', 'p.property_id', '=', 'b.property_id')
            ->where('b.is_deleted', false)
            ->where('p.is_deleted', false)
            ->orderBy('b.building_name')
            ->limit($limit)
            ->select('b.building_name');

        if ($projectId) {
            $query->whereIn('p.owner_user_id', function ($q) use ($projectId) {
                $q->select('user_id')->from('marts.bridge_user_project')->where('project_id', $projectId);
            });
        }

        return $query->pluck('building_name')->filter()->values()->all();
    }

    public function build($projectId = null): array
    {
        $now    = Carbon::now();
        $last7  = $now->copy()->subDays(7)->toDateTimeString();
        $last30 = $now->copy()->subDays(30)->toDateTimeString();

        $bindings = [];
        $projectFilter = '';
        if ($projectId) {
            $projectFilter = ' AND p.owner_user_id IN (SELECT user_id FROM marts.bridge_user_project WHERE project_id = ?)';
            $bindings[] = (int) $projectId;
        }

        $sql = <<<SQL
            SELECT
                p.property_id,
                p.property_name,
                p.property_tag,
                p.compound_name,
                p.property_type::text  AS property_type,
                p.location_type::text  AS location_type,
                p.latitude             AS p_lat,
                p.longitude            AS p_lng,
                p.location_label       AS p_loc,
                p.buildings_count,
                b.building_id,
                b.building_name,
                b.latitude             AS b_lat,
                b.longitude            AS b_lng,
                b.location_label       AS b_loc
            FROM marts.dim_property p
            JOIN marts.dim_property_building b ON b.property_id = p.property_id
            WHERE NOT p.is_deleted
              AND NOT b.is_deleted
              $projectFilter
            ORDER BY p.property_id, b.building_id
        SQL;

        $rows = DB::select($sql, $bindings);
        if (!$rows) return [];

        $buildingIds = collect($rows)->pluck('building_id')->unique()->values()->all();

        $woCounts = DB::table('marts.fact_work_order')
            ->whereIn('property_id', $buildingIds)
            ->where('status_code', '<>', 5)
            ->selectRaw('property_id AS building_id')
            ->selectRaw("COUNT(*) FILTER (WHERE work_order_type = 'reactive')   AS reactive_total")
            ->selectRaw("COUNT(*) FILTER (WHERE work_order_type = 'preventive') AS preventive_total")
            ->selectRaw("COUNT(*) FILTER (WHERE work_order_type = 'reactive'   AND created_at >= ?) AS reactive_7d",    [$last7])
            ->selectRaw("COUNT(*) FILTER (WHERE work_order_type = 'preventive' AND created_at >= ?) AS preventive_7d",  [$last7])
            ->selectRaw("COUNT(*) FILTER (WHERE work_order_type = 'reactive'   AND created_at >= ?) AS reactive_30d",   [$last30])
            ->selectRaw("COUNT(*) FILTER (WHERE work_order_type = 'preventive' AND created_at >= ?) AS preventive_30d", [$last30])
            ->groupBy('property_id')
            ->get()
            ->keyBy('building_id');

        $byCategory = DB::table('marts.fact_work_order AS wo')
            ->leftJoin('marts.dim_asset_category AS ac', 'ac.asset_category_id', '=', 'wo.asset_category_id')
            ->whereIn('wo.property_id', $buildingIds)
            ->where('wo.status_code', '<>', 5)
            ->selectRaw('wo.property_id AS building_id')
            ->selectRaw("COALESCE(ac.asset_category, 'other') AS category_name")
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw('SUM(CASE WHEN wo.created_at >= ? THEN 1 ELSE 0 END) AS total_last_7_days',  [$last7])
            ->selectRaw('SUM(CASE WHEN wo.created_at >= ? THEN 1 ELSE 0 END) AS total_last_30_days', [$last30])
            ->groupBy('wo.property_id', 'category_name')
            ->get();

        $categoryByBuilding = [];
        foreach ($byCategory as $c) {
            $categoryByBuilding[$c->building_id][$c->category_name] = [
                'total'              => (int) $c->total,
                'total_last_7_days'  => (int) $c->total_last_7_days,
                'total_last_30_days' => (int) $c->total_last_30_days,
            ];
        }

        $groups = [];
        foreach ($rows as $r) {
            $useBuilding = ($r->property_type === 'complex' && $r->location_type === 'multiple_location');
            $lat = $useBuilding ? $r->b_lat : $r->p_lat;
            $lng = $useBuilding ? $r->b_lng : $r->p_lng;
            $loc = $useBuilding ? $r->b_loc : $r->p_loc;

            if ($lat === null || $lng === null) continue;

            $wo = $woCounts[$r->building_id] ?? null;
            $reactive    = $wo ? (int) $wo->reactive_total    : 0;
            $preventive  = $wo ? (int) $wo->preventive_total  : 0;
            $reactive7   = $wo ? (int) $wo->reactive_7d       : 0;
            $preventive7 = $wo ? (int) $wo->preventive_7d     : 0;
            $reactive30  = $wo ? (int) $wo->reactive_30d      : 0;
            $preventive30= $wo ? (int) $wo->preventive_30d    : 0;

            $tag = $r->property_type === 'complex'
                ? trim(($r->compound_name ?? $r->property_name) . ' ' . $r->building_name)
                : trim($r->building_name);

            $doc = [
                'latitude'       => (float) $lat,
                'longitude'      => (float) $lng,
                'property_tag'   => $tag,
                'location'       => $loc,
                'property_type'  => $r->property_type,
                'buildings_count'=> (int) $r->buildings_count,
                'building_id'    => (int) $r->building_id,
                'reactive_work_orders_count'    => $reactive,
                'preventive_work_orders_count'  => $preventive,
                'total_work_orders'             => $reactive + $preventive,
                'reactive_work_orders_count_last_7_days'   => $reactive7,
                'preventive_work_orders_count_last_7_days' => $preventive7,
                'total_work_orders_last_7_days'            => $reactive7 + $preventive7,
                'reactive_work_orders_count_last_30_days'   => $reactive30,
                'preventive_work_orders_count_last_30_days' => $preventive30,
                'total_work_orders_last_30_days'            => $reactive30 + $preventive30,
                'work_orders_by_category' => $categoryByBuilding[$r->building_id] ?? [],
            ];

            $key = $lat . '_' . $lng;
            $groups[$key][] = $doc;
        }

        return $groups;
    }
}
