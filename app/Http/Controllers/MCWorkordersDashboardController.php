<?php

namespace App\Http\Controllers;

use App\Services\Dashboard\PropertyMapService;
use Illuminate\Http\Request;
use OpenSearch\Client;

class MCWorkordersDashboardController extends Controller
{
    private const STATUS_LABELS = [
        1 => 'Open', 2 => 'In Progress', 3 => 'On Hold', 4 => 'Closed',
        5 => 'Deleted', 6 => 'Re-open', 7 => 'Warranty', 8 => 'Scheduled',
    ];

    public function __construct(private Client $os, private PropertyMapService $propertyMap) {}

    public function index(Request $request)
    {
        $index = config('opensearch.index_prefix', 'osool_') . 'work_orders';
        $usersIndex = config('opensearch.index_prefix', 'osool_') . 'users';

        $filters = $request->only(['date_from', 'date_to', 'user_id', 'contract_id']);

        // Build base filter: exclude status 5 (Deleted)
        $must = [['bool' => ['must_not' => [['term' => ['status_code' => 5]]]]]];

        // Project scoping
        if ($pid = session('selected_project_id')) {
            $must[] = ['term' => ['project_ids' => (int) $pid]];
        }

        // Apply filters
        if (!empty($filters['date_from']) || !empty($filters['date_to'])) {
            $range = [];
            if (!empty($filters['date_from'])) $range['gte'] = $filters['date_from'];
            if (!empty($filters['date_to']))   $range['lte'] = $filters['date_to'] . 'T23:59:59Z';
            $must[] = ['range' => ['created_at' => $range]];
        }
        if (!empty($filters['user_id'])) {
            $must[] = ['term' => ['project_user_id' => (int) $filters['user_id']]];
        }
        if (!empty($filters['contract_id'])) {
            $must[] = ['term' => ['contract_id' => (int) $filters['contract_id']]];
        }

        $query = ['bool' => ['must' => $must]];

        // Single search with all aggregations
        $resp = $this->os->search([
            'index' => $index,
            'body'  => [
                'track_total_hits' => true,
                'size'  => 0,
                'query' => $query,
                'aggs'  => [
                    'total_reactive'   => ['filter' => ['term' => ['work_order_type' => 'reactive']]],
                    'total_preventive' => ['filter' => ['term' => ['work_order_type' => 'preventive']]],
                    'total_cost'       => ['sum' => ['field' => 'cost']],
                    'distinct_locations' => ['cardinality' => ['field' => 'property_id']],
                    'distinct_contracts' => ['cardinality' => ['field' => 'contract_id']],
                    // Late execution: job_submitted_at > target_at
                    'late_execution' => ['filter' => ['script' => [
                        'script' => "doc.containsKey('job_submitted_at') && doc['job_submitted_at'].size()>0 && doc.containsKey('target_at') && doc['target_at'].size()>0 && doc['job_submitted_at'].value.isAfter(doc['target_at'].value)",
                    ]]],
                    // Status breakdown
                    'by_status' => ['terms' => ['field' => 'status_code', 'size' => 10]],
                    // Location names (top 15)
                    'by_building' => ['terms' => ['field' => 'building_name', 'size' => 15, 'order' => ['_count' => 'desc']]],
                    // Category line chart: top 5 categories, then monthly within each
                    'by_category' => [
                        'terms' => ['field' => 'asset_category', 'size' => 5, 'order' => ['_count' => 'desc']],
                        'aggs'  => [
                            'monthly' => ['terms' => ['field' => 'created_year_month', 'size' => 60, 'order' => ['_key' => 'asc']]],
                        ],
                    ],
                    // Expenses by type (pie)
                    'expenses_by_type' => [
                        'terms' => ['field' => 'work_order_type', 'size' => 10, 'missing' => 'Unspecified'],
                        'aggs'  => ['total_cost' => ['sum' => ['field' => 'cost']]],
                    ],
                    // Expenses by category (table)
                    'expenses_by_category' => [
                        'terms' => ['field' => 'asset_category', 'size' => 20, 'missing' => 'Uncategorized', 'order' => ['total_cost' => 'desc']],
                        'aggs'  => ['total_cost' => ['sum' => ['field' => 'cost']]],
                    ],
                    // All months (for line chart x-axis)
                    'all_months' => ['terms' => ['field' => 'created_year_month', 'size' => 60, 'order' => ['_key' => 'asc']]],
                    // Distinct user ids for dropdown
                    'distinct_users' => ['terms' => ['field' => 'project_user_id', 'size' => 500]],
                    // Distinct contract ids for dropdown
                    'distinct_contract_ids' => ['terms' => ['field' => 'contract_id', 'size' => 500]],
                ],
            ],
        ]);

        $aggs = $resp['aggregations'] ?? [];
        $totalWOs = $resp['hits']['total']['value'] ?? 0;

        // Build totals
        $totals = (object) [
            'total_workorders'  => $totalWOs,
            'total_reactive'    => $aggs['total_reactive']['doc_count'] ?? 0,
            'total_preventive'  => $aggs['total_preventive']['doc_count'] ?? 0,
            'late_execution'    => $aggs['late_execution']['doc_count'] ?? 0,
            'total_locations'   => $aggs['distinct_locations']['value'] ?? 0,
            'total_contracts'   => $aggs['distinct_contracts']['value'] ?? 0,
            'total_expenses'    => round($aggs['total_cost']['value'] ?? 0, 2),
        ];

        // Locations bar chart
        $locations = collect($aggs['by_building']['buckets'] ?? [])->map(fn ($b) => (object) [
            'label' => $b['key'], 'total' => $b['doc_count'],
        ]);

        // Status doughnut
        $perStatus = collect($aggs['by_status']['buckets'] ?? [])->map(fn ($b) => (object) [
            'status' => $b['key'],
            'label'  => self::STATUS_LABELS[$b['key']] ?? ('Status ' . $b['key']),
            'total'  => $b['doc_count'],
        ]);

        // Category line chart
        $allMonths = collect($aggs['all_months']['buckets'] ?? [])->pluck('key')->values();
        $categoryLineSeries = collect($aggs['by_category']['buckets'] ?? [])->map(function ($catBucket) use ($allMonths) {
            $monthlyData = collect($catBucket['monthly']['buckets'] ?? [])->pluck('doc_count', 'key');
            return [
                'name' => $catBucket['key'],
                'data' => $allMonths->map(fn ($m) => (int) ($monthlyData[$m] ?? 0))->values()->toArray(),
            ];
        })->values();

        // Expenses by type (pie)
        $expensesByType = collect($aggs['expenses_by_type']['buckets'] ?? [])->map(fn ($b) => (object) [
            'label' => ucfirst($b['key']), 'total' => round($b['total_cost']['value'] ?? 0, 2),
        ]);

        // Expenses by category (table)
        $expensesByCategory = collect($aggs['expenses_by_category']['buckets'] ?? [])->map(fn ($b) => (object) [
            'label' => $b['key'], 'wo_count' => $b['doc_count'], 'total' => round($b['total_cost']['value'] ?? 0, 2),
        ]);

        // User dropdown — fetch user details from users index
        $userIds = collect($aggs['distinct_users']['buckets'] ?? [])->pluck('key')->filter()->values()->toArray();
        $userOptions = collect();
        if ($userIds) {
            $uResp = $this->os->search([
                'index' => $usersIndex,
                'body'  => [
                    'size'  => 500,
                    'query' => ['terms' => ['user_id' => $userIds]],
                    '_source' => ['user_id', 'full_name', 'email'],
                ],
            ]);
            $userOptions = collect($uResp['hits']['hits'] ?? [])->map(fn ($h) => (object) [
                'id' => $h['_source']['user_id'],
                'display_name' => trim($h['_source']['full_name'] ?? '') ?: ($h['_source']['email'] ?? 'User #' . $h['_source']['user_id']),
            ])->sortBy('display_name')->values();
        }

        // Contract dropdown — from distinct contract_ids
        $contractOptions = collect($aggs['distinct_contract_ids']['buckets'] ?? [])
            ->filter(fn ($b) => $b['key'] > 0)
            ->map(fn ($b) => (object) [
                'id' => $b['key'],
                'display_name' => 'Contract #' . $b['key'],
            ])->values();

        $months = $allMonths;

        $propertyMapData = $this->propertyMap->build(session('selected_project_id'));

        return view('dashboards.mc-workorders', compact(
            'filters', 'totals', 'locations', 'perStatus',
            'categoryLineSeries', 'months', 'expensesByType',
            'expensesByCategory', 'userOptions', 'contractOptions',
            'propertyMapData'
        ));
    }
}
