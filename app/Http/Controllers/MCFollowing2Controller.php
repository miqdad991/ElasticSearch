<?php

namespace App\Http\Controllers;

use App\Services\Dashboard\PropertyMapService;
use App\Services\Dashboard\WorkOrderTotalsService;
use Illuminate\Http\Request;
use OpenSearch\Client;

class MCFollowing2Controller extends Controller
{
    private const STATUS_LABELS = [
        1 => 'Open', 2 => 'In Progress', 3 => 'On Hold', 4 => 'Closed',
        5 => 'Deleted', 6 => 'Re-open', 7 => 'Warranty', 8 => 'Scheduled',
    ];

    private const FOLLOW_USER_TYPES = ['sp_admin', 'supervisor'];

    public function __construct(
        private PropertyMapService $propertyMap,
        private WorkOrderTotalsService $woTotals,
        private Client $os,
    ) {}

    public function index(Request $request)
    {
        $projectId = session('selected_project_id');
        $filters   = $request->only(['date_from', 'date_to', 'location_id', 'user_id']);

        $propertyMapData    = $this->propertyMap->build($projectId);
        $buildingNames      = $this->propertyMap->buildingNames($projectId);
        $totals             = $this->woTotals->totals($projectId ? (int) $projectId : null, $filters);
        $expensesByCategory = $this->woTotals->expensesByCategory($projectId ? (int) $projectId : null, 5, $filters);

        $expensesTotal = $expensesByCategory->sum('total');
        $expensesTotalFormatted = $expensesTotal >= 1_000_000
            ? number_format($expensesTotal / 1_000_000, 2) . ' M'
            : ($expensesTotal >= 1_000 ? number_format($expensesTotal / 1_000, 1) . ' K' : number_format($expensesTotal, 0));

        $lateExecution = $totals['late_execution'];
        $totalWOs      = $totals['total'];
        $latePct       = $totalWOs > 0 ? min(100, round(($lateExecution / $totalWOs) * 100)) : 0;
        $lateLabel     = number_format($lateExecution) . ' / ' . number_format($totalWOs);

        // Preventive-scoped closed/not-closed/completion + monthly-by-status (matches /mc-following)
        $following = $this->preventiveFollowing($projectId, $filters);
        $totalPreventive = $following['total'];
        $closedWO        = $following['closed'];
        $notClosedWO     = $following['not_closed'];
        $completionPct   = $totalPreventive > 0 ? round(($closedWO / $totalPreventive) * 100, 1) : 0;
        $months          = $following['months'];
        $lineSeries      = $following['line_series'];
        $perStatus       = $following['per_status'];
        $locationOptions = $following['location_options'];

        // SP / Supervisor user dropdown
        $userOptions = $this->followingUserOptions($projectId);

        return view('dashboards.mc-following2', compact(
            'propertyMapData', 'totals', 'expensesByCategory', 'expensesTotal', 'expensesTotalFormatted',
            'latePct', 'lateLabel', 'filters', 'buildingNames',
            'closedWO', 'notClosedWO', 'completionPct',
            'months', 'lineSeries', 'perStatus',
            'locationOptions', 'userOptions'
        ));
    }

    /**
     * Same scope as MCFollowingDashboardController: preventive work orders,
     * deleted (status 5) excluded. Returns totals plus a monthly-by-status
     * series suitable for the User Completion Status line chart.
     */
    private function preventiveFollowing(?int $projectId, array $filters): array
    {
        $index = config('opensearch.index_prefix', 'osool_') . 'work_orders';

        $must = [
            ['term' => ['work_order_type' => 'preventive']],
            ['bool' => ['must_not' => [['term' => ['status_code' => 5]]]]],
        ];

        if ($projectId) {
            $must[] = ['term' => ['project_ids' => (int) $projectId]];
        }
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

        $resp = $this->os->search([
            'index' => $index,
            'body'  => [
                'track_total_hits' => true,
                'size'  => 0,
                'query' => ['bool' => ['must' => $must]],
                'aggs'  => [
                    'closed'     => ['filter' => ['term' => ['status_code' => 4]]],
                    'not_closed' => ['filter' => ['bool' => ['must_not' => [['term' => ['status_code' => 4]]]]]],
                    'by_status'  => ['terms' => ['field' => 'status_code', 'size' => 10]],
                    'monthly_status' => [
                        'terms' => ['field' => 'created_year_month', 'size' => 60, 'order' => ['_key' => 'asc']],
                        'aggs'  => [
                            'by_status' => ['terms' => ['field' => 'status_code', 'size' => 10]],
                        ],
                    ],
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

        // Pivot monthly_status -> [{ name, status, data: [counts aligned with months] }]
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

        // Status pie: preventive WOs grouped by status_code
        $perStatus = collect($aggs['by_status']['buckets'] ?? [])->map(fn ($b) => (object) [
            'status' => (int) $b['key'],
            'label'  => self::STATUS_LABELS[$b['key']] ?? ('Status ' . $b['key']),
            'total'  => (int) $b['doc_count'],
        ]);

        // Location dropdown (building_name -> property_id)
        $locationOptions = collect($aggs['location_list']['buckets'] ?? [])->map(fn ($b) => (object) [
            'id'            => $b['property_id']['buckets'][0]['key'] ?? 0,
            'building_name' => $b['key'],
        ])->sortBy('building_name')->values();

        return [
            'total'            => (int) ($resp['hits']['total']['value'] ?? 0),
            'closed'           => (int) ($aggs['closed']['doc_count'] ?? 0),
            'not_closed'       => (int) ($aggs['not_closed']['doc_count'] ?? 0),
            'months'           => $months,
            'line_series'      => $lineSeries,
            'per_status'       => $perStatus,
            'location_options' => $locationOptions,
        ];
    }

    /**
     * SP / Supervisor user dropdown options (mirrors /mc-following).
     */
    private function followingUserOptions(?int $projectId): \Illuminate\Support\Collection
    {
        $usersIndex = config('opensearch.index_prefix', 'osool_') . 'users';

        $resp = $this->os->search([
            'index' => $usersIndex,
            'body'  => [
                'size'  => 2000,
                'query' => ['bool' => ['must' => [
                    ['terms' => ['user_type' => self::FOLLOW_USER_TYPES]],
                    ['term'  => ['is_deleted' => false]],
                ]]],
                '_source' => ['user_id', 'full_name', 'email', 'user_type', 'user_type_label', 'project_ids'],
            ],
        ]);

        $rows = collect($resp['hits']['hits'] ?? [])->map(fn ($h) => $h['_source']);
        if ($projectId) {
            $rows = $rows->filter(fn ($u) => in_array((int) $projectId, $u['project_ids'] ?? []));
        }

        return $rows->map(fn ($u) => (object) [
            'id'           => $u['user_id'],
            'user_type'    => $u['user_type'] ?? '',
            'display_name' => trim($u['full_name'] ?? '') ?: ($u['email'] ?? 'User #' . $u['user_id']),
            'type_label'   => $u['user_type_label'] ?? $u['user_type'] ?? '',
        ])->sortBy('display_name')->values();
    }
}
