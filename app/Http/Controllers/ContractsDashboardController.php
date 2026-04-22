<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use OpenSearch\Client;

class ContractsDashboardController extends Controller
{
    public function __construct(private Client $os) {}

    public function index(Request $request)
    {
        $index = config('opensearch.index_prefix', 'osool_') . 'contracts';

        $filters = array_filter([
            'service_provider_id' => $request->query('service_provider_id'),
            'contract_type_id'    => $request->query('contract_type_id'),
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
                        'terms' => ['field' => 'contract_type_name', 'size' => 10],
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

        $charts = [
            'by_type'     => collect($aggs['by_type']['buckets'] ?? [])
                ->map(fn ($b) => ['label' => (string) $b['key'], 'count' => round($b['v']['value'] ?? 0, 2)])->values(),
            'top_sp'      => collect($aggs['top_sp']['buckets'] ?? [])
                ->map(fn ($b) => ['label' => (string) $b['key'], 'count' => round($b['v']['value'] ?? 0, 2)])->values(),
            'top_overdue' => collect($aggs['top_overdue']['buckets'] ?? [])
                ->map(fn ($b) => ['label' => (string) $b['key'], 'count' => round($b['v']['value'] ?? 0, 2)])->values(),
        ];

        return view('dashboards.contracts', [
            'filters' => $filters,
            'cards'   => $cards,
            'charts'  => $charts,
            'rows'    => collect($hits)->pluck('_source'),
        ]);
    }
}
