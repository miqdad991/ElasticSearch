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

        // Refresh MVs so totals reflect the latest ETL state.
        try { DB::statement('REFRESH MATERIALIZED VIEW reports.mv_overview_totals'); } catch (\Throwable) {}
        try { DB::statement('REFRESH MATERIALIZED VIEW reports.mv_project_rollup'); } catch (\Throwable) {}

        $totals = DB::table('reports.mv_overview_totals')->first();

        // Projects rollup from OpenSearch
        $projResp = $this->os->search([
            'index' => $prefix . 'projects',
            'body'  => [
                'size'  => 50,
                'query' => ['match_all' => (object) []],
                'sort'  => [['contract_value' => 'desc']],
                'aggs'  => [
                    'sum_property' => ['sum' => ['field' => 'property_count']],
                    'sum_sp'       => ['sum' => ['field' => 'sp_count']],
                    'sum_budget'   => ['sum' => ['field' => 'lease_value']],
                    'sum_due'      => ['sum' => ['field' => 'payment_due']],
                    'sum_overdue'  => ['sum' => ['field' => 'payment_overdue']],
                ],
            ],
        ]);
        $projects = collect($projResp['hits']['hits'] ?? [])->pluck('_source');
        $pAggs    = $projResp['aggregations'] ?? [];

        // Subscriptions list from Postgres directly
        $subscriptions = DB::table('marts.dim_subscription_package')
            ->orderByDesc('created_at')->get();

        $cards = [
            'Total Projects'      => (int) ($totals->total_projects ?? 0),
            'Active Projects'     => (int) ($totals->active_projects ?? 0),
            'Inactive Projects'   => (int) ($totals->inactive_projects ?? 0),
            'Total Properties'    => (int) ($totals->total_properties ?? 0),
            'Service Providers'   => (int) ($totals->total_service_providers ?? 0),
            'Admins'              => (int) ($totals->total_admins ?? 0),
            'Subscriptions'       => (int) ($totals->total_subscriptions ?? 0),
            'Active Subs'         => (int) ($totals->active_subscriptions ?? 0),
            'Subscription Value'  => round($totals->subscription_value ?? 0, 2),
            'Projects Payment Due'=> round($pAggs['sum_due']['value'] ?? 0, 2),
        ];

        return view('dashboards.overview', [
            'cards'         => $cards,
            'projects'      => $projects,
            'subscriptions' => $subscriptions,
        ]);
    }
}
