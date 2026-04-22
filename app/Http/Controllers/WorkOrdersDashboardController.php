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
                    'by_journey'         => ['terms' => ['field' => 'workorder_journey', 'size' => 10]],
                    'by_status'          => ['terms' => ['field' => 'status_label', 'size' => 10]],
                    'by_priority'        => ['terms' => ['field' => 'priority_level', 'size' => 10]],
                    'by_category'        => ['terms' => ['field' => 'asset_category', 'size' => 10]],
                    'by_building'        => ['terms' => ['field' => 'building_name', 'size' => 10]],
                    'monthly'            => ['terms' => ['field' => 'created_year_month', 'size' => 60, 'order' => ['_key' => 'asc']]],
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

        return view('dashboards.work-orders', [
            'filters' => $filters,
            'cards'   => $cards,
            'rows'    => collect($hits)->pluck('_source'),
            'charts'  => [
                'monthly'        => $bucket('monthly'),
                'by_service'     => $bucket('by_service_type'),
                'by_wo_type'     => $bucket('by_wo_type'),
                'by_journey'     => $bucket('by_journey'),
                'by_status'      => $bucket('by_status'),
                'by_priority'    => $bucket('by_priority'),
                'by_category'    => $bucket('by_category'),
                'by_building'    => $bucket('by_building'),
            ],
        ]);
    }
}
