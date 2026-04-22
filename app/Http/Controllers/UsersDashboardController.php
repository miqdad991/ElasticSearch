<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use OpenSearch\Client;

class UsersDashboardController extends Controller
{
    public function __construct(private Client $os) {}

    public function index(Request $request)
    {
        $index = config('opensearch.index_prefix', 'osool_') . 'users';

        $filters = array_filter([
            'user_type'  => $request->query('user_type'),
            'is_active'  => $request->query('is_active'),
            'is_deleted' => $request->query('is_deleted'),
            'project_id' => $request->query('project_id'),
        ], fn ($v) => $v !== null && $v !== '');

        $must = [];
        foreach ($filters as $f => $v) {
            if ($f === 'project_id') {
                $must[] = ['term' => ['project_ids' => (int) $v]];
            } elseif (in_array($v, ['true','false'], true)) {
                $must[] = ['term' => [$f => $v === 'true']];
            } else {
                $must[] = ['term' => [$f => is_numeric($v) ? (int) $v : $v]];
            }
        }
        if ($pid = session('selected_project_id')) {
            $must[] = ['term' => ['project_ids' => (int) $pid]];
        }
        $query = $must ? ['bool' => ['must' => $must]] : ['match_all' => (object) []];

        $resp = $this->os->search([
            'index' => $index,
            'body'  => [
                'size'  => 50,
                'query' => $query,
                'sort'  => [['created_at' => 'desc']],
                'aggs'  => [
                    'active'   => ['filter' => ['term' => ['is_active'  => true]]],
                    'inactive' => ['filter' => ['term' => ['is_active'  => false]]],
                    'deleted'  => ['filter' => ['term' => ['is_deleted' => true]]],
                    'by_type'  => ['terms' => ['field' => 'user_type', 'size' => 15]],
                    'by_city'  => ['terms' => ['field' => 'city_name', 'size' => 15]],
                    'by_project'=> ['terms'=> ['field' => 'project_ids', 'size' => 20]],
                    'monthly'  => ['terms' => ['field' => 'created_year_month', 'size' => 60, 'order' => ['_key' => 'asc']]],
                ],
            ],
        ]);

        $hits = $resp['hits']['hits'] ?? [];
        $aggs = $resp['aggregations'] ?? [];

        $cards = [
            'Total Users' => $resp['hits']['total']['value'] ?? 0,
            'Active'      => $aggs['active']['doc_count']    ?? 0,
            'Inactive'    => $aggs['inactive']['doc_count']  ?? 0,
            'Deleted'     => $aggs['deleted']['doc_count']   ?? 0,
        ];

        $bucket = fn (string $k) => collect($aggs[$k]['buckets'] ?? [])
            ->map(fn ($b) => ['label' => (string) $b['key'], 'count' => $b['doc_count']])
            ->values();

        return view('dashboards.users', [
            'filters' => $filters,
            'cards'   => $cards,
            'rows'    => collect($hits)->pluck('_source'),
            'charts'  => [
                'monthly'    => $bucket('monthly'),
                'by_type'    => $bucket('by_type'),
                'by_city'    => $bucket('by_city'),
                'by_project' => $bucket('by_project'),
            ],
        ]);
    }
}
