<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use OpenSearch\Client;

class MCFollowingDashboardController extends Controller
{
    private const STATUS_LABELS = [
        1 => 'Open', 2 => 'In Progress', 3 => 'On Hold', 4 => 'Closed',
        5 => 'Deleted', 6 => 'Re-open', 7 => 'Warranty', 8 => 'Scheduled',
    ];

    private const USER_TYPES = ['sp_admin', 'supervisor'];

    public function __construct(private Client $os) {}

    public function index(Request $request)
    {
        $index = config('opensearch.index_prefix', 'osool_') . 'work_orders';
        $usersIndex = config('opensearch.index_prefix', 'osool_') . 'users';

        $filters = $request->only(['date_from', 'date_to', 'location_id', 'user_id']);

        // Base: preventive only, exclude deleted (status 5)
        $must = [
            ['term' => ['work_order_type' => 'preventive']],
            ['bool' => ['must_not' => [['term' => ['status_code' => 5]]]]],
        ];

        // Project scoping
        if ($pid = session('selected_project_id')) {
            $must[] = ['term' => ['project_ids' => (int) $pid]];
        }

        // Filters
        if (!empty($filters['date_from']) || !empty($filters['date_to'])) {
            $range = [];
            if (!empty($filters['date_from'])) $range['gte'] = $filters['date_from'];
            if (!empty($filters['date_to']))   $range['lte'] = $filters['date_to'] . 'T23:59:59Z';
            $must[] = ['range' => ['created_at' => $range]];
        }
        if (!empty($filters['location_id'])) {
            $must[] = ['term' => ['property_id' => (int) $filters['location_id']]];
        }
        if (!empty($filters['user_id'])) {
            $uid = (int) $filters['user_id'];
            $must[] = ['bool' => ['should' => [
                ['term' => ['service_provider_id' => $uid]],
                ['term' => ['supervisor_id' => $uid]],
            ], 'minimum_should_match' => 1]];
        }

        $query = ['bool' => ['must' => $must]];

        // Main aggregations
        $resp = $this->os->search([
            'index' => $index,
            'body'  => [
                'track_total_hits' => true,
                'size'  => 0,
                'query' => $query,
                'aggs'  => [
                    'total_closed'   => ['filter' => ['term' => ['status_code' => 4]]],
                    'total_not_closed' => ['filter' => ['bool' => ['must_not' => [['term' => ['status_code' => 4]]]]]],
                    'distinct_locations' => ['cardinality' => ['field' => 'property_id']],
                    // Status pie
                    'by_status' => ['terms' => ['field' => 'status_code', 'size' => 10]],
                    // Location bar (top 15)
                    'by_building' => ['terms' => ['field' => 'building_name', 'size' => 15, 'order' => ['_count' => 'desc']]],
                    // Monthly by status (line chart)
                    'monthly_status' => [
                        'terms' => ['field' => 'created_year_month', 'size' => 60, 'order' => ['_key' => 'asc']],
                        'aggs'  => [
                            'by_status' => ['terms' => ['field' => 'status_code', 'size' => 10]],
                        ],
                    ],
                    // Per service_provider_id breakdown
                    'by_sp' => [
                        'terms' => ['field' => 'service_provider_id', 'size' => 500],
                        'aggs'  => [
                            'by_status' => ['terms' => ['field' => 'status_code', 'size' => 10]],
                        ],
                    ],
                    // Per supervisor_id breakdown
                    'by_supervisor' => [
                        'terms' => ['field' => 'supervisor_id', 'size' => 500],
                        'aggs'  => [
                            'by_status' => ['terms' => ['field' => 'status_code', 'size' => 10]],
                        ],
                    ],
                    // Distinct buildings for dropdown
                    'location_list' => [
                        'terms' => ['field' => 'building_name', 'size' => 500],
                        'aggs'  => [
                            'property_id' => ['terms' => ['field' => 'property_id', 'size' => 1]],
                        ],
                    ],
                ],
            ],
        ]);

        $aggs = $resp['aggregations'] ?? [];
        $totalPreventive = $resp['hits']['total']['value'] ?? 0;
        $totalClosed = $aggs['total_closed']['doc_count'] ?? 0;

        $totals = (object) [
            'total_preventive' => $totalPreventive,
            'total_closed'     => $totalClosed,
            'total_not_closed' => $aggs['total_not_closed']['doc_count'] ?? 0,
            'total_locations'  => $aggs['distinct_locations']['value'] ?? 0,
        ];
        $completionPct = $totalPreventive > 0 ? round(($totalClosed / $totalPreventive) * 100, 1) : 0;

        // Status pie
        $perStatus = collect($aggs['by_status']['buckets'] ?? [])->map(fn ($b) => (object) [
            'status' => $b['key'],
            'label'  => self::STATUS_LABELS[$b['key']] ?? ('Status ' . $b['key']),
            'total'  => $b['doc_count'],
        ]);

        // Location bar
        $perLocation = collect($aggs['by_building']['buckets'] ?? [])->map(fn ($b) => (object) [
            'label' => $b['key'], 'total' => $b['doc_count'],
        ]);

        // Monthly line per status
        $months = collect($aggs['monthly_status']['buckets'] ?? [])->pluck('key')->values();
        $statusesInUse = collect();
        $monthlyMap = [];
        foreach ($aggs['monthly_status']['buckets'] ?? [] as $mBucket) {
            foreach ($mBucket['by_status']['buckets'] ?? [] as $sBucket) {
                $monthlyMap[$sBucket['key']][$mBucket['key']] = $sBucket['doc_count'];
                $statusesInUse->push($sBucket['key']);
            }
        }
        $statusesInUse = $statusesInUse->unique()->sort()->values();
        $lineSeries = $statusesInUse->map(fn ($status) => [
            'name'   => self::STATUS_LABELS[$status] ?? ('Status ' . $status),
            'status' => (int) $status,
            'data'   => $months->map(fn ($m) => (int) ($monthlyMap[$status][$m] ?? 0))->values()->toArray(),
        ])->values();

        // Per-user completion: merge SP + supervisor buckets, then resolve names
        $userBuckets = [];
        foreach (['by_sp', 'by_supervisor'] as $aggKey) {
            foreach ($aggs[$aggKey]['buckets'] ?? [] as $uBucket) {
                $uid = $uBucket['key'];
                if (!isset($userBuckets[$uid])) {
                    $userBuckets[$uid] = [];
                }
                foreach ($uBucket['by_status']['buckets'] ?? [] as $sb) {
                    $userBuckets[$uid][$sb['key']] = ($userBuckets[$uid][$sb['key']] ?? 0) + $sb['doc_count'];
                }
            }
        }

        // Fetch user details for all referenced user IDs
        $allUserIds = array_keys($userBuckets);
        $userDetailsMap = [];
        if ($allUserIds) {
            $uResp = $this->os->search([
                'index' => $usersIndex,
                'body'  => [
                    'size'  => 2000,
                    'query' => ['bool' => ['must' => [
                        ['terms' => ['user_id' => array_map('intval', $allUserIds)]],
                        ['terms' => ['user_type' => self::USER_TYPES]],
                    ]]],
                    '_source' => ['user_id', 'full_name', 'email', 'user_type', 'user_type_label'],
                ],
            ]);
            foreach ($uResp['hits']['hits'] ?? [] as $h) {
                $s = $h['_source'];
                $userDetailsMap[$s['user_id']] = $s;
            }
        }

        $perUser = collect($userBuckets)
            ->filter(fn ($_, $uid) => isset($userDetailsMap[$uid]))
            ->map(function ($byStatus, $uid) use ($userDetailsMap) {
                $u = $userDetailsMap[$uid];
                $total = array_sum($byStatus);
                $closed = $byStatus[4] ?? 0;
                return (object) [
                    'user_id'        => $uid,
                    'user_name'      => trim($u['full_name'] ?? '') ?: ($u['email'] ?? 'User #' . $uid),
                    'type_label'     => $u['user_type_label'] ?? $u['user_type'] ?? '',
                    'by_status'      => $byStatus,
                    'total'          => $total,
                    'closed'         => $closed,
                    'completion_pct' => $total > 0 ? round($closed / $total * 100, 1) : 0,
                ];
            })
            ->sortByDesc('total')
            ->values();

        // Location dropdown
        $locationOptions = collect($aggs['location_list']['buckets'] ?? [])->map(fn ($b) => (object) [
            'id'            => $b['property_id']['buckets'][0]['key'] ?? 0,
            'building_name' => $b['key'],
        ])->sortBy('building_name')->values();

        // User dropdown: SP/Supervisor users
        $userOptions = collect();
        $spSvUserResp = $this->os->search([
            'index' => $usersIndex,
            'body'  => [
                'size'  => 2000,
                'query' => ['bool' => ['must' => [
                    ['terms' => ['user_type' => self::USER_TYPES]],
                    ['term'  => ['is_deleted' => false]],
                ]]],
                '_source' => ['user_id', 'full_name', 'email', 'user_type', 'user_type_label'],
            ],
        ]);
        // If project selected, scope to project users
        if ($pid = session('selected_project_id')) {
            $userOptions = collect($spSvUserResp['hits']['hits'] ?? [])
                ->map(fn ($h) => $h['_source'])
                ->filter(fn ($u) => in_array((int) $pid, $u['project_ids'] ?? []))
                ->map(fn ($u) => (object) [
                    'id'           => $u['user_id'],
                    'user_type'    => $u['user_type'] ?? '',
                    'display_name' => trim($u['full_name'] ?? '') ?: ($u['email'] ?? 'User #' . $u['user_id']),
                    'type_label'   => $u['user_type_label'] ?? $u['user_type'] ?? '',
                ])->sortBy('display_name')->values();
        } else {
            $userOptions = collect($spSvUserResp['hits']['hits'] ?? [])->map(fn ($h) => (object) [
                'id'           => $h['_source']['user_id'],
                'user_type'    => $h['_source']['user_type'] ?? '',
                'display_name' => trim($h['_source']['full_name'] ?? '') ?: ($h['_source']['email'] ?? 'User #' . $h['_source']['user_id']),
                'type_label'   => $h['_source']['user_type_label'] ?? $h['_source']['user_type'] ?? '',
            ])->sortBy('display_name')->values();
        }

        $statusLabels = self::STATUS_LABELS;

        return view('dashboards.mc-following', compact(
            'filters', 'totals', 'completionPct', 'perStatus',
            'perLocation', 'perUser', 'statusLabels',
            'months', 'lineSeries', 'locationOptions', 'userOptions'
        ));
    }
}
