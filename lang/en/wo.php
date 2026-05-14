<?php

return [
    // Page header
    'page_title'   => 'Work Orders Dashboard',
    'heading'      => 'Work Orders',
    'subtitle'     => 'Operational performance across all projects',

    // Common controls
    'reset'        => 'Reset filters',
    'apply'        => 'Apply',
    'any'          => '— any —',

    // Filter labels (snake_case keys from blade)
    'f_service_type'      => 'Service type',
    'f_work_order_type'   => 'Work order type',
    'f_workorder_journey' => 'Journey stage',
    'f_status_code'       => 'Status',
    'f_priority_id'       => 'Priority',
    'f_asset_category_id' => 'Asset category',

    // KPI labels (mirror controller keys)
    'kpi' => [
        'Total'          => 'Total',
        'Open'           => 'Open',
        'In Progress'    => 'In progress',
        'On Hold'        => 'On hold',
        'Closed'         => 'Closed',
        'Preventive'     => 'Preventive',
        'Reactive'       => 'Reactive',
        'Hard Service'   => 'Hard service',
        'Soft Service'   => 'Soft service',
        'Total Cost'     => 'Total cost',
    ],

    // Chart titles
    'ch_monthly'   => '📈 Monthly trend',
    'ch_service'   => '🛠 By service type',
    'ch_wo_type'   => '⚙️ By WO type',
    'ch_journey'   => '🚦 By journey stage',
    'ch_status'    => '📊 By status',
    'ch_priority'  => '🔥 By priority',
    'ch_category'  => '🏷 Top asset categories',
    'ch_building'  => '🏢 Top buildings',

    // Table
    'tbl_title'    => 'Latest 50 work orders',
    'col_wo'       => 'WO #',
    'col_created'  => 'Created',
    'col_service'  => 'Service',
    'col_type'     => 'Type',
    'col_category' => 'Category',
    'col_priority' => 'Priority',
    'col_journey'  => 'Journey',
    'col_status'   => 'Status',
    'col_cost'     => 'Cost',
    'empty'        => 'No matching work orders.',
];
