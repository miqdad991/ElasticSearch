<?php

namespace App\Services\Dashboard;

use OpenSearch\Client;

class WorkOrderTotalsService
{
    public function __construct(private Client $os) {}

    public function totals(?int $projectId = null, array $filters = []): array
    {
        $index = config('opensearch.index_prefix', 'osool_') . 'work_orders';

        $resp = $this->os->search([
            'index' => $index,
            'body'  => [
                'track_total_hits' => true,
                'size'  => 0,
                'query' => ['bool' => ['must' => $this->buildMust($projectId, $filters)]],
                'aggs'  => [
                    'total_reactive'     => ['filter' => ['term' => ['work_order_type' => 'reactive']]],
                    'total_preventive'   => ['filter' => ['term' => ['work_order_type' => 'preventive']]],
                    'distinct_locations' => ['cardinality' => ['field' => 'property_id']],
                    'late_execution'     => ['filter' => ['script' => [
                        'script' => "doc.containsKey('job_submitted_at') && doc['job_submitted_at'].size()>0 && doc.containsKey('target_at') && doc['target_at'].size()>0 && doc['job_submitted_at'].value.isAfter(doc['target_at'].value)",
                    ]]],
                    'status_open'        => ['filter' => ['term' => ['status_code' => 1]]],
                    'status_in_progress' => ['filter' => ['term' => ['status_code' => 2]]],
                    'status_closed'      => ['filter' => ['term' => ['status_code' => 4]]],
                ],
            ],
        ]);

        $aggs = $resp['aggregations'] ?? [];

        return [
            'locations'      => (int) ($aggs['distinct_locations']['value'] ?? 0),
            'total'          => (int) ($resp['hits']['total']['value'] ?? 0),
            'reactive'       => (int) ($aggs['total_reactive']['doc_count'] ?? 0),
            'preventive'     => (int) ($aggs['total_preventive']['doc_count'] ?? 0),
            'late_execution' => (int) ($aggs['late_execution']['doc_count'] ?? 0),
            'status_open'        => (int) ($aggs['status_open']['doc_count'] ?? 0),
            'status_in_progress' => (int) ($aggs['status_in_progress']['doc_count'] ?? 0),
            'status_closed'      => (int) ($aggs['status_closed']['doc_count'] ?? 0),
        ];
    }

    public function expensesByCategory(?int $projectId = null, int $size = 5, array $filters = []): \Illuminate\Support\Collection
    {
        $index = config('opensearch.index_prefix', 'osool_') . 'work_orders';

        $resp = $this->os->search([
            'index' => $index,
            'body'  => [
                'size'  => 0,
                'query' => ['bool' => ['must' => $this->buildMust($projectId, $filters)]],
                'aggs'  => [
                    'by_category' => [
                        'terms' => ['field' => 'asset_category', 'size' => $size, 'missing' => 'Uncategorized', 'order' => ['total_cost' => 'desc']],
                        'aggs'  => ['total_cost' => ['sum' => ['field' => 'cost']]],
                    ],
                ],
            ],
        ]);

        return collect($resp['aggregations']['by_category']['buckets'] ?? [])->map(fn ($b) => (object) [
            'label' => $b['key'],
            'total' => round($b['total_cost']['value'] ?? 0, 2),
        ]);
    }

    public function filterOptions(?int $projectId = null): array
    {
        $index      = config('opensearch.index_prefix', 'osool_') . 'work_orders';
        $usersIndex = config('opensearch.index_prefix', 'osool_') . 'users';

        $resp = $this->os->search([
            'index' => $index,
            'body'  => [
                'size'  => 0,
                'query' => ['bool' => ['must' => $this->buildMust($projectId)]],
                'aggs'  => [
                    'distinct_users'        => ['terms' => ['field' => 'project_user_id', 'size' => 500]],
                    'distinct_contract_ids' => ['terms' => ['field' => 'contract_id', 'size' => 500]],
                ],
            ],
        ]);

        $aggs = $resp['aggregations'] ?? [];

        $userIds     = collect($aggs['distinct_users']['buckets'] ?? [])->pluck('key')->filter()->values()->toArray();
        $userOptions = collect();
        if ($userIds) {
            $uResp = $this->os->search([
                'index'  => $usersIndex,
                'body'   => [
                    'size'    => 500,
                    'query'   => ['terms' => ['user_id' => $userIds]],
                    '_source' => ['user_id', 'full_name', 'email'],
                ],
            ]);
            $userOptions = collect($uResp['hits']['hits'] ?? [])->map(fn ($h) => (object) [
                'id'           => $h['_source']['user_id'],
                'display_name' => trim($h['_source']['full_name'] ?? '') ?: ($h['_source']['email'] ?? 'User #' . $h['_source']['user_id']),
            ])->sortBy('display_name')->values();
        }

        $contractOptions = collect($aggs['distinct_contract_ids']['buckets'] ?? [])
            ->filter(fn ($b) => $b['key'] > 0)
            ->map(fn ($b) => (object) [
                'id'           => $b['key'],
                'display_name' => 'Contract #' . $b['key'],
            ])->values();

        return compact('userOptions', 'contractOptions');
    }

    private function buildMust(?int $projectId, array $filters = []): array
    {
        $must = [['bool' => ['must_not' => [['term' => ['status_code' => 5]]]]]];

        if ($projectId) {
            $must[] = ['term' => ['project_ids' => $projectId]];
        }
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

        return $must;
    }
}
