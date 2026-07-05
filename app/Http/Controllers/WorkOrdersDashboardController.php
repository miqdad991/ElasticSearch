<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use OpenSearch\Client;

class WorkOrdersDashboardController extends Controller
{
    public function __construct(private Client $os) {}

    public function index(Request $request)
    {
        $index = config('opensearch.index_prefix', 'osool_') . 'work_orders';

        $filters = array_filter([
            'service_type'      => $request->query('service_type'),
            'work_order_type'   => $request->query('work_order_type'),
            'workorder_journey' => $request->query('workorder_journey'),
            'priority_id'       => $request->query('priority_id'),
            'asset_category_id' => $request->query('asset_category_id'),
            'status_code'       => $request->query('status_code'),
        ], fn ($v) => $v !== null && $v !== '');

        $must = [];
        foreach ($filters as $field => $value) {
            $must[] = ['term' => [$field => is_numeric($value) ? (int) $value : $value]];
        }
        if ($pid = session('selected_project_id')) {
            $must[] = ['term' => ['project_ids' => (int) $pid]];
        }

        // Date range on created_at (YYYY-MM-DD inputs). 'to' is inclusive of the
        // whole day via lte on the date — OpenSearch rounds a bare date up to the
        // day's end on the upper bound.
        $from = $request->query('from');
        $to   = $request->query('to');
        $range = array_filter([
            'gte' => $from ?: null,
            'lte' => $to ?: null,
        ]);
        if ($range) {
            $range['format'] = 'yyyy-MM-dd';
            $must[] = ['range' => ['created_at' => $range]];
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
                    'total_cost'         => ['sum' => ['field' => 'cost']],
                    'by_service_type'    => ['terms' => ['field' => 'service_type', 'size' => 10]],
                    'by_wo_type'         => ['terms' => ['field' => 'work_order_type', 'size' => 10]],
                    'by_status'          => ['terms' => ['field' => 'status_label', 'size' => 10]],
                    // Journey / priority / asset-category / building are each broken down
                    // by work-order type via a nested sub-agg, so the view can render them
                    // as WO-type stacked bars (reshaped by $stackedByType below).
                    'by_journey'         => [
                        'terms' => ['field' => 'workorder_journey', 'size' => 10],
                        'aggs'  => ['by_type' => ['terms' => ['field' => 'work_order_type', 'size' => 10]]],
                    ],
                    'by_priority'        => [
                        'terms' => ['field' => 'priority_level', 'size' => 10],
                        'aggs'  => ['by_type' => ['terms' => ['field' => 'work_order_type', 'size' => 10]]],
                    ],
                    'by_category'        => [
                        'terms' => ['field' => 'asset_category', 'size' => 10],
                        'aggs'  => ['by_type' => ['terms' => ['field' => 'work_order_type', 'size' => 10]]],
                    ],
                    'by_building'        => [
                        'terms' => ['field' => 'building_name', 'size' => 10],
                        'aggs'  => ['by_type' => ['terms' => ['field' => 'work_order_type', 'size' => 10]]],
                    ],
                    'by_sp'              => [
                        'terms' => ['field' => 'service_provider_name', 'size' => 12],
                        'aggs'  => ['cost_sum' => ['sum' => ['field' => 'cost']]],
                    ],
                    'cost_by_type'       => [
                        'terms' => ['field' => 'work_order_type', 'size' => 10, 'order' => ['cost_sum' => 'desc']],
                        'aggs'  => ['cost_sum' => ['sum' => ['field' => 'cost']]],
                    ],
                    'cost_by_service'    => [
                        'terms' => ['field' => 'service_type', 'size' => 10, 'order' => ['cost_sum' => 'desc']],
                        'aggs'  => ['cost_sum' => ['sum' => ['field' => 'cost']]],
                    ],
                    'cost_by_city'       => [
                        'terms' => ['field' => 'city_name', 'size' => 15, 'order' => ['cost_sum' => 'desc']],
                        'aggs'  => ['cost_sum' => ['sum' => ['field' => 'cost']]],
                    ],
                    'monthly'            => ['terms' => ['field' => 'created_year_month', 'size' => 60, 'order' => ['_key' => 'asc']]],
                    'monthly_status'     => [
                        'terms' => ['field' => 'created_year_month', 'size' => 60, 'order' => ['_key' => 'asc']],
                        'aggs'  => ['closed' => ['filter' => ['term' => ['workorder_journey' => 'finished']]]],
                    ],
                    'distinct_sps'       => ['cardinality' => ['field' => 'service_provider_id']],
                    'distinct_mr'        => ['cardinality' => ['field' => 'maintenance_request_id']],
                    'finished'           => ['filter' => ['term' => ['workorder_journey' => 'finished']]],
                    'in_progress'        => ['filter' => ['terms' => ['workorder_journey' => ['submitted', 'job_execution', 'job_evaluation', 'job_approval']]]],
                    'preventive'         => ['filter' => ['term' => ['work_order_type' => 'preventive']]],
                    'reactive'           => ['filter' => ['term' => ['work_order_type' => 'reactive']]],
                    'hard'               => ['filter' => ['term' => ['service_type' => 'hard']]],
                    'soft'               => ['filter' => ['term' => ['service_type' => 'soft']]],
                ],
            ],
        ]);

        $hits = $resp['hits']['hits'] ?? [];
        $aggs = $resp['aggregations'] ?? [];

        $cards = [
            'Total Work Orders'    => $resp['hits']['total']['value'] ?? 0,
            'Preventive'           => $aggs['preventive']['doc_count']  ?? 0,
            'Reactive'             => $aggs['reactive']['doc_count']    ?? 0,
            'Hard Service'         => $aggs['hard']['doc_count']        ?? 0,
            'Soft Service'         => $aggs['soft']['doc_count']        ?? 0,
            'Maintenance Requests' => $aggs['distinct_mr']['value']     ?? 0,
            'Service Providers'    => $aggs['distinct_sps']['value']    ?? 0,
            'Total Cost'           => round($aggs['total_cost']['value'] ?? 0, 2),
            'Finished'             => $aggs['finished']['doc_count']    ?? 0,
            'Open / In Progress'   => $aggs['in_progress']['doc_count'] ?? 0,
        ];

        $bucket = fn (string $key) => collect($aggs[$key]['buckets'] ?? [])
            ->map(fn ($b) => ['label' => (string) $b['key'], 'count' => $b['doc_count']])
            ->values();

        // Like $bucket, but carries the summed cost (for cost-per-dimension charts).
        $costBucket = fn (string $key) => collect($aggs[$key]['buckets'] ?? [])
            ->map(fn ($b) => ['label' => (string) $b['key'], 'cost' => round($b['cost_sum']['value'] ?? 0, 2)])
            ->values();

        // Reshape a terms agg carrying a nested `by_type` sub-agg into stacked-bar
        // shape: { categories: [label…], series: [{ name: wo_type, data: [count per label] }] }.
        $stackedByType = function (string $key) use ($aggs) {
            $buckets = $aggs[$key]['buckets'] ?? [];
            $woTypes = collect($buckets)
                ->flatMap(fn ($b) => collect($b['by_type']['buckets'] ?? [])->pluck('key'))
                ->unique()->values();
            return [
                'categories' => collect($buckets)->map(fn ($b) => (string) $b['key'])->values(),
                'series'     => $woTypes->map(fn ($type) => [
                    'name' => (string) $type,
                    'data' => collect($buckets)->map(function ($b) use ($type) {
                        $hit = collect($b['by_type']['buckets'] ?? [])->firstWhere('key', $type);
                        return $hit['doc_count'] ?? 0;
                    })->values(),
                ])->values(),
            ];
        };

        return view('dashboards.work-orders', [
            'filters' => $filters,
            'dates'   => ['from' => $from, 'to' => $to],
            'cards'   => $cards,
            'rows'    => collect($hits)->pluck('_source'),
            'charts'  => [
                'monthly'        => $bucket('monthly'),
                'monthly_status' => collect($aggs['monthly_status']['buckets'] ?? [])
                    ->map(fn ($b) => [
                        'label'  => (string) $b['key'],
                        'closed' => $b['closed']['doc_count'] ?? 0,
                        'open'   => ($b['doc_count'] ?? 0) - ($b['closed']['doc_count'] ?? 0),
                    ])
                    ->values(),
                'by_service'     => $bucket('by_service_type'),
                'by_wo_type'     => $bucket('by_wo_type'),
                'by_status'      => $bucket('by_status'),
                'by_journey'     => $stackedByType('by_journey'),
                'by_priority'    => $stackedByType('by_priority'),
                'by_category'    => $stackedByType('by_category'),
                'by_building'    => $stackedByType('by_building'),
                'by_sp'          => collect($aggs['by_sp']['buckets'] ?? [])
                    ->map(fn ($b) => [
                        'label' => (string) $b['key'],
                        'count' => $b['doc_count'],
                        'cost'  => round($b['cost_sum']['value'] ?? 0, 2),
                    ])
                    ->values(),
                'cost_by_type'    => $costBucket('cost_by_type'),
                'cost_by_service' => $costBucket('cost_by_service'),
                'cost_by_city'    => $costBucket('cost_by_city'),
            ],
        ]);
    }
}
