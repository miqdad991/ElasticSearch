<?php

namespace App\Http\Controllers;

use App\Services\Dashboard\WorkOrderTotalsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use OpenSearch\Client;

class DashboardBuilderController extends Controller
{
    public const KPI_OPTIONS = [
        'total_workorders'  => ['label' => 'Total Work Orders',    'color' => '#6366f1'],
        'preventive'        => ['label' => 'Preventive',           'color' => '#059669'],
        'reactive'          => ['label' => 'Reactive',             'color' => '#dc2626'],
        'hard_service'      => ['label' => 'Hard Service',         'color' => '#0ea5e9'],
        'soft_service'      => ['label' => 'Soft Service',         'color' => '#8b5cf6'],
        'maintenance_req'   => ['label' => 'Maintenance Requests', 'color' => '#f59e0b'],
        'service_providers' => ['label' => 'Service Providers',    'color' => '#14b8a6'],
        'total_cost'        => ['label' => 'Total Expenses',       'color' => '#f97316'],
        'finished'          => ['label' => 'Finished',             'color' => '#22c55e'],
        'in_progress'       => ['label' => 'Open / In Progress',   'color' => '#3b82f6'],
        'late_execution'    => ['label' => 'Late Execution',       'color' => '#ef4444'],
        'locations'         => ['label' => 'Locations',            'color' => '#6366f1'],
        'contracts'         => ['label' => 'Contracts',            'color' => '#0ea5e9'],
    ];

    public const CHART_OPTIONS = [
        'monthly'      => ['label' => 'Monthly Trend',         'types' => ['line', 'bar', 'area']],
        'by_status'    => ['label' => 'Work Order Status',     'types' => ['donut', 'pie', 'bar']],
        'by_category'  => ['label' => 'By Category',           'types' => ['line', 'bar']],
        'by_building'  => ['label' => 'By Building',           'types' => ['bar']],
        'by_wo_type'   => ['label' => 'By WO Type',            'types' => ['donut', 'pie', 'bar']],
        'by_service'   => ['label' => 'By Service Type',       'types' => ['donut', 'pie', 'bar']],
        'by_journey'   => ['label' => 'By Journey Stage',      'types' => ['bar', 'pie', 'donut']],
        'by_priority'  => ['label' => 'By Priority',           'types' => ['bar', 'pie', 'donut']],
        'expense_type' => ['label' => 'Expenses by Type',      'types' => ['pie', 'donut', 'bar']],
        'expense_cat'  => ['label' => 'Expenses by Category',  'types' => ['table', 'bar']],
    ];

    public const PROPERTIES_KPI_OPTIONS = [
        'total_properties'  => ['label' => 'Total Properties',    'color' => '#0ea5e9'],
        'total_buildings'   => ['label' => 'Total Buildings',     'color' => '#10b981'],
        'buildings_only'    => ['label' => 'Single Buildings',    'color' => '#f59e0b'],
        'complexes'         => ['label' => 'Complexes',           'color' => '#8b5cf6'],
        'active_properties' => ['label' => 'Active Properties',   'color' => '#22c55e'],
        'total_contracts'   => ['label' => 'Total Contracts',     'color' => '#6366f1'],
        'active_contracts'  => ['label' => 'Active Contracts',    'color' => '#3b82f6'],
        'rent_contracts'    => ['label' => 'Rent Contracts',      'color' => '#ec4899'],
        'lease_contracts'   => ['label' => 'Lease Contracts',     'color' => '#14b8a6'],
        'auto_renewal'      => ['label' => 'Auto-Renewal',        'color' => '#f97316'],
        'total_budget'      => ['label' => 'Total Budget',        'color' => '#06b6d4'],
        'total_assets'      => ['label' => 'Total Assets',        'color' => '#a855f7'],
        'total_wo'          => ['label' => 'Total Work Orders',   'color' => '#d946ef'],
        'total_wo_cost'     => ['label' => 'Total WO Cost',       'color' => '#ef4444'],
    ];

    public const PROPERTIES_CHART_OPTIONS = [
        'monthly'   => ['label' => 'Monthly Trend',                'types' => ['area', 'line', 'bar']],
        'by_type'   => ['label' => 'By Property Type',            'types' => ['donut', 'pie', 'bar']],
        'by_status' => ['label' => 'By Status',                   'types' => ['donut', 'pie', 'bar']],
        'by_region' => ['label' => 'By Region',                   'types' => ['bar']],
        'by_city'   => ['label' => 'By City',                     'types' => ['bar']],
        'top_props' => ['label' => 'Top Properties by Contracts', 'types' => ['bar']],
        'by_ejar'   => ['label' => 'By Ejar Status',              'types' => ['bar', 'pie', 'donut']],
    ];

    public const BILLING_KPI_OPTIONS = [
        'total_contracts'  => ['label' => 'Total Contracts',       'color' => '#6366f1'],
        'total_value'      => ['label' => 'Total Contract Value',  'color' => '#0ea5e9'],
        'rent'             => ['label' => 'Rent Contracts',        'color' => '#3b82f6'],
        'lease'            => ['label' => 'Lease Contracts',       'color' => '#8b5cf6'],
        'active'           => ['label' => 'Active Contracts',      'color' => '#22c55e'],
        'auto_renewal'     => ['label' => 'Auto-Renewal',          'color' => '#14b8a6'],
        'security_deposits'=> ['label' => 'Security Deposits',     'color' => '#f59e0b'],
        'late_fees'        => ['label' => 'Late Fees',             'color' => '#ef4444'],
        'brokerage_fees'   => ['label' => 'Brokerage Fees',        'color' => '#f97316'],
        'retainer_fees'    => ['label' => 'Retainer Fees',         'color' => '#ec4899'],
        'collected'        => ['label' => 'Collected',             'color' => '#10b981'],
        'outstanding'      => ['label' => 'Outstanding',           'color' => '#dc2626'],
        'overdue_amount'   => ['label' => 'Overdue Amount',        'color' => '#b91c1c'],
        'payment_due'      => ['label' => 'Payment Due',           'color' => '#d946ef'],
    ];

    public const BILLING_CHART_OPTIONS = [
        'monthly'      => ['label' => 'Collections vs Outstanding', 'types' => ['stacked_bar']],
        'aging'        => ['label' => 'Aging Buckets',              'types' => ['bar', 'pie', 'donut']],
        'by_type'      => ['label' => 'By Contract Type',          'types' => ['donut', 'pie', 'bar']],
        'by_ejar'      => ['label' => 'Ejar Sync Status',          'types' => ['donut', 'pie', 'bar']],
        'by_ptype'     => ['label' => 'Payment Methods',           'types' => ['donut', 'pie', 'bar']],
        'top_tenants'  => ['label' => 'Top Tenants by Outstanding', 'types' => ['bar']],
    ];

    public const USERS_KPI_OPTIONS = [
        'total_users' => ['label' => 'Total Users',   'color' => '#6366f1'],
        'active'      => ['label' => 'Active',        'color' => '#22c55e'],
        'inactive'    => ['label' => 'Inactive',      'color' => '#94a3b8'],
        'deleted'     => ['label' => 'Deleted',       'color' => '#ef4444'],
    ];

    public const USERS_CHART_OPTIONS = [
        'monthly'    => ['label' => 'Onboarding Trend',  'types' => ['area', 'line', 'bar']],
        'by_type'    => ['label' => 'By User Type',      'types' => ['donut', 'pie', 'bar']],
        'by_city'    => ['label' => 'By City',           'types' => ['bar', 'donut', 'pie']],
        'by_project' => ['label' => 'By Project',        'types' => ['bar']],
    ];

    public const ASSETS_KPI_OPTIONS = [
        'total_assets'    => ['label' => 'Total Assets',     'color' => '#14b8a6'],
        'categories'      => ['label' => 'Categories',       'color' => '#6366f1'],
        'buildings'       => ['label' => 'Buildings',        'color' => '#f59e0b'],
        'with_status'     => ['label' => 'With Status',      'color' => '#22c55e'],
        'without_status'  => ['label' => 'No Status',        'color' => '#94a3b8'],
        'under_warranty'  => ['label' => 'Under Warranty',   'color' => '#a855f7'],
        'total_value'     => ['label' => 'Total Value (SAR)','color' => '#ef4444'],
    ];

    public const ASSETS_CHART_OPTIONS = [
        'monthly'      => ['label' => 'Assets Added per Month', 'types' => ['area', 'line', 'bar']],
        'by_category'  => ['label' => 'By Category',           'types' => ['bar', 'donut', 'pie']],
        'by_status'    => ['label' => 'By Status',             'types' => ['donut', 'pie', 'bar']],
        'by_building'  => ['label' => 'By Building',           'types' => ['bar']],
        'by_name'      => ['label' => 'By Asset Name',         'types' => ['bar']],
        'by_manufac'   => ['label' => 'Top Manufacturers',     'types' => ['bar']],
    ];

    public const CONTRACTS_KPI_OPTIONS = [
        'total_contracts' => ['label' => 'Total Contracts',  'color' => '#6366f1'],
        'total_value'     => ['label' => 'Total Value',      'color' => '#0ea5e9'],
        'avg_value'       => ['label' => 'Average Value',    'color' => '#8b5cf6'],
        'active'          => ['label' => 'Active',           'color' => '#22c55e'],
        'expired'         => ['label' => 'Expired',          'color' => '#94a3b8'],
        'subcontracts'    => ['label' => 'Subcontracts',     'color' => '#f59e0b'],
        'scheduled_total' => ['label' => 'Scheduled Total',  'color' => '#14b8a6'],
        'paid_total'      => ['label' => 'Paid',             'color' => '#10b981'],
        'pending_total'   => ['label' => 'Pending',          'color' => '#f97316'],
        'overdue_total'   => ['label' => 'Overdue',          'color' => '#ef4444'],
        'closed_wo'       => ['label' => 'Closed WOs',       'color' => '#3b82f6'],
        'wo_cost'         => ['label' => 'WO Extras Total',  'color' => '#ec4899'],
    ];

    public const CONTRACTS_CHART_OPTIONS = [
        'by_type'     => ['label' => 'Value by Contract Type',          'types' => ['bar', 'donut', 'pie']],
        'top_sp'      => ['label' => 'Top Service Providers by Value',  'types' => ['bar']],
        'top_overdue' => ['label' => 'Top Contracts by Overdue Amount', 'types' => ['bar']],
    ];

    public const OVERVIEW_KPI_OPTIONS = [
        'total_projects'      => ['label' => 'Total Projects',      'color' => '#6366f1'],
        'active_projects'     => ['label' => 'Active Projects',     'color' => '#22c55e'],
        'service_providers'   => ['label' => 'Service Providers',   'color' => '#0ea5e9'],
        'subscriptions'       => ['label' => 'Subscriptions',       'color' => '#8b5cf6'],
        'total_wo'            => ['label' => 'Total Work Orders',   'color' => '#4f46e5'],
        'open_wo'             => ['label' => 'Open WOs',            'color' => '#f59e0b'],
        'in_progress_wo'      => ['label' => 'In Progress WOs',     'color' => '#0ea5e9'],
        'closed_wo'           => ['label' => 'Closed WOs',          'color' => '#22c55e'],
        'preventive_wo'       => ['label' => 'Preventive WOs',      'color' => '#10b981'],
        'reactive_wo'         => ['label' => 'Reactive WOs',        'color' => '#dc2626'],
        'wo_cost'             => ['label' => 'WO Total Cost',       'color' => '#ef4444'],
        'total_props'         => ['label' => 'Total Properties',    'color' => '#0ea5e9'],
        'active_props'        => ['label' => 'Active Properties',   'color' => '#22c55e'],
        'total_assets'        => ['label' => 'Total Assets',        'color' => '#14b8a6'],
        'under_warranty'      => ['label' => 'Under Warranty',      'color' => '#a855f7'],
        'asset_value'         => ['label' => 'Asset Value',         'color' => '#f97316'],
        'total_users'         => ['label' => 'Total Users',         'color' => '#6366f1'],
        'active_users'        => ['label' => 'Active Users',        'color' => '#22c55e'],
        'billing_collected'   => ['label' => 'Collected',           'color' => '#10b981'],
        'billing_outstanding' => ['label' => 'Outstanding',         'color' => '#f59e0b'],
        'billing_overdue'     => ['label' => 'Billing Overdue',     'color' => '#ef4444'],
        'total_contracts'     => ['label' => 'Exec. Contracts',     'color' => '#22c55e'],
        'contract_value'      => ['label' => 'Contract Value',      'color' => '#06b6d4'],
        'contract_overdue'    => ['label' => 'Contract Overdue',    'color' => '#b91c1c'],
    ];

    public const OVERVIEW_CHART_OPTIONS = [
        'wo_by_status'      => ['label' => 'WO by Status',           'types' => ['donut', 'pie', 'bar']],
        'wo_by_type'        => ['label' => 'WO by Type',             'types' => ['donut', 'pie', 'bar']],
        'prop_by_region'    => ['label' => 'Properties by Region',   'types' => ['bar']],
        'asset_by_category' => ['label' => 'Assets by Category',     'types' => ['bar', 'donut', 'pie']],
        'user_by_type'      => ['label' => 'Users by Type',          'types' => ['bar', 'donut', 'pie']],
        'billing_summary'   => ['label' => 'Billing Summary',        'types' => ['bar']],
        'contract_by_type'  => ['label' => 'Contracts by Type',      'types' => ['bar', 'donut', 'pie']],
    ];

    public function __construct(private WorkOrderTotalsService $woTotals) {}

    public function select()
    {
        return view('dashboards.builder-select');
    }

    public function index(string $type)
    {
        [$kpiOptions, $chartOptions] = $this->optionsForType($type);
        $config = session("dashboard_builder_config_{$type}", $this->defaultConfig($type));
        return view('dashboards.builder', compact('type', 'kpiOptions', 'chartOptions', 'config'));
    }

    public function save(string $type, Request $request)
    {
        [, $chartOptions] = $this->optionsForType($type);
        $charts = [];
        foreach (array_keys($chartOptions) as $key) {
            $charts[$key] = [
                'enabled' => $request->boolean("charts_{$key}_enabled"),
                'type'    => $request->input("charts_{$key}_type", $chartOptions[$key]['types'][0]),
            ];
        }
        session(["dashboard_builder_config_{$type}" => [
            'name'         => $request->input('name', 'My Dashboard'),
            'kpis'         => $request->input('kpis', []),
            'kpi_cols'     => (int) $request->input('kpi_cols', 4),
            'charts'       => $charts,
            'show_filters' => $request->boolean('show_filters'),
            'show_map'     => $request->boolean('show_map'),
            'show_table'   => $request->boolean('show_table'),
        ]]);
        return redirect()->route('dashboard.preview', ['type' => $type]);
    }

    public function preview(string $type, Request $request, Client $os)
    {
        return match($type) {
            'properties' => $this->previewProperties($request, $os),
            'billing'    => $this->previewBilling($request, $os),
            'users'      => $this->previewUsers($request, $os),
            'assets'     => $this->previewAssets($request, $os),
            'contracts'  => $this->previewContracts($request, $os),
            'overview'   => $this->previewOverview($request, $os),
            default      => $this->previewWorkOrders($request, $os),
        };
    }

    // -------------------------------------------------------------------------

    private function previewWorkOrders(Request $request, Client $os): \Illuminate\View\View
    {
        $config  = session('dashboard_builder_config_work-orders', $this->defaultConfig('work-orders'));
        $filters = $request->only(['date_from', 'date_to', 'user_id', 'contract_id']);
        $index   = config('opensearch.index_prefix', 'osool_') . 'work_orders';
        $pid     = session('selected_project_id');

        $must = [['bool' => ['must_not' => [['term' => ['status_code' => 5]]]]]];
        if ($pid) $must[] = ['term' => ['project_ids' => (int) $pid]];
        if (!empty($filters['date_from']) || !empty($filters['date_to'])) {
            $range = [];
            if (!empty($filters['date_from'])) $range['gte'] = $filters['date_from'];
            if (!empty($filters['date_to']))   $range['lte'] = $filters['date_to'] . 'T23:59:59Z';
            $must[] = ['range' => ['created_at' => $range]];
        }
        if (!empty($filters['user_id']))     $must[] = ['term' => ['project_user_id' => (int) $filters['user_id']]];
        if (!empty($filters['contract_id'])) $must[] = ['term' => ['contract_id'     => (int) $filters['contract_id']]];

        $resp = $os->search([
            'index' => $index,
            'body'  => [
                'track_total_hits' => true,
                'size'  => 0,
                'query' => ['bool' => ['must' => $must]],
                'aggs'  => [
                    'total_reactive'        => ['filter' => ['term' => ['work_order_type' => 'reactive']]],
                    'total_preventive'      => ['filter' => ['term' => ['work_order_type' => 'preventive']]],
                    'total_cost'            => ['sum'    => ['field' => 'cost']],
                    'distinct_locations'    => ['cardinality' => ['field' => 'property_id']],
                    'distinct_contracts'    => ['cardinality' => ['field' => 'contract_id']],
                    'distinct_mr'           => ['cardinality' => ['field' => 'maintenance_request_id']],
                    'distinct_sps'          => ['cardinality' => ['field' => 'service_provider_id']],
                    'finished'              => ['filter' => ['term' => ['workorder_journey' => 'finished']]],
                    'in_progress'           => ['filter' => ['terms' => ['workorder_journey' => ['submitted', 'job_execution', 'job_evaluation', 'job_approval']]]],
                    'hard_service'          => ['filter' => ['term' => ['service_type' => 'hard']]],
                    'soft_service'          => ['filter' => ['term' => ['service_type' => 'soft']]],
                    'late_execution'        => ['filter' => ['script' => ['script' => "doc.containsKey('job_submitted_at') && doc['job_submitted_at'].size()>0 && doc.containsKey('target_at') && doc['target_at'].size()>0 && doc['job_submitted_at'].value.isAfter(doc['target_at'].value)"]]],
                    'by_status'             => ['terms' => ['field' => 'status_code', 'size' => 10]],
                    'by_category'           => ['terms' => ['field' => 'asset_category', 'size' => 5, 'order' => ['_count' => 'desc']], 'aggs' => ['monthly' => ['terms' => ['field' => 'created_year_month', 'size' => 60, 'order' => ['_key' => 'asc']]]]],
                    'by_building'           => ['terms' => ['field' => 'building_name', 'size' => 15, 'order' => ['_count' => 'desc']]],
                    'by_wo_type'            => ['terms' => ['field' => 'work_order_type', 'size' => 10]],
                    'by_service_type'       => ['terms' => ['field' => 'service_type', 'size' => 10]],
                    'by_journey'            => ['terms' => ['field' => 'workorder_journey', 'size' => 10]],
                    'by_priority'           => ['terms' => ['field' => 'priority_level', 'size' => 10]],
                    'all_months'            => ['terms' => ['field' => 'created_year_month', 'size' => 60, 'order' => ['_key' => 'asc']]],
                    'expense_by_type'       => ['terms' => ['field' => 'work_order_type', 'size' => 10, 'missing' => 'Unspecified'], 'aggs' => ['total_cost' => ['sum' => ['field' => 'cost']]]],
                    'expense_by_cat'        => ['terms' => ['field' => 'asset_category', 'size' => 20, 'missing' => 'Uncategorized', 'order' => ['total_cost' => 'desc']], 'aggs' => ['total_cost' => ['sum' => ['field' => 'cost']]]],
                    'distinct_users'        => ['terms' => ['field' => 'project_user_id', 'size' => 500]],
                    'distinct_contract_ids' => ['terms' => ['field' => 'contract_id', 'size' => 500]],
                ],
            ],
        ]);

        $aggs     = $resp['aggregations'] ?? [];
        $totalWOs = (int) ($resp['hits']['total']['value'] ?? 0);
        $statusLabels = [1=>'Open',2=>'In Progress',3=>'On Hold',4=>'Closed',5=>'Deleted',6=>'Re-open',7=>'Warranty',8=>'Scheduled'];

        $kpiValues = [
            'total_workorders'  => $totalWOs,
            'preventive'        => (int) ($aggs['total_preventive']['doc_count'] ?? 0),
            'reactive'          => (int) ($aggs['total_reactive']['doc_count']   ?? 0),
            'hard_service'      => (int) ($aggs['hard_service']['doc_count']     ?? 0),
            'soft_service'      => (int) ($aggs['soft_service']['doc_count']     ?? 0),
            'maintenance_req'   => (int) ($aggs['distinct_mr']['value']          ?? 0),
            'service_providers' => (int) ($aggs['distinct_sps']['value']         ?? 0),
            'total_cost'        => round($aggs['total_cost']['value']            ?? 0, 2),
            'finished'          => (int) ($aggs['finished']['doc_count']         ?? 0),
            'in_progress'       => (int) ($aggs['in_progress']['doc_count']      ?? 0),
            'late_execution'    => (int) ($aggs['late_execution']['doc_count']   ?? 0),
            'locations'         => (int) ($aggs['distinct_locations']['value']   ?? 0),
            'contracts'         => (int) ($aggs['distinct_contracts']['value']   ?? 0),
        ];

        $allMonths = collect($aggs['all_months']['buckets'] ?? [])->pluck('key')->values();

        $chartData = [
            'monthly'            => collect($aggs['all_months']['buckets'] ?? [])->map(fn($b) => ['label' => $b['key'], 'count' => $b['doc_count']])->values(),
            'by_status'          => collect($aggs['by_status']['buckets'] ?? [])->map(fn($b) => ['label' => $statusLabels[$b['key']] ?? 'Status '.$b['key'], 'count' => $b['doc_count']])->values(),
            'by_category'        => collect($aggs['by_category']['buckets'] ?? [])->map(fn($catBucket) => ['name' => $catBucket['key'], 'data' => $allMonths->map(fn($m) => (int) (collect($catBucket['monthly']['buckets'] ?? [])->pluck('doc_count', 'key')[$m] ?? 0))->values()->toArray()])->values(),
            'by_category_simple' => collect($aggs['by_category']['buckets'] ?? [])->map(fn($b) => ['label' => $b['key'], 'count' => $b['doc_count']])->values(),
            'by_building'        => collect($aggs['by_building']['buckets'] ?? [])->map(fn($b) => ['label' => $b['key'], 'count' => $b['doc_count']])->values(),
            'by_wo_type'         => collect($aggs['by_wo_type']['buckets'] ?? [])->map(fn($b) => ['label' => ucfirst($b['key']), 'count' => $b['doc_count']])->values(),
            'by_service'         => collect($aggs['by_service_type']['buckets'] ?? [])->map(fn($b) => ['label' => ucfirst($b['key']), 'count' => $b['doc_count']])->values(),
            'by_journey'         => collect($aggs['by_journey']['buckets'] ?? [])->map(fn($b) => ['label' => ucfirst(str_replace('_', ' ', $b['key'])), 'count' => $b['doc_count']])->values(),
            'by_priority'        => collect($aggs['by_priority']['buckets'] ?? [])->map(fn($b) => ['label' => $b['key'], 'count' => $b['doc_count']])->values(),
            'expense_type'       => collect($aggs['expense_by_type']['buckets'] ?? [])->map(fn($b) => ['label' => ucfirst($b['key']), 'total' => round($b['total_cost']['value'] ?? 0, 2)])->values(),
            'expense_cat'        => collect($aggs['expense_by_cat']['buckets'] ?? [])->map(fn($b) => ['label' => $b['key'], 'wo_count' => $b['doc_count'], 'total' => round($b['total_cost']['value'] ?? 0, 2)])->values(),
            'months'             => $allMonths,
        ];

        $userOptions = collect(); $contractOptions = collect();
        if ($config['show_filters']) {
            $fo = $this->woTotals->filterOptions($pid ? (int) $pid : null);
            $userOptions     = $fo['userOptions'];
            $contractOptions = $fo['contractOptions'];
        }

        return view('dashboards.builder-preview', compact('config', 'kpiValues', 'chartData', 'filters', 'userOptions', 'contractOptions'));
    }

    private function previewProperties(Request $request, Client $os): \Illuminate\View\View
    {
        $config  = session('dashboard_builder_config_properties', $this->defaultConfig('properties'));
        $filters = $request->only(['property_type', 'location_type', 'status', 'region_id', 'city_id']);
        $prefix  = config('opensearch.index_prefix', 'osool_');
        $pid     = session('selected_project_id');

        $must = [];
        foreach (array_filter($filters, fn($v) => $v !== null && $v !== '') as $field => $value) {
            $must[] = ['term' => [$field => is_numeric($value) ? (int) $value : $value]];
        }
        if ($pid) $must[] = ['term' => ['project_ids' => (int) $pid]];
        $query = $must ? ['bool' => ['must' => $must]] : ['match_all' => (object) []];

        $resp = $os->search([
            'index' => $prefix . 'properties',
            'body'  => [
                'track_total_hits' => true,
                'size'  => 0,
                'query' => $query,
                'aggs'  => [
                    'sum_buildings'  => ['sum' => ['field' => 'buildings_count']],
                    'sum_contracts'  => ['sum' => ['field' => 'contract_count']],
                    'sum_rent'       => ['sum' => ['field' => 'rent_count']],
                    'sum_lease'      => ['sum' => ['field' => 'lease_count']],
                    'sum_budget'     => ['sum' => ['field' => 'total_budget']],
                    'sum_auto_ren'   => ['sum' => ['field' => 'auto_renewal_count']],
                    'active'         => ['filter' => ['term' => ['is_active' => true]]],
                    'buildings_only' => ['filter' => ['term' => ['property_type' => 'building']]],
                    'complexes'      => ['filter' => ['term' => ['property_type' => 'complex']]],
                    'monthly'        => ['terms' => ['field' => 'created_year_month', 'size' => 60, 'order' => ['_key' => 'asc']]],
                    'by_type'        => ['terms' => ['field' => 'property_type', 'size' => 5]],
                    'by_region'      => ['terms' => ['field' => 'region_name', 'size' => 15]],
                    'by_city'        => ['terms' => ['field' => 'city_name', 'size' => 15]],
                    'by_status'      => ['terms' => ['field' => 'is_active', 'size' => 5]],
                    'top_props'      => ['terms' => ['field' => 'property_name.raw', 'size' => 15, 'order' => ['c' => 'desc']], 'aggs' => ['c' => ['sum' => ['field' => 'contract_count']]]],
                ],
            ],
        ]);

        $woResp     = $this->safeOsSearch($os, $prefix . 'work_orders',           ['track_total_hits' => true, 'size' => 0, 'query' => ['match_all' => (object) []], 'aggs' => ['total_cost' => ['sum' => ['field' => 'cost']], 'distinct_sps' => ['cardinality' => ['field' => 'service_provider_id']], 'distinct_mr' => ['cardinality' => ['field' => 'maintenance_request_id']]]]);
        $assetsResp = $this->safeOsSearch($os, $prefix . 'assets',                ['track_total_hits' => true, 'size' => 0, 'query' => ['match_all' => (object) []]]);
        $ccResp     = $this->safeOsSearch($os, $prefix . 'commercial_contracts',  ['size' => 0, 'query' => ['match_all' => (object) []], 'aggs' => ['active' => ['filter' => ['term' => ['is_active' => true]]], 'by_ejar' => ['terms' => ['field' => 'ejar_sync_status', 'size' => 10]]]]);

        $aggs   = $resp['aggregations'] ?? [];
        $ccAggs = $ccResp['aggregations'] ?? [];

        $kpiValues = [
            'total_properties'  => (int) ($resp['hits']['total']['value']          ?? 0),
            'total_buildings'   => (int) ($aggs['sum_buildings']['value']          ?? 0),
            'buildings_only'    => (int) ($aggs['buildings_only']['doc_count']     ?? 0),
            'complexes'         => (int) ($aggs['complexes']['doc_count']          ?? 0),
            'active_properties' => (int) ($aggs['active']['doc_count']             ?? 0),
            'total_contracts'   => (int) ($aggs['sum_contracts']['value']          ?? 0),
            'active_contracts'  => (int) ($ccAggs['active']['doc_count']           ?? 0),
            'rent_contracts'    => (int) ($aggs['sum_rent']['value']               ?? 0),
            'lease_contracts'   => (int) ($aggs['sum_lease']['value']              ?? 0),
            'auto_renewal'      => (int) ($aggs['sum_auto_ren']['value']           ?? 0),
            'total_budget'      => round($aggs['sum_budget']['value']              ?? 0, 2),
            'total_assets'      => (int) ($assetsResp['hits']['total']['value']    ?? 0),
            'total_wo'          => (int) ($woResp['hits']['total']['value']        ?? 0),
            'total_wo_cost'     => round($woResp['aggregations']['total_cost']['value'] ?? 0, 2),
        ];

        $chartData = [
            'monthly'   => collect($aggs['monthly']['buckets'] ?? [])->map(fn($b) => ['label' => $b['key'], 'count' => $b['doc_count']])->values(),
            'by_type'   => collect($aggs['by_type']['buckets'] ?? [])->map(fn($b) => ['label' => ucfirst($b['key']), 'count' => $b['doc_count']])->values(),
            'by_status' => collect($aggs['by_status']['buckets'] ?? [])->map(fn($b) => ['label' => (bool) $b['key'] ? 'Active' : 'Inactive', 'count' => $b['doc_count']])->values(),
            'by_region' => collect($aggs['by_region']['buckets'] ?? [])->map(fn($b) => ['label' => $b['key'], 'count' => $b['doc_count']])->values(),
            'by_city'   => collect($aggs['by_city']['buckets'] ?? [])->map(fn($b) => ['label' => $b['key'], 'count' => $b['doc_count']])->values(),
            'top_props' => collect($aggs['top_props']['buckets'] ?? [])->map(fn($b) => ['label' => $b['key'], 'count' => (int) ($b['c']['value'] ?? 0)])->values(),
            'by_ejar'   => collect($ccAggs['by_ejar']['buckets'] ?? [])->map(fn($b) => ['label' => $b['key'], 'count' => $b['doc_count']])->values(),
        ];

        $regions = DB::table('marts.dim_region')->where('is_deleted', false)->select('region_id', 'name')->orderBy('name')->get();
        $cities  = DB::table('marts.dim_city')->where('is_deleted', false)->select('city_id', 'name_en')->orderBy('name_en')->get();

        return view('dashboards.builder-preview-properties', compact('config', 'kpiValues', 'chartData', 'filters', 'regions', 'cities'));
    }

    private function previewBilling(Request $request, Client $os): \Illuminate\View\View
    {
        $config  = session('dashboard_builder_config_billing', $this->defaultConfig('billing'));
        $filters = array_filter($request->only(['contract_type', 'ejar_sync_status']), fn($v) => $v !== null && $v !== '');
        $prefix  = config('opensearch.index_prefix', 'osool_');
        $pid     = session('selected_project_id');

        $ccMust = [['term' => ['is_deleted' => false]]];
        if ($pid) $ccMust[] = ['term' => ['project_id' => (int) $pid]];
        foreach ($filters as $f => $v) $ccMust[] = ['term' => [$f => $v]];

        $cc = $os->search([
            'index' => $prefix . 'commercial_contracts',
            'body'  => [
                'track_total_hits' => true,
                'size'  => 0,
                'query' => ['bool' => ['must' => $ccMust]],
                'aggs'  => [
                    'total_value'   => ['sum'    => ['field' => 'amount']],
                    'sum_security'  => ['sum'    => ['field' => 'security_deposit_amount']],
                    'sum_late'      => ['sum'    => ['field' => 'late_fees_charge']],
                    'sum_brokerage' => ['sum'    => ['field' => 'brokerage_fee']],
                    'sum_retainer'  => ['sum'    => ['field' => 'retainer_fee']],
                    'sum_due'       => ['sum'    => ['field' => 'payment_due']],
                    'rent'          => ['filter' => ['term' => ['contract_type' => 'rent']]],
                    'lease'         => ['filter' => ['term' => ['contract_type' => 'lease']]],
                    'active'        => ['filter' => ['term' => ['is_active' => true]]],
                    'auto_renewal'  => ['filter' => ['term' => ['auto_renewal' => true]]],
                    'by_type'       => ['terms'  => ['field' => 'contract_type', 'size' => 5]],
                    'by_ejar'       => ['terms'  => ['field' => 'ejar_sync_status', 'size' => 10]],
                ],
            ],
        ]);

        $inMust = [];
        if ($pid) $inMust[] = ['term' => ['project_id' => (int) $pid]];
        if (isset($filters['contract_type'])) $inMust[] = ['term' => ['contract_type' => $filters['contract_type']]];
        $inQuery = $inMust ? ['bool' => ['must' => $inMust]] : ['match_all' => (object) []];

        $in = $os->search([
            'index' => $prefix . 'installments',
            'body'  => [
                'size'  => 0,
                'query' => $inQuery,
                'aggs'  => [
                    'paid'        => ['filter' => ['term' => ['is_paid' => true]],   'aggs' => ['sum' => ['sum' => ['field' => 'amount']]]],
                    'unpaid'      => ['filter' => ['term' => ['is_paid' => false]],  'aggs' => ['sum' => ['sum' => ['field' => 'amount']]]],
                    'overdue'     => ['filter' => ['term' => ['is_overdue' => true]],'aggs' => ['sum' => ['sum' => ['field' => 'amount']]]],
                    'monthly'     => ['terms'  => ['field' => 'due_year_month', 'size' => 60, 'order' => ['_key' => 'asc']],
                                      'aggs'   => [
                                          'paid'   => ['filter' => ['term' => ['is_paid' => true]],  'aggs' => ['sum' => ['sum' => ['field' => 'amount']]]],
                                          'unpaid' => ['filter' => ['term' => ['is_paid' => false]], 'aggs' => ['sum' => ['sum' => ['field' => 'amount']]]],
                                      ]],
                    'aging'       => ['terms' => ['field' => 'aging_bucket',  'size' => 10]],
                    'by_ptype'    => ['terms' => ['field' => 'payment_type',  'size' => 10]],
                    'top_tenants' => ['filter' => ['term' => ['is_paid' => false]],
                                      'aggs'   => ['tenants' => ['terms' => ['field' => 'tenant_name', 'size' => 10, 'order' => ['outstanding' => 'desc']],
                                                                  'aggs'  => ['outstanding' => ['sum' => ['field' => 'amount']]]]]],
                ],
            ],
        ]);

        $overdueRows  = $config['show_table'] ? collect($this->safeOsSearch($os, $prefix . 'installments', [
            'size'  => 15,
            'query' => ['bool' => ['must' => array_merge($inMust, [['term' => ['is_overdue' => true]]])]],
            'sort'  => [['days_overdue' => 'desc']],
        ])['hits']['hits'] ?? [])->pluck('_source') : collect();

        $upcomingRows = $config['show_table'] ? collect($this->safeOsSearch($os, $prefix . 'installments', [
            'size'  => 15,
            'query' => ['bool' => ['must' => array_merge($inMust, [['term' => ['is_paid' => false]], ['range' => ['payment_due_date' => ['gte' => 'now/d']]]])]],
            'sort'  => [['payment_due_date' => 'asc']],
        ])['hits']['hits'] ?? [])->pluck('_source') : collect();

        $ccAggs = $cc['aggregations'] ?? [];
        $inAggs = $in['aggregations'] ?? [];

        $kpiValues = [
            'total_contracts'   => (int)   ($cc['hits']['total']['value']            ?? 0),
            'total_value'       => round(   $ccAggs['total_value']['value']           ?? 0, 2),
            'rent'              => (int)   ($ccAggs['rent']['doc_count']              ?? 0),
            'lease'             => (int)   ($ccAggs['lease']['doc_count']             ?? 0),
            'active'            => (int)   ($ccAggs['active']['doc_count']            ?? 0),
            'auto_renewal'      => (int)   ($ccAggs['auto_renewal']['doc_count']      ?? 0),
            'security_deposits' => round(   $ccAggs['sum_security']['value']          ?? 0, 2),
            'late_fees'         => round(   $ccAggs['sum_late']['value']              ?? 0, 2),
            'brokerage_fees'    => round(   $ccAggs['sum_brokerage']['value']         ?? 0, 2),
            'retainer_fees'     => round(   $ccAggs['sum_retainer']['value']          ?? 0, 2),
            'collected'         => round(   $inAggs['paid']['sum']['value']           ?? 0, 2),
            'outstanding'       => round(   $inAggs['unpaid']['sum']['value']         ?? 0, 2),
            'overdue_amount'    => round(   $inAggs['overdue']['sum']['value']        ?? 0, 2),
            'payment_due'       => round(   $ccAggs['sum_due']['value']              ?? 0, 2),
        ];

        $chartData = [
            'monthly'     => collect($inAggs['monthly']['buckets'] ?? [])->map(fn($b) => ['label' => $b['key'], 'paid' => (float)($b['paid']['sum']['value'] ?? 0), 'unpaid' => (float)($b['unpaid']['sum']['value'] ?? 0)])->values(),
            'aging'       => collect($inAggs['aging']['buckets']   ?? [])->map(fn($b) => ['label' => $b['key'], 'count' => $b['doc_count']])->values(),
            'by_type'     => collect($ccAggs['by_type']['buckets'] ?? [])->map(fn($b) => ['label' => ucfirst($b['key']), 'count' => $b['doc_count']])->values(),
            'by_ejar'     => collect($ccAggs['by_ejar']['buckets'] ?? [])->map(fn($b) => ['label' => $b['key'], 'count' => $b['doc_count']])->values(),
            'by_ptype'    => collect($inAggs['by_ptype']['buckets'] ?? [])->map(fn($b) => ['label' => $b['key'], 'count' => $b['doc_count']])->values(),
            'top_tenants' => collect($inAggs['top_tenants']['tenants']['buckets'] ?? [])->map(fn($b) => ['label' => $b['key'], 'amount' => round($b['outstanding']['value'] ?? 0, 2)])->values(),
        ];

        return view('dashboards.builder-preview-billing', compact('config', 'kpiValues', 'chartData', 'filters', 'overdueRows', 'upcomingRows'));
    }

    private function previewUsers(Request $request, Client $os): \Illuminate\View\View
    {
        $config  = session('dashboard_builder_config_users', $this->defaultConfig('users'));
        $filters = array_filter($request->only(['user_type', 'is_active', 'is_deleted']), fn($v) => $v !== null && $v !== '');
        $index   = config('opensearch.index_prefix', 'osool_') . 'users';
        $pid     = session('selected_project_id');

        $must = [];
        foreach ($filters as $f => $v) {
            if (in_array($v, ['true', 'false'], true)) {
                $must[] = ['term' => [$f => $v === 'true']];
            } else {
                $must[] = ['term' => [$f => $v]];
            }
        }
        if ($pid) $must[] = ['term' => ['project_ids' => (int) $pid]];
        $query = $must ? ['bool' => ['must' => $must]] : ['match_all' => (object) []];

        $resp = $os->search([
            'index' => $index,
            'body'  => [
                'size'  => $config['show_table'] ? 50 : 0,
                'query' => $query,
                'sort'  => [['created_at' => 'desc']],
                'aggs'  => [
                    'active'    => ['filter' => ['term' => ['is_active'  => true]]],
                    'inactive'  => ['filter' => ['term' => ['is_active'  => false]]],
                    'deleted'   => ['filter' => ['term' => ['is_deleted' => true]]],
                    'by_type'   => ['terms'  => ['field' => 'user_type',           'size' => 15]],
                    'by_city'   => ['terms'  => ['field' => 'city_name',           'size' => 15]],
                    'by_project'=> ['terms'  => ['field' => 'project_ids',         'size' => 20]],
                    'monthly'   => ['terms'  => ['field' => 'created_year_month',  'size' => 60, 'order' => ['_key' => 'asc']]],
                ],
            ],
        ]);

        $aggs = $resp['aggregations'] ?? [];
        $bucket = fn(string $k) => collect($aggs[$k]['buckets'] ?? [])->map(fn($b) => ['label' => (string) $b['key'], 'count' => $b['doc_count']])->values();

        $kpiValues = [
            'total_users' => (int) ($resp['hits']['total']['value']  ?? 0),
            'active'      => (int) ($aggs['active']['doc_count']     ?? 0),
            'inactive'    => (int) ($aggs['inactive']['doc_count']   ?? 0),
            'deleted'     => (int) ($aggs['deleted']['doc_count']    ?? 0),
        ];

        $chartData = [
            'monthly'    => $bucket('monthly'),
            'by_type'    => $bucket('by_type'),
            'by_city'    => $bucket('by_city'),
            'by_project' => $bucket('by_project'),
        ];

        $rows = $config['show_table']
            ? collect($resp['hits']['hits'] ?? [])->pluck('_source')
            : collect();

        return view('dashboards.builder-preview-users', compact('config', 'kpiValues', 'chartData', 'filters', 'rows'));
    }

    private function previewAssets(Request $request, Client $os): \Illuminate\View\View
    {
        $config  = session('dashboard_builder_config_assets', $this->defaultConfig('assets'));
        $filters = array_filter($request->only(['asset_category_id', 'asset_status_id', 'building_id', 'has_status', 'under_warranty']), fn($v) => $v !== null && $v !== '');
        $index   = config('opensearch.index_prefix', 'osool_') . 'assets';
        $pid     = session('selected_project_id');

        $must = [];
        foreach ($filters as $f => $v) {
            $must[] = ['term' => [$f => in_array($v, ['true','false'], true) ? $v === 'true' : (is_numeric($v) ? (int)$v : $v)]];
        }
        if ($pid) $must[] = ['term' => ['project_ids' => (int) $pid]];
        $query = $must ? ['bool' => ['must' => $must]] : ['match_all' => (object) []];

        $resp = $os->search([
            'index' => $index,
            'body'  => [
                'track_total_hits' => true,
                'size'  => $config['show_table'] ? 50 : 0,
                'query' => $query,
                'sort'  => [['created_at' => 'desc']],
                'aggs'  => [
                    'sum_value'      => ['sum'         => ['field' => 'purchase_amount']],
                    'with_status'    => ['filter'      => ['term' => ['has_status'      => true]]],
                    'without_status' => ['filter'      => ['term' => ['has_status'      => false]]],
                    'under_warranty' => ['filter'      => ['term' => ['under_warranty'  => true]]],
                    'distinct_cat'   => ['cardinality' => ['field' => 'asset_category_id']],
                    'distinct_bldg'  => ['cardinality' => ['field' => 'building_id']],
                    'monthly'        => ['terms' => ['field' => 'created_year_month', 'size' => 60, 'order' => ['_key' => 'asc']]],
                    'by_category'    => ['terms' => ['field' => 'asset_category',     'size' => 15]],
                    'by_status'      => ['terms' => ['field' => 'asset_status_name',  'size' => 15]],
                    'by_building'    => ['terms' => ['field' => 'building_name',       'size' => 15]],
                    'by_name'        => ['terms' => ['field' => 'asset_name',          'size' => 15]],
                    'by_manufac'     => ['terms' => ['field' => 'manufacturer_name',   'size' => 10]],
                ],
            ],
        ]);

        $aggs   = $resp['aggregations'] ?? [];
        $bucket = fn(string $k) => collect($aggs[$k]['buckets'] ?? [])->map(fn($b) => ['label' => (string)$b['key'], 'count' => $b['doc_count']])->values();

        $kpiValues = [
            'total_assets'   => (int)  ($resp['hits']['total']['value']          ?? 0),
            'categories'     => (int)  ($aggs['distinct_cat']['value']           ?? 0),
            'buildings'      => (int)  ($aggs['distinct_bldg']['value']          ?? 0),
            'with_status'    => (int)  ($aggs['with_status']['doc_count']        ?? 0),
            'without_status' => (int)  ($aggs['without_status']['doc_count']     ?? 0),
            'under_warranty' => (int)  ($aggs['under_warranty']['doc_count']     ?? 0),
            'total_value'    => round(  $aggs['sum_value']['value']              ?? 0, 2),
        ];

        $chartData = [
            'monthly'     => $bucket('monthly'),
            'by_category' => $bucket('by_category'),
            'by_status'   => $bucket('by_status'),
            'by_building' => $bucket('by_building'),
            'by_name'     => $bucket('by_name'),
            'by_manufac'  => $bucket('by_manufac'),
        ];

        $rows       = $config['show_table'] ? collect($resp['hits']['hits'] ?? [])->pluck('_source') : collect();
        $categories = DB::table('marts.dim_asset_category')->where('is_deleted', false)->select('asset_category_id', 'asset_category')->orderBy('asset_category')->get();
        $statuses   = DB::table('marts.dim_asset_status')->where('is_deleted', false)->select('asset_status_id', 'name')->orderBy('name')->get();
        $buildings  = DB::table('marts.dim_property_building')->where('is_deleted', false)->select('building_id', 'building_name')->orderBy('building_name')->get();

        return view('dashboards.builder-preview-assets', compact('config', 'kpiValues', 'chartData', 'filters', 'rows', 'categories', 'statuses', 'buildings'));
    }

    private function previewContracts(Request $request, Client $os): \Illuminate\View\View
    {
        $config  = session('dashboard_builder_config_contracts', $this->defaultConfig('contracts'));
        $filters = array_filter($request->only(['service_provider_id', 'contract_type_id', 'status']), fn($v) => $v !== null && $v !== '');
        $index   = config('opensearch.index_prefix', 'osool_') . 'contracts';
        $pid     = session('selected_project_id');

        $must = [];
        foreach ($filters as $f => $v) {
            $must[] = ['term' => [$f => is_numeric($v) ? (int)$v : $v]];
        }
        if ($pid) $must[] = ['term' => ['project_ids' => (int) $pid]];
        $query = $must ? ['bool' => ['must' => $must]] : ['match_all' => (object) []];

        $resp = $os->search([
            'index' => $index,
            'body'  => [
                'track_total_hits' => true,
                'size'  => $config['show_table'] ? 30 : 0,
                'query' => $query,
                'sort'  => [['contract_value' => 'desc']],
                'aggs'  => [
                    'sum_value'     => ['sum'    => ['field' => 'contract_value']],
                    'avg_value'     => ['avg'    => ['field' => 'contract_value']],
                    'sum_scheduled' => ['sum'    => ['field' => 'scheduled_total']],
                    'sum_paid'      => ['sum'    => ['field' => 'paid_total']],
                    'sum_pending'   => ['sum'    => ['field' => 'pending_total']],
                    'sum_overdue'   => ['sum'    => ['field' => 'overdue_total']],
                    'sum_wo_cost'   => ['sum'    => ['field' => 'wo_total_cost']],
                    'sum_closed_wo' => ['sum'    => ['field' => 'closed_wo_count']],
                    'active'        => ['filter' => ['term' => ['is_active'      => true]]],
                    'expired'       => ['filter' => ['term' => ['is_expired'     => true]]],
                    'subcontracts'  => ['filter' => ['term' => ['is_subcontract' => true]]],
                    'by_type'       => ['terms'  => ['field' => 'contract_type_name',     'size' => 10], 'aggs' => ['v' => ['sum' => ['field' => 'contract_value']]]],
                    'top_sp'        => ['terms'  => ['field' => 'service_provider_name',  'size' => 10, 'order' => ['v' => 'desc']], 'aggs' => ['v' => ['sum' => ['field' => 'contract_value']]]],
                    'top_overdue'   => ['terms'  => ['field' => 'contract_number',        'size' => 10, 'order' => ['v' => 'desc']], 'aggs' => ['v' => ['sum' => ['field' => 'overdue_total']]]],
                ],
            ],
        ]);

        $aggs = $resp['aggregations'] ?? [];

        $kpiValues = [
            'total_contracts' => (int)  ($resp['hits']['total']['value']      ?? 0),
            'total_value'     => round(  $aggs['sum_value']['value']          ?? 0, 2),
            'avg_value'       => round(  $aggs['avg_value']['value']          ?? 0, 2),
            'active'          => (int)  ($aggs['active']['doc_count']         ?? 0),
            'expired'         => (int)  ($aggs['expired']['doc_count']        ?? 0),
            'subcontracts'    => (int)  ($aggs['subcontracts']['doc_count']   ?? 0),
            'scheduled_total' => round(  $aggs['sum_scheduled']['value']      ?? 0, 2),
            'paid_total'      => round(  $aggs['sum_paid']['value']           ?? 0, 2),
            'pending_total'   => round(  $aggs['sum_pending']['value']        ?? 0, 2),
            'overdue_total'   => round(  $aggs['sum_overdue']['value']        ?? 0, 2),
            'closed_wo'       => (int)  ($aggs['sum_closed_wo']['value']      ?? 0),
            'wo_cost'         => round(  $aggs['sum_wo_cost']['value']        ?? 0, 2),
        ];

        $chartData = [
            'by_type'     => collect($aggs['by_type']['buckets']     ?? [])->map(fn($b) => ['label' => $b['key'], 'value' => round($b['v']['value'] ?? 0, 2)])->values(),
            'top_sp'      => collect($aggs['top_sp']['buckets']      ?? [])->map(fn($b) => ['label' => $b['key'], 'value' => round($b['v']['value'] ?? 0, 2)])->values(),
            'top_overdue' => collect($aggs['top_overdue']['buckets'] ?? [])->map(fn($b) => ['label' => $b['key'], 'value' => round($b['v']['value'] ?? 0, 2)])->values(),
        ];

        $rows = $config['show_table'] ? collect($resp['hits']['hits'] ?? [])->pluck('_source') : collect();

        return view('dashboards.builder-preview-contracts', compact('config', 'kpiValues', 'chartData', 'filters', 'rows'));
    }

    private function previewOverview(Request $request, Client $os): \Illuminate\View\View
    {
        $config  = session('dashboard_builder_config_overview', $this->defaultConfig('overview'));
        $filters = $request->only(['project_id']);
        $prefix  = config('opensearch.index_prefix', 'osool_');
        $pid     = !empty($filters['project_id']) ? (int) $filters['project_id'] : session('selected_project_id');

        try { DB::statement('REFRESH MATERIALIZED VIEW reports.mv_overview_totals'); } catch (\Throwable) {}
        $totals = DB::table('reports.mv_overview_totals')->first();

        $woMust = [['bool' => ['must_not' => [['term' => ['status_code' => 5]]]]]];
        if ($pid) $woMust[] = ['term' => ['project_ids' => $pid]];
        $woResp = $this->safeOsSearch($os, $prefix . 'work_orders', [
            'track_total_hits' => true,
            'size' => 0,
            'query' => ['bool' => ['must' => $woMust]],
            'aggs' => [
                'preventive'  => ['filter' => ['term'  => ['work_order_type' => 'preventive']]],
                'reactive'    => ['filter' => ['term'  => ['work_order_type' => 'reactive']]],
                'open'        => ['filter' => ['term'  => ['status_code' => 1]]],
                'in_progress' => ['filter' => ['term'  => ['status_code' => 2]]],
                'closed'      => ['filter' => ['term'  => ['status_code' => 4]]],
                'total_cost'  => ['sum'    => ['field' => 'cost']],
                'by_status'   => ['terms'  => ['field' => 'status_code',     'size' => 10]],
                'by_type'     => ['terms'  => ['field' => 'work_order_type', 'size' => 5]],
            ],
        ]);

        $propQuery = $pid ? ['bool' => ['must' => [['term' => ['project_ids' => $pid]]]]] : ['match_all' => (object) []];
        $propResp  = $this->safeOsSearch($os, $prefix . 'properties', [
            'track_total_hits' => true,
            'size' => 0,
            'query' => $propQuery,
            'aggs' => [
                'active'    => ['filter' => ['term'  => ['is_active' => true]]],
                'by_region' => ['terms'  => ['field' => 'region_name', 'size' => 10]],
            ],
        ]);

        $assetQuery = $pid ? ['bool' => ['must' => [['term' => ['project_ids' => $pid]]]]] : ['match_all' => (object) []];
        $assetResp  = $this->safeOsSearch($os, $prefix . 'assets', [
            'track_total_hits' => true,
            'size' => 0,
            'query' => $assetQuery,
            'aggs' => [
                'under_warranty' => ['filter' => ['term'  => ['under_warranty' => true]]],
                'total_value'    => ['sum'    => ['field' => 'purchase_amount']],
                'by_category'    => ['terms'  => ['field' => 'asset_category', 'size' => 10]],
            ],
        ]);

        $userQuery = $pid ? ['bool' => ['must' => [['term' => ['project_ids' => $pid]]]]] : ['match_all' => (object) []];
        $userResp  = $this->safeOsSearch($os, $prefix . 'users', [
            'track_total_hits' => true,
            'size' => 0,
            'query' => $userQuery,
            'aggs' => [
                'active'  => ['filter' => ['term'  => ['is_active' => true]]],
                'by_type' => ['terms'  => ['field' => 'user_type', 'size' => 15]],
            ],
        ]);

        $ccMust = [['term' => ['is_deleted' => false]]];
        if ($pid) $ccMust[] = ['term' => ['project_id' => $pid]];
        $ccResp = $this->safeOsSearch($os, $prefix . 'commercial_contracts', [
            'size' => 0,
            'query' => ['bool' => ['must' => $ccMust]],
            'aggs' => ['total_amount' => ['sum' => ['field' => 'amount']]],
        ]);

        $inQuery = $pid ? ['bool' => ['must' => [['term' => ['project_id' => $pid]]]]] : ['match_all' => (object) []];
        $inResp  = $this->safeOsSearch($os, $prefix . 'installments', [
            'size' => 0,
            'query' => $inQuery,
            'aggs' => [
                'collected'   => ['filter' => ['term' => ['is_paid'    => true]],  'aggs' => ['sum' => ['sum' => ['field' => 'amount']]]],
                'outstanding' => ['filter' => ['term' => ['is_paid'    => false]], 'aggs' => ['sum' => ['sum' => ['field' => 'amount']]]],
                'overdue'     => ['filter' => ['term' => ['is_overdue' => true]],  'aggs' => ['sum' => ['sum' => ['field' => 'amount']]]],
            ],
        ]);

        $conQuery = $pid ? ['bool' => ['must' => [['term' => ['project_ids' => $pid]]]]] : ['match_all' => (object) []];
        $conResp  = $this->safeOsSearch($os, $prefix . 'contracts', [
            'track_total_hits' => true,
            'size' => 0,
            'query' => $conQuery,
            'aggs' => [
                'sum_value'   => ['sum'   => ['field' => 'contract_value']],
                'sum_overdue' => ['sum'   => ['field' => 'overdue_total']],
                'by_type'     => ['terms' => ['field' => 'contract_type_name', 'size' => 10]],
            ],
        ]);

        $woAggs    = $woResp['aggregations']    ?? [];
        $propAggs  = $propResp['aggregations']  ?? [];
        $assetAggs = $assetResp['aggregations'] ?? [];
        $userAggs  = $userResp['aggregations']  ?? [];
        $inAggs    = $inResp['aggregations']    ?? [];
        $conAggs   = $conResp['aggregations']   ?? [];

        $woStatusLabels = [1 => 'Open', 2 => 'In Progress', 3 => 'On Hold', 4 => 'Closed', 6 => 'Re-open', 7 => 'Warranty'];

        $kpiValues = [
            'total_projects'      => (int)  ($totals->total_projects              ?? 0),
            'active_projects'     => (int)  ($totals->active_projects             ?? 0),
            'service_providers'   => (int)  ($totals->total_service_providers     ?? 0),
            'subscriptions'       => (int)  ($totals->total_subscriptions         ?? 0),
            'total_wo'            => (int)  ($woResp['hits']['total']['value']     ?? 0),
            'open_wo'             => (int)  ($woAggs['open']['doc_count']          ?? 0),
            'in_progress_wo'      => (int)  ($woAggs['in_progress']['doc_count']   ?? 0),
            'closed_wo'           => (int)  ($woAggs['closed']['doc_count']        ?? 0),
            'preventive_wo'       => (int)  ($woAggs['preventive']['doc_count']    ?? 0),
            'reactive_wo'         => (int)  ($woAggs['reactive']['doc_count']      ?? 0),
            'wo_cost'             => round(  $woAggs['total_cost']['value']        ?? 0, 2),
            'total_props'         => (int)  ($propResp['hits']['total']['value']   ?? 0),
            'active_props'        => (int)  ($propAggs['active']['doc_count']      ?? 0),
            'total_assets'        => (int)  ($assetResp['hits']['total']['value']  ?? 0),
            'under_warranty'      => (int)  ($assetAggs['under_warranty']['doc_count'] ?? 0),
            'asset_value'         => round(  $assetAggs['total_value']['value']    ?? 0, 2),
            'total_users'         => (int)  ($userResp['hits']['total']['value']   ?? 0),
            'active_users'        => (int)  ($userAggs['active']['doc_count']      ?? 0),
            'billing_collected'   => round(  $inAggs['collected']['sum']['value']  ?? 0, 2),
            'billing_outstanding' => round(  $inAggs['outstanding']['sum']['value']?? 0, 2),
            'billing_overdue'     => round(  $inAggs['overdue']['sum']['value']    ?? 0, 2),
            'total_contracts'     => (int)  ($conResp['hits']['total']['value']    ?? 0),
            'contract_value'      => round(  $conAggs['sum_value']['value']        ?? 0, 2),
            'contract_overdue'    => round(  $conAggs['sum_overdue']['value']      ?? 0, 2),
        ];

        $chartData = [
            'wo_by_status'      => collect($woAggs['by_status']['buckets']    ?? [])->map(fn($b) => ['label' => $woStatusLabels[$b['key']] ?? 'Status '.$b['key'], 'count' => $b['doc_count']])->values(),
            'wo_by_type'        => collect($woAggs['by_type']['buckets']      ?? [])->map(fn($b) => ['label' => ucfirst($b['key']), 'count' => $b['doc_count']])->values(),
            'prop_by_region'    => collect($propAggs['by_region']['buckets']  ?? [])->map(fn($b) => ['label' => $b['key'], 'count' => $b['doc_count']])->values(),
            'asset_by_category' => collect($assetAggs['by_category']['buckets'] ?? [])->map(fn($b) => ['label' => $b['key'], 'count' => $b['doc_count']])->values(),
            'user_by_type'      => collect($userAggs['by_type']['buckets']    ?? [])->map(fn($b) => ['label' => $b['key'], 'count' => $b['doc_count']])->values(),
            'billing_summary'   => collect([
                ['label' => 'Collected',   'value' => round($inAggs['collected']['sum']['value']   ?? 0, 2)],
                ['label' => 'Outstanding', 'value' => round($inAggs['outstanding']['sum']['value'] ?? 0, 2)],
                ['label' => 'Overdue',     'value' => round($inAggs['overdue']['sum']['value']     ?? 0, 2)],
            ]),
            'contract_by_type'  => collect($conAggs['by_type']['buckets']    ?? [])->map(fn($b) => ['label' => $b['key'], 'count' => $b['doc_count']])->values(),
        ];

        $projects = DB::table('marts.dim_project')->where('is_deleted', false)->select('project_id', 'project_name')->orderBy('project_name')->get();

        return view('dashboards.builder-preview-overview', compact('config', 'kpiValues', 'chartData', 'filters', 'projects'));
    }

    private function safeOsSearch(Client $os, string $index, array $body): array
    {
        try {
            return $os->search(['index' => $index, 'body' => $body]);
        } catch (\Throwable) {
            return ['hits' => ['total' => ['value' => 0], 'hits' => []], 'aggregations' => []];
        }
    }

    private function optionsForType(string $type): array
    {
        [$kpis, $charts] = match($type) {
            'properties' => [self::PROPERTIES_KPI_OPTIONS, self::PROPERTIES_CHART_OPTIONS],
            'billing'    => [self::BILLING_KPI_OPTIONS,    self::BILLING_CHART_OPTIONS],
            'users'      => [self::USERS_KPI_OPTIONS,      self::USERS_CHART_OPTIONS],
            'assets'     => [self::ASSETS_KPI_OPTIONS,     self::ASSETS_CHART_OPTIONS],
            'contracts'  => [self::CONTRACTS_KPI_OPTIONS,  self::CONTRACTS_CHART_OPTIONS],
            'overview'   => [self::OVERVIEW_KPI_OPTIONS,   self::OVERVIEW_CHART_OPTIONS],
            default      => [self::KPI_OPTIONS,             self::CHART_OPTIONS],
        };

        $pfx = match($type) {
            'properties' => 'prop', 'billing' => 'bill', 'users' => 'users',
            'assets' => 'assets', 'contracts' => 'con', 'overview' => 'ov',
            default => 'wo',
        };

        foreach ($kpis as $key => &$opt) {
            $k = "builder.kpi_{$pfx}_{$key}";
            if (($t = __($k)) !== $k) $opt['label'] = $t;
        }
        unset($opt);

        foreach ($charts as $key => &$opt) {
            $k = "builder.chart_opt_{$pfx}_{$key}";
            if (($t = __($k)) !== $k) $opt['label'] = $t;
        }
        unset($opt);

        return [$kpis, $charts];
    }

    private function defaultConfig(string $type): array
    {
        [, $chartOptions] = $this->optionsForType($type);
        $charts = [];
        foreach ($chartOptions as $key => $opt) {
            $charts[$key] = ['enabled' => false, 'type' => $opt['types'][0]];
        }
        if ($type === 'properties') {
            return ['name' => 'My Properties Dashboard', 'kpis' => ['total_properties', 'total_buildings', 'active_properties', 'total_contracts'], 'kpi_cols' => 4, 'charts' => $charts, 'show_filters' => true, 'show_map' => false, 'show_table' => false];
        }
        if ($type === 'billing') {
            return ['name' => 'My Billing Dashboard', 'kpis' => ['total_contracts', 'collected', 'outstanding', 'overdue_amount'], 'kpi_cols' => 4, 'charts' => $charts, 'show_filters' => true, 'show_map' => false, 'show_table' => true];
        }
        if ($type === 'users') {
            return ['name' => 'My Users Dashboard', 'kpis' => ['total_users', 'active', 'inactive', 'deleted'], 'kpi_cols' => 4, 'charts' => $charts, 'show_filters' => true, 'show_map' => false, 'show_table' => true];
        }
        if ($type === 'assets') {
            return ['name' => 'My Assets Dashboard', 'kpis' => ['total_assets', 'categories', 'under_warranty', 'total_value'], 'kpi_cols' => 4, 'charts' => $charts, 'show_filters' => true, 'show_map' => false, 'show_table' => true];
        }
        if ($type === 'contracts') {
            return ['name' => 'My Contracts Dashboard', 'kpis' => ['total_contracts', 'total_value', 'paid_total', 'overdue_total'], 'kpi_cols' => 4, 'charts' => $charts, 'show_filters' => true, 'show_map' => false, 'show_table' => true];
        }
        if ($type === 'overview') {
            return ['name' => 'My Overview Dashboard', 'kpis' => ['total_wo', 'total_props', 'total_assets', 'total_users', 'billing_collected', 'billing_overdue', 'total_contracts', 'contract_overdue'], 'kpi_cols' => 4, 'charts' => $charts, 'show_filters' => true, 'show_map' => false, 'show_table' => false];
        }
        return ['name' => 'My Work Orders Dashboard', 'kpis' => ['total_workorders', 'preventive', 'reactive', 'total_cost'], 'kpi_cols' => 4, 'charts' => $charts, 'show_filters' => true, 'show_map' => false, 'show_table' => false];
    }
}
