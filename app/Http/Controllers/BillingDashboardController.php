<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use OpenSearch\Client;

class BillingDashboardController extends Controller
{
    public function __construct(private Client $os) {}

    public function index(Request $request)
    {
        $prefix = config('opensearch.index_prefix', 'osool_');
        $ccIdx  = $prefix . 'commercial_contracts';
        $inIdx  = $prefix . 'installments';

        $filters = array_filter([
            'contract_type'    => $request->query('contract_type'),
            'ejar_sync_status' => $request->query('ejar_sync_status'),
            'project_id'       => $request->query('project_id'),
        ], fn ($v) => $v !== null && $v !== '');

        $must = [];
        foreach ($filters as $f => $v) {
            $must[] = ['term' => [$f => is_numeric($v) ? (int) $v : $v]];
        }
        if ($pid = session('selected_project_id')) {
            $must[] = ['term' => ['project_id' => (int) $pid]];
            $filters['project_id'] = (int) $pid;
        }
        $must[] = ['term' => ['is_deleted' => false]];
        $ccQuery = ['bool' => ['must' => $must]];

        // Contracts aggregations
        $cc = $this->os->search([
            'index' => $ccIdx,
            'body'  => [
                'size'  => 0,
                'query' => $ccQuery,
                'aggs'  => [
                    'total_amount'      => ['sum' => ['field' => 'amount']],
                    'sum_security'      => ['sum' => ['field' => 'security_deposit_amount']],
                    'sum_late'          => ['sum' => ['field' => 'late_fees_charge']],
                    'sum_brokerage'     => ['sum' => ['field' => 'brokerage_fee']],
                    'sum_retainer'      => ['sum' => ['field' => 'retainer_fee']],
                    'sum_due'           => ['sum' => ['field' => 'payment_due']],
                    'sum_overdue'       => ['sum' => ['field' => 'payment_overdue']],
                    'rent'              => ['filter' => ['term' => ['contract_type' => 'rent']]],
                    'lease'             => ['filter' => ['term' => ['contract_type' => 'lease']]],
                    'auto_renewal'      => ['filter' => ['term' => ['auto_renewal' => true]]],
                    'active'            => ['filter' => ['term' => ['is_active' => true]]],
                    'by_type'           => ['terms' => ['field' => 'contract_type', 'size' => 5]],
                    'by_ejar'           => ['terms' => ['field' => 'ejar_sync_status', 'size' => 10]],
                ],
            ],
        ]);

        // Installment scope filter — contract-type through parent field copy, project_id directly
        $inMust = [];
        if (isset($filters['project_id']))    $inMust[] = ['term' => ['project_id' => (int) $filters['project_id']]];
        if (isset($filters['contract_type'])) $inMust[] = ['term' => ['contract_type' => $filters['contract_type']]];
        $inQuery = $inMust ? ['bool' => ['must' => $inMust]] : ['match_all' => (object) []];

        $in = $this->os->search([
            'index' => $inIdx,
            'body'  => [
                'size'  => 0,
                'query' => $inQuery,
                'aggs'  => [
                    'paid'        => ['filter' => ['term' => ['is_paid' => true]],  'aggs' => ['sum' => ['sum' => ['field' => 'amount']]]],
                    'unpaid'      => ['filter' => ['term' => ['is_paid' => false]], 'aggs' => ['sum' => ['sum' => ['field' => 'amount']]]],
                    'overdue'     => ['filter' => ['term' => ['is_overdue' => true]], 'aggs' => ['sum' => ['sum' => ['field' => 'amount']]]],
                    'monthly'     => [
                        'terms' => ['field' => 'due_year_month', 'size' => 60, 'order' => ['_key' => 'asc']],
                        'aggs'  => [
                            'paid'   => ['filter' => ['term' => ['is_paid' => true]],  'aggs' => ['sum' => ['sum' => ['field' => 'amount']]]],
                            'unpaid' => ['filter' => ['term' => ['is_paid' => false]], 'aggs' => ['sum' => ['sum' => ['field' => 'amount']]]],
                        ],
                    ],
                    'aging'       => ['terms' => ['field' => 'aging_bucket', 'size' => 10]],
                    'by_ptype'    => ['terms' => ['field' => 'payment_type', 'size' => 10]],
                    'top_tenants' => [
                        'filter' => ['term' => ['is_paid' => false]],
                        'aggs'   => ['tenants' => [
                            'terms' => ['field' => 'tenant_name', 'size' => 10, 'order' => ['outstanding' => 'desc']],
                            'aggs'  => ['outstanding' => ['sum' => ['field' => 'amount']]],
                        ]],
                    ],
                ],
            ],
        ]);

        // Upcoming + overdue rows
        $overdueRows = $this->os->search([
            'index' => $inIdx,
            'body'  => [
                'size'  => 15,
                'query' => ['bool' => ['must' => array_merge($inMust, [['term' => ['is_overdue' => true]]])]],
                'sort'  => [['days_overdue' => 'desc']],
            ],
        ])['hits']['hits'] ?? [];

        $upcomingRows = $this->os->search([
            'index' => $inIdx,
            'body'  => [
                'size'  => 15,
                'query' => ['bool' => ['must' => array_merge($inMust, [
                    ['term' => ['is_paid' => false]],
                    ['range' => ['payment_due_date' => ['gte' => 'now/d']]],
                ])]],
                'sort'  => [['payment_due_date' => 'asc']],
            ],
        ])['hits']['hits'] ?? [];

        $ccAggs = $cc['aggregations'] ?? [];
        $inAggs = $in['aggregations'] ?? [];

        $cards = [
            'Total Contracts'     => $cc['hits']['total']['value'] ?? 0,
            'Total Contract Value'=> round($ccAggs['total_amount']['value'] ?? 0, 2),
            'Rent'                => $ccAggs['rent']['doc_count'] ?? 0,
            'Lease'               => $ccAggs['lease']['doc_count'] ?? 0,
            'Security Deposits'   => round($ccAggs['sum_security']['value']  ?? 0, 2),
            'Late Fees'           => round($ccAggs['sum_late']['value']      ?? 0, 2),
            'Brokerage Fees'      => round($ccAggs['sum_brokerage']['value'] ?? 0, 2),
            'Retainer Fees'       => round($ccAggs['sum_retainer']['value']  ?? 0, 2),
            'Collected'           => round($inAggs['paid']['sum']['value']    ?? 0, 2),
            'Outstanding'         => round($inAggs['unpaid']['sum']['value']  ?? 0, 2),
            'Overdue Amount'      => round($inAggs['overdue']['sum']['value'] ?? 0, 2),
            'Payment Due (contracts)' => round($ccAggs['sum_due']['value']    ?? 0, 2),
        ];

        $monthly = collect($inAggs['monthly']['buckets'] ?? [])
            ->map(fn ($b) => [
                'label'   => (string) $b['key'],
                'paid'    => (float) ($b['paid']['sum']['value']   ?? 0),
                'unpaid'  => (float) ($b['unpaid']['sum']['value'] ?? 0),
            ])->values();

        $aging = collect($inAggs['aging']['buckets'] ?? [])
            ->map(fn ($b) => ['label' => (string) $b['key'], 'count' => $b['doc_count']])
            ->values();

        $byType = collect($ccAggs['by_type']['buckets'] ?? [])
            ->map(fn ($b) => ['label' => (string) $b['key'], 'count' => $b['doc_count']])->values();

        $byEjar = collect($ccAggs['by_ejar']['buckets'] ?? [])
            ->map(fn ($b) => ['label' => (string) $b['key'], 'count' => $b['doc_count']])->values();

        $topTenants = collect($inAggs['top_tenants']['tenants']['buckets'] ?? [])
            ->map(fn ($b) => ['label' => (string) $b['key'], 'count' => round($b['outstanding']['value'] ?? 0, 2)])
            ->values();

        $byPtype = collect($inAggs['by_ptype']['buckets'] ?? [])
            ->map(fn ($b) => ['label' => (string) $b['key'], 'count' => $b['doc_count']])->values();

        return view('dashboards.billing', [
            'filters'      => $filters,
            'cards'        => $cards,
            'charts'       => [
                'monthly'     => $monthly,
                'aging'       => $aging,
                'by_type'     => $byType,
                'by_ejar'     => $byEjar,
                'top_tenants' => $topTenants,
                'by_ptype'    => $byPtype,
            ],
            'overdueRows'  => collect($overdueRows)->pluck('_source'),
            'upcomingRows' => collect($upcomingRows)->pluck('_source'),
        ]);
    }
}
