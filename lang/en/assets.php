<?php

return [
    'page_title' => 'Assets Dashboard',
    'heading'    => 'Assets',
    'subtitle'   => 'Inventory, warranty & maintenance cost rollup',

    'reset'      => 'Reset filters',
    'apply'      => 'Apply',
    'any'        => '— any —',

    // Filter labels
    'f_asset_category_id' => 'Category',
    'f_asset_status_id'   => 'Status',
    'f_building_id'       => 'Building',
    'f_has_status'        => 'Has status',
    'f_under_warranty'    => 'Under warranty',
    'opt_yes' => 'Yes',
    'opt_no'  => 'No',

    // KPI labels
    'kpi' => [
        'Total Assets'      => 'Total assets',
        'Avg Availability'  => 'Avg availability',
        'Active Assets'     => 'Active assets',
        'Under Warranty'    => 'Under warranty',
        'Categories'        => 'Categories',
        'Total Value'       => 'Total value',
        'Avg Value'         => 'Average value',
        'With Status'       => 'With status',
        'Without Status'    => 'Without status',
        'Total Buildings'   => 'Total buildings',
        'Manufacturers'     => 'Manufacturers',
    ],

    // Chart titles
    'ch_monthly'   => '📈 Assets added per month',
    'ch_category'  => '🏷 By category',
    'ch_status'    => '⚡ By status',
    'ch_building'  => '🏢 By building',
    'ch_name'      => '🔖 By asset name',
    'ch_manufac'   => '🏭 Top manufacturers',
    'ch_availability' => '🟢 Uptime vs Downtime by Asset Category',
    'js_uptime'    => 'Uptime %',
    'js_downtime'  => 'Downtime %',
    'js_assets'    => 'Assets',

    // Table
    'tbl_title'       => 'Latest 50 assets',
    'col_tag'         => 'Tag',
    'col_name'        => 'Name',
    'col_category'    => 'Category',
    'col_status'      => 'Status',
    'col_building'    => 'Building',
    'col_manufacturer'=> 'Manufacturer',
    'col_warranty'    => 'Warranty',
    'col_value'       => 'Value',
    'col_created'     => 'Created',
    'st_none'         => 'None',
    'st_warranty_active'  => 'Active',
    'st_warranty_expired' => 'Expired',
    'empty'           => 'No matching assets.',

    // Assets availability table
    'av_title'        => 'Assets availability',
    'av_export'       => 'Export to Excel',
    'col_av_name'      => 'Asset Name',
    'col_av_id'        => 'Asset ID',
    'col_av_status'    => 'Asset Current Status',
    'col_av_category'  => 'Asset Category (Service Type)',
    'col_av_property'  => 'Property',
    'col_av_unit'      => 'Unit',
    'col_av_uptime'    => 'Asset Total Uptime',
    'col_av_downtime'  => 'Asset Total Downtime',
    'col_av_last_down' => 'Last Downtime Timestamp',
];
