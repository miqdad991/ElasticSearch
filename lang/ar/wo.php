<?php

return [
    // Page header
    'page_title'   => 'لوحة أوامر العمل',
    'heading'      => 'أوامر العمل',
    'subtitle'     => 'الأداء التشغيلي عبر جميع المشاريع',

    // Common controls
    'reset'        => 'إعادة ضبط الفلاتر',
    'apply'        => 'تطبيق',
    'any'          => '— الكل —',

    // Filter labels
    'f_service_type'      => 'نوع الخدمة',
    'f_work_order_type'   => 'نوع أمر العمل',
    'f_workorder_journey' => 'مرحلة الإنجاز',
    'f_status_code'       => 'الحالة',
    'f_priority_id'       => 'الأولوية',
    'f_asset_category_id' => 'فئة الأصل',
    'f_from'              => 'من تاريخ',
    'f_to'                => 'إلى تاريخ',

    // KPI labels
    'kpi' => [
        'Total'          => 'الإجمالي',
        'Open'           => 'مفتوحة',
        'In Progress'    => 'قيد التنفيذ',
        'On Hold'        => 'معلقة',
        'Closed'         => 'مغلقة',
        'Preventive'     => 'وقائية',
        'Reactive'       => 'تفاعلية',
        'Hard Service'   => 'خدمات صلبة',
        'Soft Service'   => 'خدمات ناعمة',
        'Total Cost'     => 'إجمالي التكلفة',
    ],

    // Chart titles
    'ch_monthly'   => '📈 الاتجاه الشهري',
    'ch_service'   => '🛠 حسب نوع الخدمة',
    'ch_wo_type'   => '⚙️ حسب نوع أمر العمل',
    'ch_journey'   => '🚦 حسب مرحلة الإنجاز',
    'ch_status'    => '📊 حسب الحالة',
    'ch_priority'  => '🔥 حسب الأولوية',
    'ch_category'  => '🏷 أعلى فئات الأصول',
    'ch_building'  => '🏢 أعلى المباني حسب نوع أمر العمل',
    'ch_sp'        => '🛠 أوامر العمل والتكلفة حسب مزود الخدمة',
    'sp_count'     => 'عدد أوامر العمل',
    'sp_cost'      => 'إجمالي التكلفة',
    'ch_cost_type'    => '💰 التكلفة حسب نوع أمر العمل',
    'ch_cost_service' => '💰 التكلفة حسب الخدمة',
    'ch_cost_city'    => '💰 التكلفة حسب المدينة',
    'ch_open_closed'  => '📈 أوامر العمل المفتوحة مقابل المغلقة لكل فترة',
    'oc_open'         => 'مفتوحة',
    'oc_closed'       => 'مغلقة',

    // Table
    'tbl_title'    => 'أحدث 50 أمر عمل',
    'col_wo'       => 'رقم الأمر',
    'col_created'  => 'تاريخ الإنشاء',
    'col_service'  => 'الخدمة',
    'col_type'     => 'النوع',
    'col_category' => 'الفئة',
    'col_priority' => 'الأولوية',
    'col_journey'  => 'المرحلة',
    'col_status'   => 'الحالة',
    'col_cost'     => 'التكلفة',
    'empty'        => 'لا توجد أوامر عمل مطابقة.',
];
