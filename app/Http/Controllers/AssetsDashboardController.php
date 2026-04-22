<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use OpenSearch\Client;

class AssetsDashboardController extends Controller
{
    public function __construct(private Client $os) {}

    public function index(Request $request)
    {
        $index = config('opensearch.index_prefix', 'osool_') . 'assets';

        $filters = array_filter([
            'asset_category_id' => $request->query('asset_category_id'),
            'asset_status_id'   => $request->query('asset_status_id'),
            'building_id'       => $request->query('building_id'),
            'has_status'        => $request->query('has_status'),
            'under_warranty'    => $request->query('under_warranty'),
        ], fn ($v) => $v !== null && $v !== '');

        $must = [];
        foreach ($filters as $f => $v) {
            $must[] = ['term' => [$f => in_array($v, ['true','false'], true) ? $v==='true' : (is_numeric($v) ? (int)$v : $v)]];
        }
        if ($pid = session('selected_project_id')) {
            $must[] = ['term' => ['project_ids' => (int) $pid]];
        }
        $query = $must ? ['bool' => ['must' => $must]] : ['match_all' => (object) []];

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
                    'monthly'         => ['terms' => ['field' => 'created_year_month', 'size' => 60, 'order' => ['_key' => 'asc']]],
                    'by_category'     => ['terms' => ['field' => 'asset_category', 'size' => 15]],
                    'by_status'       => ['terms' => ['field' => 'asset_status_name', 'size' => 15]],
                    'by_building'     => ['terms' => ['field' => 'building_name', 'size' => 15]],
                    'by_name'         => ['terms' => ['field' => 'asset_name', 'size' => 15]],
                    'by_manufacturer' => ['terms' => ['field' => 'manufacturer_name', 'size' => 10]],
                ],
            ],
        ]);

        $hits = $resp['hits']['hits'] ?? [];
        $aggs = $resp['aggregations'] ?? [];

        $cards = [
            'Total Assets'     => $resp['hits']['total']['value'] ?? 0,
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
            ],
        ]);
    }
}
