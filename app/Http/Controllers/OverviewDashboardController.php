<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use OpenSearch\Client;

class OverviewDashboardController extends Controller
{
    public function __construct(private Client $os) {}

    public function index()
    {
        $prefix = config('opensearch.index_prefix', 'osool_');
        $pid    = session('selected_project_id');

        try { DB::statement('REFRESH MATERIALIZED VIEW reports.mv_overview_totals'); } catch (\Throwable) {}
        try { DB::statement('REFRESH MATERIALIZED VIEW reports.mv_project_rollup'); } catch (\Throwable) {}

        $totals = DB::table('reports.mv_overview_totals')->first();

        // ── Projects ──────────────────────────────────────────────────────────
        $projResp = $this->safe($prefix . 'projects', [
            'size'  => 50,
            'query' => ['match_all' => (object) []],
            'sort'  => [['contract_value' => 'desc']],
            'aggs'  => [
                'sum_due'     => ['sum' => ['field' => 'payment_due']],
                'sum_overdue' => ['sum' => ['field' => 'payment_overdue']],
            ],
        ]);
        $projects = collect($projResp['hits']['hits'] ?? [])->pluck('_source');
        $pAggs    = $projResp['aggregations'] ?? [];

        // ── Work Orders ───────────────────────────────────────────────────────
        $woMust = [['bool' => ['must_not' => [['term' => ['status_code' => 5]]]]]];
        if ($pid) $woMust[] = ['term' => ['project_ids' => (int) $pid]];
        $woResp = $this->safe($prefix . 'work_orders', [
            'track_total_hits' => true,
            'size'  => 0,
            'query' => ['bool' => ['must' => $woMust]],
            'aggs'  => [
                'preventive'   => ['filter' => ['term'  => ['work_order_type'    => 'preventive']]],
                'reactive'     => ['filter' => ['term'  => ['work_order_type'    => 'reactive']]],
                'hard_service' => ['filter' => ['term'  => ['service_type'       => 'hard']]],
                'soft_service' => ['filter' => ['term'  => ['service_type'       => 'soft']]],
                'open'         => ['filter' => ['term'  => ['status_code'        => 1]]],
                'in_progress'  => ['filter' => ['term'  => ['status_code'        => 2]]],
                'closed'       => ['filter' => ['term'  => ['status_code'        => 4]]],
                'total_cost'   => ['sum'    => ['field' => 'cost']],
                'by_status'    => ['terms'  => ['field' => 'status_code',        'size' => 10]],
                'by_type'      => ['terms'  => ['field' => 'work_order_type',    'size' => 5]],
            ],
        ]);
        $woAggs   = $woResp['aggregations'] ?? [];
        $totalWOs = (int)($woResp['hits']['total']['value'] ?? 0);
        $woStatusLabels = [1 => 'Open', 2 => 'In Progress', 3 => 'On Hold', 4 => 'Closed', 6 => 'Re-open', 7 => 'Warranty'];

        // ── Properties ────────────────────────────────────────────────────────
        $propMust  = $pid ? [['term' => ['project_ids' => (int) $pid]]] : [];
        $propQuery = $propMust ? ['bool' => ['must' => $propMust]] : ['match_all' => (object) []];
        $propResp  = $this->safe($prefix . 'properties', [
            'track_total_hits' => true,
            'size'  => 0,
            'query' => $propQuery,
            'aggs'  => [
                'active'        => ['filter' => ['term' => ['is_active'     => true]]],
                'buildings_only'=> ['filter' => ['term' => ['property_type' => 'building']]],
                'complexes'     => ['filter' => ['term' => ['property_type' => 'complex']]],
                'sum_buildings' => ['sum'    => ['field' => 'buildings_count']],
                'by_region'     => ['terms'  => ['field' => 'region_name',  'size' => 8]],
            ],
        ]);
        $propAggs   = $propResp['aggregations'] ?? [];
        $totalProps = (int)($propResp['hits']['total']['value'] ?? 0);

        // ── Assets ────────────────────────────────────────────────────────────
        $assetMust  = $pid ? [['term' => ['project_ids' => (int) $pid]]] : [];
        $assetQuery = $assetMust ? ['bool' => ['must' => $assetMust]] : ['match_all' => (object) []];
        $assetResp  = $this->safe($prefix . 'assets', [
            'track_total_hits' => true,
            'size'  => 0,
            'query' => $assetQuery,
            'aggs'  => [
                'under_warranty'=> ['filter'      => ['term'  => ['under_warranty'   => true]]],
                'with_status'   => ['filter'      => ['term'  => ['has_status'       => true]]],
                'distinct_cat'  => ['cardinality' => ['field' => 'asset_category_id']],
                'total_value'   => ['sum'         => ['field' => 'purchase_amount']],
                'by_category'   => ['terms'       => ['field' => 'asset_category',  'size' => 6]],
            ],
        ]);
        $assetAggs   = $assetResp['aggregations'] ?? [];
        $totalAssets = (int)($assetResp['hits']['total']['value'] ?? 0);

        // ── Users ─────────────────────────────────────────────────────────────
        $userMust  = $pid ? [['term' => ['project_ids' => (int) $pid]]] : [];
        $userQuery = $userMust ? ['bool' => ['must' => $userMust]] : ['match_all' => (object) []];
        $userResp  = $this->safe($prefix . 'users', [
            'track_total_hits' => true,
            'size'  => 0,
            'query' => $userQuery,
            'aggs'  => [
                'active'   => ['filter' => ['term'  => ['is_active' => true]]],
                'inactive' => ['filter' => ['term'  => ['is_active' => false]]],
                'by_type'  => ['terms'  => ['field' => 'user_type', 'size' => 15]],
            ],
        ]);
        $userAggs   = $userResp['aggregations'] ?? [];
        $totalUsers = (int)($userResp['hits']['total']['value'] ?? 0);

        // ── Billing ───────────────────────────────────────────────────────────
        $ccMust = [['term' => ['is_deleted' => false]]];
        if ($pid) $ccMust[] = ['term' => ['project_id' => (int) $pid]];
        $ccResp = $this->safe($prefix . 'commercial_contracts', [
            'track_total_hits' => true,
            'size'  => 0,
            'query' => ['bool' => ['must' => $ccMust]],
            'aggs'  => [
                'active'       => ['filter' => ['term' => ['is_active'     => true]]],
                'rent'         => ['filter' => ['term' => ['contract_type' => 'rent']]],
                'lease'        => ['filter' => ['term' => ['contract_type' => 'lease']]],
                'total_amount' => ['sum'    => ['field' => 'amount']],
                'by_ejar'      => ['terms'  => ['field' => 'ejar_sync_status', 'size' => 5]],
            ],
        ]);
        $ccAggs = $ccResp['aggregations'] ?? [];

        $inMust  = $pid ? [['term' => ['project_id' => (int) $pid]]] : [];
        $inQuery = $inMust ? ['bool' => ['must' => $inMust]] : ['match_all' => (object) []];
        $inResp  = $this->safe($prefix . 'installments', [
            'size'  => 0,
            'query' => $inQuery,
            'aggs'  => [
                'collected'   => ['filter' => ['term' => ['is_paid'    => true]],  'aggs' => ['sum' => ['sum' => ['field' => 'amount']]]],
                'outstanding' => ['filter' => ['term' => ['is_paid'    => false]], 'aggs' => ['sum' => ['sum' => ['field' => 'amount']]]],
                'overdue_inst'=> ['filter' => ['term' => ['is_overdue' => true]],  'aggs' => ['sum' => ['sum' => ['field' => 'amount']]]],
            ],
        ]);
        $inAggs = $inResp['aggregations'] ?? [];

        // ── Execution Contracts ───────────────────────────────────────────────
        $conMust  = $pid ? [['term' => ['project_ids' => (int) $pid]]] : [];
        $conQuery = $conMust ? ['bool' => ['must' => $conMust]] : ['match_all' => (object) []];
        $conResp  = $this->safe($prefix . 'contracts', [
            'track_total_hits' => true,
            'size'  => 0,
            'query' => $conQuery,
            'aggs'  => [
                'active'      => ['filter' => ['term' => ['is_active' => true]]],
                'total_value' => ['sum'    => ['field' => 'contract_value']],
                'paid'        => ['sum'    => ['field' => 'paid_total']],
                'overdue'     => ['sum'    => ['field' => 'overdue_total']],
                'by_type'     => ['terms'  => ['field' => 'contract_type_name', 'size' => 8]],
            ],
        ]);
        $conAggs = $conResp['aggregations'] ?? [];

        // ── Assemble ──────────────────────────────────────────────────────────
        $platformCards = [
            'Total Projects'      => (int)  ($totals->total_projects          ?? 0),
            'Active Projects'     => (int)  ($totals->active_projects         ?? 0),
            'Service Providers'   => (int)  ($totals->total_service_providers ?? 0),
            'Admins'              => (int)  ($totals->total_admins            ?? 0),
            'Subscriptions'       => (int)  ($totals->total_subscriptions     ?? 0),
            'Active Subs'         => (int)  ($totals->active_subscriptions    ?? 0),
            'Sub Value'           => round(  $totals->subscription_value      ?? 0, 2),
            'Projects Payment Due'=> round(  $pAggs['sum_due']['value']       ?? 0, 2),
            'Projects Overdue'    => round(  $pAggs['sum_overdue']['value']   ?? 0, 2),
        ];

        $workOrderStats = [
            'total'        => $totalWOs,
            'preventive'   => (int)($woAggs['preventive']['doc_count']   ?? 0),
            'reactive'     => (int)($woAggs['reactive']['doc_count']     ?? 0),
            'hard_service' => (int)($woAggs['hard_service']['doc_count'] ?? 0),
            'soft_service' => (int)($woAggs['soft_service']['doc_count'] ?? 0),
            'open'         => (int)($woAggs['open']['doc_count']         ?? 0),
            'in_progress'  => (int)($woAggs['in_progress']['doc_count']  ?? 0),
            'closed'       => (int)($woAggs['closed']['doc_count']       ?? 0),
            'total_cost'   => round($woAggs['total_cost']['value']       ?? 0, 2),
        ];
        $woByStatus = collect($woAggs['by_status']['buckets'] ?? [])->map(fn($b) => ['label' => $woStatusLabels[$b['key']] ?? 'Status '.$b['key'], 'count' => $b['doc_count']])->values();
        $woByType   = collect($woAggs['by_type']['buckets']   ?? [])->map(fn($b) => ['label' => ucfirst($b['key']), 'count' => $b['doc_count']])->values();

        $propertyStats = [
            'total'          => $totalProps,
            'active'         => (int)($propAggs['active']['doc_count']         ?? 0),
            'buildings_only' => (int)($propAggs['buildings_only']['doc_count'] ?? 0),
            'complexes'      => (int)($propAggs['complexes']['doc_count']      ?? 0),
            'total_buildings'=> (int)($propAggs['sum_buildings']['value']      ?? 0),
        ];
        $propByRegion = collect($propAggs['by_region']['buckets'] ?? [])->map(fn($b) => ['label' => $b['key'], 'count' => $b['doc_count']])->values();

        $assetStats = [
            'total'         => $totalAssets,
            'categories'    => (int)  ($assetAggs['distinct_cat']['value']       ?? 0),
            'under_warranty'=> (int)  ($assetAggs['under_warranty']['doc_count'] ?? 0),
            'with_status'   => (int)  ($assetAggs['with_status']['doc_count']    ?? 0),
            'total_value'   => round(  $assetAggs['total_value']['value']        ?? 0, 2),
        ];
        $assetByCategory = collect($assetAggs['by_category']['buckets'] ?? [])->map(fn($b) => ['label' => $b['key'], 'count' => $b['doc_count']])->values();

        $userStats  = [
            'total'    => $totalUsers,
            'active'   => (int)($userAggs['active']['doc_count']   ?? 0),
            'inactive' => (int)($userAggs['inactive']['doc_count'] ?? 0),
        ];
        $userByType = collect($userAggs['by_type']['buckets'] ?? [])->map(fn($b) => ['label' => $b['key'], 'count' => $b['doc_count']])->values();

        $billingStats = [
            'total_cc'    => (int)  ($ccResp['hits']['total']['value']        ?? 0),
            'active_cc'   => (int)  ($ccAggs['active']['doc_count']           ?? 0),
            'rent'        => (int)  ($ccAggs['rent']['doc_count']             ?? 0),
            'lease'       => (int)  ($ccAggs['lease']['doc_count']            ?? 0),
            'total_amount'=> round(  $ccAggs['total_amount']['value']         ?? 0, 2),
            'collected'   => round(  $inAggs['collected']['sum']['value']     ?? 0, 2),
            'outstanding' => round(  $inAggs['outstanding']['sum']['value']   ?? 0, 2),
            'overdue'     => round(  $inAggs['overdue_inst']['sum']['value']  ?? 0, 2),
        ];

        $contractStats = [
            'total'       => (int)  ($conResp['hits']['total']['value'] ?? 0),
            'active'      => (int)  ($conAggs['active']['doc_count']    ?? 0),
            'total_value' => round(  $conAggs['total_value']['value']   ?? 0, 2),
            'paid'        => round(  $conAggs['paid']['value']          ?? 0, 2),
            'overdue'     => round(  $conAggs['overdue']['value']       ?? 0, 2),
        ];
        $conByType = collect($conAggs['by_type']['buckets'] ?? [])->map(fn($b) => ['label' => $b['key'], 'count' => $b['doc_count']])->values();

        $subscriptions = DB::table('marts.dim_subscription_package')->orderByDesc('created_at')->get();

        return view('dashboards.overview', compact(
            'platformCards', 'projects', 'subscriptions',
            'workOrderStats', 'woByStatus', 'woByType',
            'propertyStats',  'propByRegion',
            'assetStats',     'assetByCategory',
            'userStats',      'userByType',
            'billingStats',   'contractStats', 'conByType'
        ));
    }

    private function safe(string $index, array $body): array
    {
        try {
            return $this->os->search(['index' => $index, 'body' => $body]);
        } catch (\Throwable) {
            return ['hits' => ['total' => ['value' => 0], 'hits' => []], 'aggregations' => []];
        }
    }
}
