@extends('layouts.app')
@section('title', $config['name'])

@section('styles')
    .page-bg { background: linear-gradient(180deg,#f1f5f9 0%,#e2e8f0 100%); padding: 1.25rem; border-radius: 12px; }
    .card-soft { background:#fff; border-radius:14px; box-shadow: 0 1px 2px rgba(15,23,42,.04), 0 8px 24px -12px rgba(15,23,42,.08); padding:1rem; }
    .gradient-title { background:linear-gradient(90deg,#6366f1,#d946ef,#f43f5e); -webkit-background-clip:text; background-clip:text; color:transparent; font-weight:700; }

    /* KPI grid */
    .kpi-grid { display:grid; gap:.75rem; margin-bottom:1rem; }
    .kpi { position:relative; overflow:hidden; padding-left:1.25rem; }
    .kpi::before { content:""; position:absolute; left:0; top:0; bottom:0; width:4px; border-radius:4px 0 0 4px; }
    .kpi-label { font-size:11px; text-transform:uppercase; letter-spacing:.06em; color:#64748b; font-weight:600; }
    .kpi-value { font-size:1.5rem; font-weight:700; color:#0f172a; margin-top:.25rem; }

    /* Charts grid */
    .charts-grid { display:grid; grid-template-columns:1fr 1fr; gap:1rem; margin-bottom:1rem; }
    .chart-wide { grid-column:1 / -1; }
    @media (max-width:900px) {
        .charts-grid { grid-template-columns:1fr; }
        .kpi-grid { grid-template-columns:repeat(2,1fr) !important; }
    }

    /* Filters */
    .filter-bar { display:flex; flex-wrap:wrap; gap:.75rem; align-items:flex-end; }
    .filter-group { display:flex; flex-direction:column; gap:.2rem; min-width:150px; flex:1; }
    .filter-group label { font-size:11px; text-transform:uppercase; letter-spacing:.05em; color:#64748b; font-weight:600; }
    .filter-group select, .filter-group input { padding:.4rem .5rem; border:1.5px solid #e2e8f0; border-radius:8px; font-size:13px; background:#f8fafc; outline:none; }
    .filter-group select:focus, .filter-group input:focus { border-color:#6366f1; box-shadow:0 0 0 2px rgba(99,102,241,.1); }

    /* Expense table */
    .exp-table { width:100%; border-collapse:collapse; font-size:13px; }
    .exp-table th { text-align:left; padding:.55rem .65rem; color:#64748b; font-weight:600; font-size:11px; text-transform:uppercase; letter-spacing:.04em; border-bottom:1px solid #e2e8f0; background:#f8fafc; }
    .exp-table td { padding:.55rem .65rem; border-bottom:1px solid #f1f5f9; color:#334155; }
    .exp-table td.num { text-align:right; font-variant-numeric:tabular-nums; }

    /* No-data */
    .no-data { display:flex; align-items:center; justify-content:center; height:200px; color:#94a3b8; font-size:13px; }

    /* Card title */
    .card-title { font-weight:600; color:#1e293b; margin-bottom:.75rem; font-size:14px; }

    /* Back link */
    .back-link { font-size:13px; color:#6366f1; text-decoration:none; font-weight:600; }
    .back-link:hover { text-decoration:underline; }
@endsection

@section('content')
<div class="page-bg">

    {{-- Header --}}
    <div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:1rem;">
        <div>
            <h2 class="gradient-title" style="font-size:1.75rem;margin:0;">{{ $config['name'] }}</h2>
            <p style="color:#64748b;font-size:.875rem;margin:.25rem 0 0;">{{ __('builder.preview_wo_sub') }}</p>
        </div>
        <a href="{{ route('dashboard.builder', 'work-orders') }}" class="back-link" style="margin-top:.25rem;">{{ __('builder.edit_dashboard') }}</a>
    </div>

    {{-- Filters --}}
    @if($config['show_filters'])
    <form method="GET" action="{{ route('dashboard.preview', 'work-orders') }}" class="card-soft mb-3" style="margin-bottom:1rem;">
        <div class="filter-bar">
            <div class="filter-group">
                <label>{{ __('builder.filter_from') }}</label>
                <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}">
            </div>
            <div class="filter-group">
                <label>{{ __('builder.filter_to') }}</label>
                <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}">
            </div>
            <div class="filter-group">
                <label>{{ __('builder.filter_user') }}</label>
                <select name="user_id">
                    <option value="">{{ __('builder.filter_all_users') }}</option>
                    @foreach($userOptions as $u)
                        <option value="{{ $u->id }}" {{ ($filters['user_id'] ?? '') == $u->id ? 'selected' : '' }}>{{ $u->display_name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="filter-group">
                <label>{{ __('builder.filter_contract') }}</label>
                <select name="contract_id">
                    <option value="">{{ __('builder.filter_all_contracts') }}</option>
                    @foreach($contractOptions as $c)
                        <option value="{{ $c->id }}" {{ ($filters['contract_id'] ?? '') == $c->id ? 'selected' : '' }}>{{ $c->display_name }}</option>
                    @endforeach
                </select>
            </div>
            <div style="display:flex;gap:.5rem;align-items:flex-end;">
                <button type="submit" class="btn btn-primary btn-sm">{{ __('builder.apply') }}</button>
                <a href="{{ route('dashboard.preview', 'work-orders') }}" class="btn btn-sm btn-outline-secondary">{{ __('builder.reset') }}</a>
            </div>
        </div>
    </form>
    @endif

    {{-- KPI Cards --}}
    @if(!empty($config['kpis']))
    @php $kpiOptions = \App\Http\Controllers\DashboardBuilderController::KPI_OPTIONS; @endphp
    <div class="kpi-grid" style="grid-template-columns:repeat({{ $config['kpi_cols'] }},minmax(0,1fr));margin-bottom:1rem;">
        @foreach($config['kpis'] as $kpiKey)
            @if(isset($kpiOptions[$kpiKey]))
                @php
                    $opt   = $kpiOptions[$kpiKey];
                    $val   = $kpiValues[$kpiKey] ?? 0;
                    $fmtVal = $kpiKey === 'total_cost' ? number_format($val, 2) : number_format($val);
                @endphp
                <div class="card-soft kpi" style="--kpi-color:{{ $opt['color'] }};">
                    <style>.kpi[style*="{{ $opt['color'] }}"]:before { background:{{ $opt['color'] }}; }</style>
                    <div class="kpi-label">{{ $opt['label'] }}</div>
                    <div class="kpi-value">{{ $fmtVal }}</div>
                </div>
            @endif
        @endforeach
    </div>
    @endif

    {{-- Charts --}}
    @php
        $enabledCharts = collect($config['charts'])->filter(fn($c) => $c['enabled']);
    @endphp
    @if($enabledCharts->isNotEmpty())
    <div class="charts-grid">

        @if($config['charts']['monthly']['enabled'] ?? false)
        @php $chartType = $config['charts']['monthly']['type']; @endphp
        <div class="card-soft {{ in_array($chartType, ['line','area']) ? 'chart-wide' : '' }}">
            <div class="card-title">{{ __('builder.chart_monthly_title') }}</div>
            @if($chartData['monthly']->isEmpty())
                <div class="no-data">{{ __('builder.no_data') }}</div>
            @else
                <div id="chart_monthly"></div>
            @endif
        </div>
        @endif

        @if($config['charts']['by_category']['enabled'] ?? false)
        @php $chartType = $config['charts']['by_category']['type']; @endphp
        <div class="card-soft {{ $chartType === 'line' ? 'chart-wide' : '' }}">
            <div class="card-title">{{ __('builder.chart_by_category_title') }}</div>
            @if($chartData['by_category']->isEmpty())
                <div class="no-data">{{ __('builder.no_data') }}</div>
            @else
                <div id="chart_by_category"></div>
            @endif
        </div>
        @endif

        @if($config['charts']['by_building']['enabled'] ?? false)
        <div class="card-soft chart-wide">
            <div class="card-title">{{ __('builder.chart_by_building_title') }}</div>
            @if($chartData['by_building']->isEmpty())
                <div class="no-data">{{ __('builder.no_data') }}</div>
            @else
                <div id="chart_by_building"></div>
            @endif
        </div>
        @endif

        @if($config['charts']['by_status']['enabled'] ?? false)
        <div class="card-soft">
            <div class="card-title">{{ __('builder.chart_wo_status_title') }}</div>
            @if($chartData['by_status']->isEmpty())
                <div class="no-data">{{ __('builder.no_data') }}</div>
            @else
                <div id="chart_by_status"></div>
            @endif
        </div>
        @endif

        @if($config['charts']['by_wo_type']['enabled'] ?? false)
        <div class="card-soft">
            <div class="card-title">{{ __('builder.chart_by_wo_type_title') }}</div>
            @if($chartData['by_wo_type']->isEmpty())
                <div class="no-data">{{ __('builder.no_data') }}</div>
            @else
                <div id="chart_by_wo_type"></div>
            @endif
        </div>
        @endif

        @if($config['charts']['by_service']['enabled'] ?? false)
        <div class="card-soft">
            <div class="card-title">{{ __('builder.chart_by_service_title') }}</div>
            @if($chartData['by_service']->isEmpty())
                <div class="no-data">{{ __('builder.no_data') }}</div>
            @else
                <div id="chart_by_service"></div>
            @endif
        </div>
        @endif

        @if($config['charts']['by_journey']['enabled'] ?? false)
        <div class="card-soft">
            <div class="card-title">{{ __('builder.chart_by_journey_title') }}</div>
            @if($chartData['by_journey']->isEmpty())
                <div class="no-data">{{ __('builder.no_data') }}</div>
            @else
                <div id="chart_by_journey"></div>
            @endif
        </div>
        @endif

        @if($config['charts']['by_priority']['enabled'] ?? false)
        <div class="card-soft">
            <div class="card-title">{{ __('builder.chart_by_priority_title') }}</div>
            @if($chartData['by_priority']->isEmpty())
                <div class="no-data">{{ __('builder.no_data') }}</div>
            @else
                <div id="chart_by_priority"></div>
            @endif
        </div>
        @endif

        @if($config['charts']['expense_type']['enabled'] ?? false)
        <div class="card-soft">
            <div class="card-title">{{ __('builder.chart_expense_type_title') }}</div>
            @if($chartData['expense_type']->isEmpty())
                <div class="no-data">{{ __('builder.no_data') }}</div>
            @else
                <div id="chart_expense_type"></div>
            @endif
        </div>
        @endif

        @if($config['charts']['expense_cat']['enabled'] ?? false)
        @php $expCatType = $config['charts']['expense_cat']['type']; @endphp
        <div class="card-soft {{ $expCatType === 'table' ? 'chart-wide' : '' }}">
            <div class="card-title">{{ __('builder.chart_expense_cat_title') }}</div>
            @if($chartData['expense_cat']->isEmpty())
                <div class="no-data">{{ __('builder.no_data') }}</div>
            @elseif($expCatType === 'table')
                <table class="exp-table">
                    <thead>
                        <tr>
                            <th>{{ __('builder.th_category') }}</th>
                            <th class="num">{{ __('builder.th_work_orders') }}</th>
                            <th class="num">{{ __('builder.th_total_cost') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($chartData['expense_cat'] as $row)
                            <tr>
                                <td>{{ $row['label'] }}</td>
                                <td class="num">{{ number_format($row['wo_count']) }}</td>
                                <td class="num">{{ number_format($row['total'], 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <div id="chart_expense_cat"></div>
            @endif
        </div>
        @endif

    </div>
    @endif

</div>
@endsection

@section('scripts')
<script>
const PALETTE = ['#6366f1','#8b5cf6','#ec4899','#f43f5e','#f97316','#eab308','#22c55e','#14b8a6','#06b6d4','#3b82f6'];
const baseOpts = {
    chart:{ fontFamily:'inherit', toolbar:{ show:false }, animations:{ easing:'easeinout', speed:600 }, dir: IS_RTL ? 'rtl' : 'ltr' },
    grid:{ borderColor:'#e2e8f0', strokeDashArray:4 },
    dataLabels:{ enabled:false },
    tooltip:{ theme:'light' }
};

function makePieDonut(el, labels, values, type, height) {
    height = height || 280;
    new ApexCharts(el, {
        ...baseOpts,
        chart:{ ...baseOpts.chart, type: type, height: height },
        series: values,
        labels: labels,
        colors: PALETTE.slice(0, labels.length),
        stroke:{ width: type === 'donut' ? 0 : 2 },
        legend:{ position:'bottom', fontSize:'12px' },
        plotOptions: type === 'donut' ? { pie:{ donut:{ size:'65%', labels:{ show:true, total:{ show:true, label:'Total' }}}}} : {},
        dataLabels:{ enabled:true, style:{ fontSize:'11px', fontWeight:600, colors:['#fff'] }},
    }).render();
}

function makeBar(el, labels, values, height, horizontal) {
    horizontal = !!horizontal;
    height = height || 280;
    new ApexCharts(el, {
        ...baseOpts,
        chart:{ ...baseOpts.chart, type:'bar', height: height },
        series:[{ name:'Count', data: values }],
        xaxis:{ categories: labels },
        plotOptions:{ bar:{ horizontal: horizontal, borderRadius:5, barHeight: horizontal ? '70%' : undefined, distributed:true }},
        colors: PALETTE,
        legend:{ show:false },
    }).render();
}

function makeBarCost(el, labels, values, height, horizontal) {
    horizontal = !!horizontal;
    height = height || 280;
    new ApexCharts(el, {
        ...baseOpts,
        chart:{ ...baseOpts.chart, type:'bar', height: height },
        series:[{ name:'Total Cost', data: values }],
        xaxis:{ categories: labels },
        plotOptions:{ bar:{ horizontal: horizontal, borderRadius:5, barHeight: horizontal ? '70%' : undefined, distributed:true }},
        colors: PALETTE,
        legend:{ show:false },
        tooltip:{ y:{ formatter: v => new Intl.NumberFormat('en-US',{minimumFractionDigits:2,maximumFractionDigits:2}).format(v) }},
    }).render();
}

@if(!empty($config['charts']['monthly']['enabled']))
@php
    $monthlyType = $config['charts']['monthly']['type'];
    $monthlyData = $chartData['monthly'];
@endphp
@if(!$monthlyData->isEmpty())
(function(){
    const labels = @json($monthlyData->pluck('label'));
    const values = @json($monthlyData->pluck('count'));
    const type   = '{{ $monthlyType }}';
    const el     = document.querySelector('#chart_monthly');
    if (!el) return;
    if (type === 'bar') {
        makeBar(el, labels, values, 300, false);
    } else {
        new ApexCharts(el, {
            ...baseOpts,
            chart:{ ...baseOpts.chart, type: type, height: 300 },
            series:[{ name:'Work Orders', data: values }],
            xaxis:{ categories: labels },
            stroke:{ curve:'smooth', width:2 },
            fill: type === 'area' ? { type:'gradient', gradient:{ shadeIntensity:.6, opacityFrom:.4, opacityTo:.05 }} : {},
            colors:['#6366f1'],
        }).render();
    }
})();
@endif
@endif

@if(!empty($config['charts']['by_category']['enabled']))
@php $catType = $config['charts']['by_category']['type']; @endphp
@if(!$chartData['by_category']->isEmpty())
(function(){
    const el = document.querySelector('#chart_by_category');
    if (!el) return;
    @if($catType === 'line')
    const catSeries = @json($chartData['by_category']);
    const months    = @json($chartData['months']);
    new ApexCharts(el, {
        ...baseOpts,
        chart:{ ...baseOpts.chart, type:'line', height:320 },
        series: catSeries.map((s,i) => ({ name: s.name, data: s.data })),
        xaxis:{ categories: months },
        stroke:{ curve:'smooth', width:2 },
        colors: PALETTE.slice(0, catSeries.length),
        markers:{ size:3 },
        legend:{ position:'bottom', fontSize:'12px' },
    }).render();
    @else
    const catSimple = @json($chartData['by_category_simple']);
    const labels    = catSimple.map(r => r.label);
    const values    = catSimple.map(r => r.count);
    makeBar(el, labels, values, 300, true);
    @endif
})();
@endif
@endif

@if(!empty($config['charts']['by_building']['enabled']))
@if(!$chartData['by_building']->isEmpty())
(function(){
    const el = document.querySelector('#chart_by_building');
    if (!el) return;
    const data   = @json($chartData['by_building']);
    const labels = data.map(r => r.label);
    const values = data.map(r => r.count);
    makeBar(el, labels, values, Math.max(280, labels.length * 30), true);
})();
@endif
@endif

@if(!empty($config['charts']['by_status']['enabled']))
@php $statusType = $config['charts']['by_status']['type']; @endphp
@if(!$chartData['by_status']->isEmpty())
(function(){
    const el = document.querySelector('#chart_by_status');
    if (!el) return;
    const data   = @json($chartData['by_status']);
    const labels = data.map(r => r.label);
    const values = data.map(r => r.count);
    const type   = '{{ $statusType }}';
    if (type === 'bar') {
        makeBar(el, labels, values, 280, false);
    } else {
        makePieDonut(el, labels, values, type, 280);
    }
})();
@endif
@endif

@if(!empty($config['charts']['by_wo_type']['enabled']))
@php $woTypeType = $config['charts']['by_wo_type']['type']; @endphp
@if(!$chartData['by_wo_type']->isEmpty())
(function(){
    const el = document.querySelector('#chart_by_wo_type');
    if (!el) return;
    const data   = @json($chartData['by_wo_type']);
    const labels = data.map(r => r.label);
    const values = data.map(r => r.count);
    const type   = '{{ $woTypeType }}';
    if (type === 'bar') {
        makeBar(el, labels, values, 280, false);
    } else {
        makePieDonut(el, labels, values, type, 280);
    }
})();
@endif
@endif

@if(!empty($config['charts']['by_service']['enabled']))
@php $serviceType = $config['charts']['by_service']['type']; @endphp
@if(!$chartData['by_service']->isEmpty())
(function(){
    const el = document.querySelector('#chart_by_service');
    if (!el) return;
    const data   = @json($chartData['by_service']);
    const labels = data.map(r => r.label);
    const values = data.map(r => r.count);
    const type   = '{{ $serviceType }}';
    if (type === 'bar') {
        makeBar(el, labels, values, 280, false);
    } else {
        makePieDonut(el, labels, values, type, 280);
    }
})();
@endif
@endif

@if(!empty($config['charts']['by_journey']['enabled']))
@php $journeyType = $config['charts']['by_journey']['type']; @endphp
@if(!$chartData['by_journey']->isEmpty())
(function(){
    const el = document.querySelector('#chart_by_journey');
    if (!el) return;
    const data   = @json($chartData['by_journey']);
    const labels = data.map(r => r.label);
    const values = data.map(r => r.count);
    const type   = '{{ $journeyType }}';
    if (type === 'bar') {
        makeBar(el, labels, values, 280, false);
    } else {
        makePieDonut(el, labels, values, type, 280);
    }
})();
@endif
@endif

@if(!empty($config['charts']['by_priority']['enabled']))
@php $priorityType = $config['charts']['by_priority']['type']; @endphp
@if(!$chartData['by_priority']->isEmpty())
(function(){
    const el = document.querySelector('#chart_by_priority');
    if (!el) return;
    const data   = @json($chartData['by_priority']);
    const labels = data.map(r => r.label);
    const values = data.map(r => r.count);
    const type   = '{{ $priorityType }}';
    if (type === 'bar') {
        makeBar(el, labels, values, 280, false);
    } else {
        makePieDonut(el, labels, values, type, 280);
    }
})();
@endif
@endif

@if(!empty($config['charts']['expense_type']['enabled']))
@php $expTypeType = $config['charts']['expense_type']['type']; @endphp
@if(!$chartData['expense_type']->isEmpty())
(function(){
    const el = document.querySelector('#chart_expense_type');
    if (!el) return;
    const data   = @json($chartData['expense_type']);
    const labels = data.map(r => r.label);
    const values = data.map(r => r.total);
    const type   = '{{ $expTypeType }}';
    if (type === 'bar') {
        makeBarCost(el, labels, values, 280, false);
    } else {
        new ApexCharts(el, {
            ...baseOpts,
            chart:{ ...baseOpts.chart, type: type, height: 280 },
            series: values,
            labels: labels,
            colors: PALETTE.slice(0, labels.length),
            stroke:{ width: type === 'donut' ? 0 : 2 },
            legend:{ position:'bottom', fontSize:'12px' },
            plotOptions: type === 'donut' ? { pie:{ donut:{ size:'65%', labels:{ show:true, total:{ show:true, label:'Total' }}}}} : {},
            dataLabels:{ enabled:true, style:{ fontSize:'11px', fontWeight:600, colors:['#fff'] }},
            tooltip:{ y:{ formatter: v => new Intl.NumberFormat('en-US',{minimumFractionDigits:2,maximumFractionDigits:2}).format(v) }},
        }).render();
    }
})();
@endif
@endif

@if(!empty($config['charts']['expense_cat']['enabled']) && $config['charts']['expense_cat']['type'] === 'bar')
@if(!$chartData['expense_cat']->isEmpty())
(function(){
    const el = document.querySelector('#chart_expense_cat');
    if (!el) return;
    const data   = @json($chartData['expense_cat']);
    const labels = data.map(r => r.label);
    const values = data.map(r => r.total);
    makeBarCost(el, labels, values, Math.max(280, labels.length * 30), true);
})();
@endif
@endif

</script>
@endsection
