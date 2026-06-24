<?php

namespace App\Http\Controllers;

use App\Services\Depreciation\DepreciationQuery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use OpenSearch\Client;

class AssetsDashboardController extends Controller
{
    public function __construct(private Client $os) {}

    /** Build the active filter set from the request query string. */
    private function filters(Request $request): array
    {
        return array_filter([
            'asset_category_id' => $request->query('asset_category_id'),
            'asset_status_id'   => $request->query('asset_status_id'),
            'building_id'       => $request->query('building_id'),
            'has_status'        => $request->query('has_status'),
            'under_warranty'    => $request->query('under_warranty'),
        ], fn ($v) => $v !== null && $v !== '');
    }

    /** Translate filters (+ project scope) into an OpenSearch query. */
    private function osQuery(array $filters): array
    {
        $must = [];
        foreach ($filters as $f => $v) {
            $must[] = ['term' => [$f => in_array($v, ['true','false'], true) ? $v==='true' : (is_numeric($v) ? (int)$v : $v)]];
        }
        if ($pid = session('selected_project_id')) {
            $must[] = ['term' => ['project_ids' => (int) $pid]];
        }
        return $must ? ['bool' => ['must' => $must]] : ['match_all' => (object) []];
    }

    public function index(Request $request)
    {
        $index = config('opensearch.index_prefix', 'osool_') . 'assets';

        $filters = $this->filters($request);
        $query   = $this->osQuery($filters);

        $resp = $this->os->search([
            'index' => $index,
            'body'  => [
                'track_total_hits' => true,
                'size'  => 50,
                'query' => $query,
                'sort'  => [['created_at' => 'desc']],
                'aggs'  => [
                    'sum_value'       => ['sum' => ['field' => 'purchase_amount']],
                    'with_status'     => ['filter' => ['term' => ['has_status' => true]]],
                    'without_status'  => ['filter' => ['term' => ['has_status' => false]]],
                    'under_warranty'  => ['filter' => ['term' => ['under_warranty' => true]]],
                    'distinct_cat'    => ['cardinality' => ['field' => 'asset_category_id']],
                    'distinct_bldg'   => ['cardinality' => ['field' => 'building_id']],
                    'avg_availability'=> ['avg' => ['field' => 'uptime_pct']],
                    'monthly'         => ['terms' => ['field' => 'created_year_month', 'size' => 60, 'order' => ['_key' => 'asc']]],
                    'by_category'     => ['terms' => ['field' => 'asset_category', 'size' => 15]],
                    'by_status'       => ['terms' => ['field' => 'asset_status_name', 'size' => 15]],
                    'by_building'     => ['terms' => ['field' => 'building_name', 'size' => 15]],
                    'by_name'         => ['terms' => ['field' => 'asset_name', 'size' => 15]],
                    'by_manufacturer' => ['terms' => ['field' => 'manufacturer_name', 'size' => 10]],
                    // Uptime vs Downtime by Asset Category — average % per category.
                    'availability'    => [
                        'terms' => ['field' => 'asset_category', 'size' => 20, 'order' => ['avg_down' => 'desc']],
                        'aggs'  => [
                            'avg_up'   => ['avg' => ['field' => 'uptime_pct']],
                            'avg_down' => ['avg' => ['field' => 'downtime_pct']],
                        ],
                    ],
                ],
            ],
        ]);

        $hits = $resp['hits']['hits'] ?? [];
        $aggs = $resp['aggregations'] ?? [];

        $avail = $aggs['avg_availability']['value'] ?? null;

        $cards = [
            'Total Assets'     => $resp['hits']['total']['value'] ?? 0,
            'Avg Availability' => $avail !== null ? round($avail, 1) . '%' : '—',
            'Categories'       => $aggs['distinct_cat']['value']  ?? 0,
            'Buildings'        => $aggs['distinct_bldg']['value'] ?? 0,
            'With Status'      => $aggs['with_status']['doc_count']    ?? 0,
            'No Status'        => $aggs['without_status']['doc_count'] ?? 0,
            'Under Warranty'   => $aggs['under_warranty']['doc_count'] ?? 0,
            'Total Value (SAR)'=> round($aggs['sum_value']['value'] ?? 0, 2),
        ];

        $bucket = fn (string $k) => collect($aggs[$k]['buckets'] ?? [])
            ->map(fn ($b) => ['label' => (string) $b['key'], 'count' => $b['doc_count']])
            ->values();

        // Uptime vs Downtime by Asset Category (averages, asset count for the tooltip).
        $availability = collect($aggs['availability']['buckets'] ?? [])
            ->map(fn ($b) => [
                'label'    => (string) $b['key'],
                'uptime'   => round($b['avg_up']['value']   ?? 0, 1),
                'downtime' => round($b['avg_down']['value'] ?? 0, 1),
                'count'    => $b['doc_count'],
            ])->values();

        // Mirrored depreciation charts (computed in Postgres as of today, sharing the
        // overlapping asset filters from this dashboard).
        $depFilters = array_intersect_key($filters, array_flip(['asset_category_id', 'asset_status_id', 'building_id']));
        $depQuery = new DepreciationQuery($depFilters + ['project_id' => session('selected_project_id')]);

        $categories = DB::table('marts.dim_asset_category')->where('is_deleted', false)
            ->select('asset_category_id', 'asset_category')->orderBy('asset_category')->get();
        $statuses = DB::table('marts.dim_asset_status')->where('is_deleted', false)
            ->select('asset_status_id', 'name')->orderBy('name')->get();
        $buildings = DB::table('marts.dim_property_building')->where('is_deleted', false)
            ->select('building_id', 'building_name')->orderBy('building_name')->get();

        return view('dashboards.assets', [
            'filters'    => $filters,
            'cards'      => $cards,
            'rows'       => collect($hits)->pluck('_source'),
            'categories' => $categories,
            'statuses'   => $statuses,
            'buildings'  => $buildings,
            'charts'  => [
                'monthly'      => $bucket('monthly'),
                'by_category'  => $bucket('by_category'),
                'by_status'    => $bucket('by_status'),
                'by_building'  => $bucket('by_building'),
                'by_name'      => $bucket('by_name'),
                'by_manufac'   => $bucket('by_manufacturer'),
                'availability' => $availability,
                'dep_by_property'    => $depQuery->byProperty(),
                'dep_remaining_life' => $depQuery->usefulLifeBuckets(),
            ],
        ]);
    }

    /**
     * Export the "Assets availability" table to an Excel-readable file.
     * Dependency-free CSV with a UTF-8 BOM so Excel renders the Arabic headers.
     */
    public function exportAvailability(Request $request)
    {
        $index = config('opensearch.index_prefix', 'osool_') . 'assets';

        $resp = $this->os->search([
            'index' => $index,
            'body'  => [
                'size'    => 10000,
                'query'   => $this->osQuery($this->filters($request)),
                'sort'    => [['asset_category' => 'asc'], ['asset_id' => 'asc']],
                '_source' => [
                    'asset_id', 'asset_name', 'asset_status_name', 'asset_category',
                    'property_name', 'unit_id', 'total_uptime', 'total_downtime', 'last_downtime_at',
                ],
            ],
        ]);

        $fmtDur = function ($s) {
            if ($s === null || $s === '') return '';
            $s = (int) $s;
            $d = intdiv($s, 86400); $h = intdiv($s % 86400, 3600); $m = intdiv($s % 3600, 60);
            $parts = [];
            if ($d) $parts[] = "{$d}d";
            if ($h) $parts[] = "{$h}h";
            if ($m || !$parts) $parts[] = "{$m}m";
            return implode(' ', $parts);
        };

        $headers = [
            __('assets.col_av_name'), __('assets.col_av_id'), __('assets.col_av_status'),
            __('assets.col_av_category'), __('assets.col_av_property'), __('assets.col_av_unit'),
            __('assets.col_av_uptime'), __('assets.col_av_downtime'), __('assets.col_av_last_down'),
        ];

        $rows = collect($resp['hits']['hits'] ?? [])->pluck('_source');

        $callback = function () use ($headers, $rows, $fmtDur) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM for Excel
            fputcsv($out, $headers);
            foreach ($rows as $r) {
                $last = !empty($r['last_downtime_at'])
                    ? \Illuminate\Support\Carbon::parse($r['last_downtime_at'])->format('Y-m-d H:i')
                    : '';
                fputcsv($out, [
                    $r['asset_name']        ?? '',
                    $r['asset_id']          ?? '',
                    $r['asset_status_name'] ?? '',
                    $r['asset_category']    ?? '',
                    $r['property_name']     ?? '',
                    $r['unit_id']           ?? '',
                    $fmtDur($r['total_uptime']   ?? null),
                    $fmtDur($r['total_downtime'] ?? null),
                    $last,
                ]);
            }
            fclose($out);
        };

        return response()->streamDownload($callback, 'assets-availability.csv', [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="assets-availability.csv"',
        ]);
    }
}
