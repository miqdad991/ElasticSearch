<?php

namespace App\Http\Controllers;

use App\Services\Contract\ContractorAnalyticsQuery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use OpenSearch\Client;

class ContractsDashboardController extends Controller
{
    public function __construct(private Client $os) {}

    public function index(Request $request)
    {
        $index = config('opensearch.index_prefix', 'osool_') . 'contracts';

        $filters = array_filter([
            'service_provider_id' => $request->query('service_provider_id'),
            'contract_class'      => $request->query('contract_class'),
            'status'              => $request->query('status'),
        ], fn ($v) => $v !== null && $v !== '');

        $must = [];
        foreach ($filters as $f => $v) {
            $must[] = ['term' => [$f => is_numeric($v) ? (int) $v : $v]];
        }
        if ($pid = session('selected_project_id')) {
            $must[] = ['term' => ['project_ids' => (int) $pid]];
        }
        $query = $must ? ['bool' => ['must' => $must]] : ['match_all' => (object) []];

        $resp = $this->os->search([
            'index' => $index,
            'body'  => [
                'size'  => 30,
                'query' => $query,
                'sort'  => [['contract_value' => 'desc']],
                'aggs'  => [
                    'sum_value'      => ['sum' => ['field' => 'contract_value']],
                    'avg_value'      => ['avg' => ['field' => 'contract_value']],
                    'sum_scheduled'  => ['sum' => ['field' => 'scheduled_total']],
                    'sum_paid'       => ['sum' => ['field' => 'paid_total']],
                    'sum_pending'    => ['sum' => ['field' => 'pending_total']],
                    'sum_overdue'    => ['sum' => ['field' => 'overdue_total']],
                    'sum_wo_cost'    => ['sum' => ['field' => 'wo_total_cost']],
                    'sum_closed_wo'  => ['sum' => ['field' => 'closed_wo_count']],
                    'active'         => ['filter' => ['term' => ['is_active' => true]]],
                    'expired'        => ['filter' => ['term' => ['is_expired' => true]]],
                    'subcontracts'   => ['filter' => ['term' => ['is_subcontract' => true]]],
                    'by_type'        => [
                        'terms' => ['field' => 'contract_class', 'size' => 10],
                        'aggs'  => ['v' => ['sum' => ['field' => 'contract_value']]],
                    ],
                    'top_sp'         => [
                        'terms' => ['field' => 'service_provider_name', 'size' => 10, 'order' => ['v' => 'desc']],
                        'aggs'  => ['v' => ['sum' => ['field' => 'contract_value']]],
                    ],
                    'top_overdue'    => [
                        'terms' => ['field' => 'contract_number', 'size' => 10, 'order' => ['v' => 'desc']],
                        'aggs'  => ['v' => ['sum' => ['field' => 'overdue_total']]],
                    ],
                    'top_completed'  => [
                        'terms' => ['field' => 'service_provider_name', 'size' => 10, 'order' => ['c' => 'desc']],
                        'aggs'  => ['c' => ['sum' => ['field' => 'closed_wo_count']]],
                    ],
                ],
            ],
        ]);

        $hits = $resp['hits']['hits'] ?? [];
        $aggs = $resp['aggregations'] ?? [];

        $cards = [
            'Total Contracts' => $resp['hits']['total']['value'] ?? 0,
            'Total Value'     => round($aggs['sum_value']['value']     ?? 0, 2),
            'Average Value'   => round($aggs['avg_value']['value']     ?? 0, 2),
            'Active'          => $aggs['active']['doc_count']          ?? 0,
            'Scheduled Total' => round($aggs['sum_scheduled']['value'] ?? 0, 2),
            'Paid'            => round($aggs['sum_paid']['value']      ?? 0, 2),
            'Pending'         => round($aggs['sum_pending']['value']   ?? 0, 2),
            'Overdue'         => round($aggs['sum_overdue']['value']   ?? 0, 2),
            'Subcontracts'    => $aggs['subcontracts']['doc_count']    ?? 0,
            'Expired'         => $aggs['expired']['doc_count']         ?? 0,
            'Closed WOs'      => (int) ($aggs['sum_closed_wo']['value'] ?? 0),
            'WO Extras Total' => round($aggs['sum_wo_cost']['value']   ?? 0, 2),
        ];

        $classLabels = [
            'regular'  => __('contracts.type_regular'),
            'advanced' => __('contracts.type_advanced'),
        ];
        $charts = [
            'by_type'     => collect($aggs['by_type']['buckets'] ?? [])
                ->map(fn ($b) => ['label' => $classLabels[$b['key']] ?? (string) $b['key'], 'count' => round($b['v']['value'] ?? 0, 2)])->values(),
            'top_sp'      => collect($aggs['top_sp']['buckets'] ?? [])
                ->map(fn ($b) => ['label' => (string) $b['key'], 'count' => round($b['v']['value'] ?? 0, 2)])->values(),
            'top_overdue' => collect($aggs['top_overdue']['buckets'] ?? [])
                ->map(fn ($b) => ['label' => (string) $b['key'], 'count' => round($b['v']['value'] ?? 0, 2)])->values(),
            'top_completed' => collect($aggs['top_completed']['buckets'] ?? [])
                ->map(fn ($b) => ['label' => (string) $b['key'], 'count' => (int) ($b['c']['value'] ?? 0)])->values(),
        ];

        // Filter option lists (real labels, not raw IDs). Restrict SPs to those that
        // actually own contracts so the dropdown isn't cluttered with unused providers.
        $spOptions = DB::table('marts.dim_contract as dc')
            ->join('marts.dim_service_provider as sp', 'sp.sp_id', '=', 'dc.service_provider_id')
            ->where('dc.is_current', true)->where('dc.is_deleted', false)
            ->whereNotNull('dc.service_provider_id')
            ->distinct()->orderBy('sp.name')
            ->pluck('sp.name', 'sp.sp_id');

        // Contractor analytics (service coverage, workforce, portfolio) — computed from
        // the Postgres marts, honouring the same filters + project scope as the dashboard.
        $analytics = new ContractorAnalyticsQuery($filters + [
            'project_id' => session('selected_project_id'),
        ]);
        $wf = $analytics->workforce();
        $workforce = [
            ['label' => __('contracts.wf_workers'),        'count' => $wf['workers']],
            ['label' => __('contracts.wf_supervisors'),    'count' => $wf['supervisors']],
            ['label' => __('contracts.wf_administrators'), 'count' => $wf['administrators']],
            ['label' => __('contracts.wf_engineers'),      'count' => $wf['engineers']],
        ];

        return view('dashboards.contracts', [
            'filters'     => $filters,
            'cards'       => $cards,
            'charts'      => $charts,
            'coverage'    => $analytics->serviceCoverage(),
            'workforce'   => $workforce,
            'portfolio'   => $analytics->portfolio(),
            'spOptions'   => $spOptions,
            'classLabels' => $classLabels,
            'rows'        => collect($hits)->pluck('_source'),
        ]);
    }
}
