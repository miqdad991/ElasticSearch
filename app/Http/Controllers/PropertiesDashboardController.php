<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use OpenSearch\Client;

class PropertiesDashboardController extends Controller
{
    public function __construct(private Client $os) {}

    public function index(Request $request)
    {
        $prefix = config('opensearch.index_prefix', 'osool_');
        $index  = $prefix . 'properties';

        $filters = array_filter([
            'property_type' => $request->query('property_type'),
            'location_type' => $request->query('location_type'),
            'region_id'     => $request->query('region_id'),
            'city_id'       => $request->query('city_id'),
            'status'        => $request->query('status'),
        ], fn ($v) => $v !== null && $v !== '');

        $must = [];
        foreach ($filters as $field => $value) {
            $must[] = ['term' => [$field => is_numeric($value) ? (int) $value : $value]];
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
                    'sum_buildings' => ['sum' => ['field' => 'buildings_count']],
                    'sum_units'     => ['sum' => ['field' => 'total_units']],
                    'sum_budget'    => ['sum' => ['field' => 'total_budget']],
                    'sum_contracts' => ['sum' => ['field' => 'contract_count']],
                    'sum_rent'      => ['sum' => ['field' => 'rent_count']],
                    'sum_lease'     => ['sum' => ['field' => 'lease_count']],
                    'sum_active_c'  => ['sum' => ['field' => 'active_contracts']],
                    'sum_auto_ren'  => ['sum' => ['field' => 'auto_renewal_count']],
                    'active'        => ['filter' => ['term' => ['is_active' => true]]],
                    'buildings_only'=> ['filter' => ['term' => ['property_type' => 'building']]],
                    'complexes'     => ['filter' => ['term' => ['property_type' => 'complex']]],
                    'monthly'       => ['terms' => ['field' => 'created_year_month', 'size' => 60, 'order' => ['_key' => 'asc']]],
                    'by_type'       => ['terms' => ['field' => 'property_type', 'size' => 5]],
                    'by_region'     => ['terms' => ['field' => 'region_name', 'size' => 15]],
                    'by_city'       => ['terms' => ['field' => 'city_name', 'size' => 15]],
                    'by_status'     => ['terms' => ['field' => 'is_active', 'size' => 5]],
                    'top_property_contracts' => [
                        'terms' => ['field' => 'property_name.raw', 'size' => 15, 'order' => ['c' => 'desc']],
                        'aggs'  => ['c' => ['sum' => ['field' => 'contract_count']]],
                    ],
                ],
            ],
        ]);

        // Cross-index cards (Total Assets, Total WOs, MR, SPs, WO Cost, Active Contracts, Ejar)
        $woResp = $this->safeSearch($prefix . 'work_orders', [
            'track_total_hits' => true,
            'size'  => 0,
            'query' => ['match_all' => (object) []],
            'aggs'  => [
                'total_cost'     => ['sum' => ['field' => 'cost']],
                'distinct_sps'   => ['cardinality' => ['field' => 'service_provider_id']],
                'distinct_mr'    => ['cardinality' => ['field' => 'maintenance_request_id']],
            ],
        ]);
        $assetsResp = $this->safeSearch($prefix . 'assets', [
            'track_total_hits' => true, 'size' => 0,
            'query' => ['match_all' => (object) []],
        ]);
        $ccResp = $this->safeSearch($prefix . 'commercial_contracts', [
            'size'  => 0,
            'query' => ['match_all' => (object) []],
            'aggs'  => [
                'active'  => ['filter' => ['term' => ['is_active' => true]]],
                'by_ejar' => ['terms' => ['field' => 'ejar_sync_status', 'size' => 10]],
            ],
        ]);

        $hits = $resp['hits']['hits'] ?? [];
        $aggs = $resp['aggregations'] ?? [];

        $cards = [
            'Total Properties'    => $resp['hits']['total']['value']      ?? 0,
            'Total Buildings'     => (int) ($aggs['sum_buildings']['value'] ?? 0),
            'Single Buildings'    => $aggs['buildings_only']['doc_count']  ?? 0,
            'Complexes'           => $aggs['complexes']['doc_count']       ?? 0,
            'Active Properties'   => $aggs['active']['doc_count']          ?? 0,
            'Total Contracts'     => (int) ($aggs['sum_contracts']['value'] ?? 0),
            'Active Contracts'    => $ccResp['aggregations']['active']['doc_count'] ?? 0,
            'Rent Contracts'      => (int) ($aggs['sum_rent']['value']     ?? 0),
            'Lease Contracts'     => (int) ($aggs['sum_lease']['value']    ?? 0),
            'Auto-Renewal'        => (int) ($aggs['sum_auto_ren']['value'] ?? 0),
            'Total Budget'        => round($aggs['sum_budget']['value']    ?? 0, 2),
            'Total Assets'        => $assetsResp['hits']['total']['value'] ?? 0,
            'Total Work Orders'   => $woResp['hits']['total']['value']     ?? 0,
            'Maintenance Requests'=> $woResp['aggregations']['distinct_mr']['value']  ?? 0,
            'Service Providers'   => $woResp['aggregations']['distinct_sps']['value'] ?? 0,
            'Total WO Cost'       => round($woResp['aggregations']['total_cost']['value'] ?? 0, 2),
        ];

        $bucket = function (string $key) use ($aggs) {
            return collect($aggs[$key]['buckets'] ?? [])
                ->map(function ($b) use ($key) {
                    $raw = $b['key'];
                    if ($key === 'by_status') {
                        $label = (int) $raw === 1 || $raw === true ? 'Active' : 'Inactive';
                    } elseif (is_bool($raw)) {
                        $label = $raw ? 'Active' : 'Inactive';
                    } else {
                        $label = (string) $raw;
                    }
                    return ['label' => $label, 'count' => $b['doc_count']];
                })
                ->values();
        };

        $topProps = collect($aggs['top_property_contracts']['buckets'] ?? [])
            ->map(fn ($b) => ['label' => (string) $b['key'], 'count' => (int) ($b['c']['value'] ?? 0)])
            ->values();

        $regions = DB::table('marts.dim_region')->where('is_deleted', false)
            ->select('region_id', 'name')->orderBy('name')->get();
        $cities  = DB::table('marts.dim_city')->where('is_deleted', false)
            ->select('city_id', 'name_en')->orderBy('name_en')->get();

        return view('dashboards.properties', [
            'filters'     => $filters,
            'cards'       => $cards,
            'regions'     => $regions,
            'cities'      => $cities,
            'rows'        => collect($hits)->pluck('_source'),
            'charts'  => [
                'monthly'   => $bucket('monthly'),
                'by_type'   => $bucket('by_type'),
                'by_region' => $bucket('by_region'),
                'by_city'   => $bucket('by_city'),
                'by_status' => $bucket('by_status'),
                'top_props' => $topProps,
                'by_ejar'   => collect($ccResp['aggregations']['by_ejar']['buckets'] ?? [])
                    ->map(fn ($b) => ['label' => (string) $b['key'], 'count' => $b['doc_count']])
                    ->values(),
            ],
        ]);
    }

    /** Safe OS search that returns empty structure if the index doesn't exist yet. */
    private function safeSearch(string $index, array $body): array
    {
        try {
            return $this->os->search(['index' => $index, 'body' => $body]);
        } catch (\Throwable) {
            return ['hits' => ['total' => ['value' => 0], 'hits' => []], 'aggregations' => []];
        }
    }
}
