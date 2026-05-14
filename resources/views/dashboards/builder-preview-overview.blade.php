@extends('layouts.app')
@section('title', $config['name'])

@section('styles')
    .page-bg { background: linear-gradient(180deg,#f1f5f9 0%,#e2e8f0 100%); padding: 1.25rem; border-radius: 12px; }
    .card-soft { background:#fff; border-radius:14px; box-shadow: 0 1px 2px rgba(15,23,42,.04), 0 8px 24px -12px rgba(15,23,42,.08); padding:1rem; }
    .gradient-title { background:linear-gradient(90deg,#f59e0b,#6366f1,#10b981); -webkit-background-clip:text; background-clip:text; color:transparent; font-weight:700; }

    .kpi-grid { display:grid; gap:.75rem; margin-bottom:1rem; }
    .kpi { position:relative; overflow:hidden; padding-left:1.25rem; }
    .kpi::before { content:""; position:absolute; left:0; top:0; bottom:0; width:4px; border-radius:4px 0 0 4px; }
    .kpi-label { font-size:11px; text-transform:uppercase; letter-spacing:.06em; color:#64748b; font-weight:600; }
    .kpi-value { font-size:1.5rem; font-weight:700; color:#0f172a; margin-top:.25rem; }

    .charts-grid { display:grid; grid-template-columns:1fr 1fr; gap:1rem; margin-bottom:1rem; }
    .chart-wide { grid-column:1 / -1; }
    @media (max-width:900px) {
        .charts-grid { grid-template-columns:1fr; }
        .kpi-grid { grid-template-columns:repeat(2,1fr) !important; }
    }

    .filter-bar { display:flex; flex-wrap:wrap; gap:.75rem; align-items:flex-end; }
    .filter-group { display:flex; flex-direction:column; gap:.2rem; min-width:150px; flex:1; }
    .filter-group label { font-size:11px; text-transform:uppercase; letter-spacing:.05em; color:#64748b; font-weight:600; }
    .filter-group select { padding:.4rem .5rem; border:1.5px solid #e2e8f0; border-radius:8px; font-size:13px; background:#f8fafc; outline:none; }
    .filter-group select:focus { border-color:#f59e0b; box-shadow:0 0 0 2px rgba(245,158,11,.1); }

    .no-data { display:flex; align-items:center; justify-content:center; height:200px; color:#94a3b8; font-size:13px; }
    .card-title { font-weight:600; color:#1e293b; margin-bottom:.75rem; font-size:14px; }
    .back-link { font-size:13px; color:#f59e0b; text-decoration:none; font-weight:600; }
    .back-link:hover { text-decoration:underline; }
@endsection

@section('content')
<div class="page-bg">

    {{-- Header --}}
    <div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:1rem;">
        <div>
            <h2 class="gradient-title" style="font-size:1.75rem;margin:0;">{{ $config['name'] }}</h2>
            <p style="color:#64748b;font-size:.875rem;margin:.25rem 0 0;">{{ __('builder.preview_overview_sub') }}</p>
        </div>
        <a href="{{ route('dashboard.builder', 'overview') }}" class="back-link" style="margin-top:.25rem;">{{ __('builder.edit_dashboard') }}</a>
    </div>

    {{-- Filters --}}
    @if($config['show_filters'])
    <form method="GET" action="{{ route('dashboard.preview', 'overview') }}" class="card-soft" style="margin-bottom:1rem;">
        <div class="filter-bar">
            <div class="filter-group">
                <label>{{ __('builder.filter_project') }}</label>
                <select name="project_id">
                    <option value="">{{ __('builder.filter_all_projects') }}</option>
                    @foreach($projects as $p)
                        <option value="{{ $p->project_id }}" {{ ($filters['project_id'] ?? '') == $p->project_id ? 'selected' : '' }}>
                            {{ $p->project_name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div style="display:flex;gap:.5rem;align-items:flex-end;">
                <button type="submit" class="btn btn-primary btn-sm">{{ __('builder.apply') }}</button>
                <a href="{{ route('dashboard.preview', 'overview') }}" class="btn btn-sm btn-outline-secondary">{{ __('builder.reset') }}</a>
            </div>
        </div>
    </form>
    @endif

    {{-- KPI Cards --}}
    @if(!empty($config['kpis']))
    @php
        $ovKpiOpts = \App\Http\Controllers\DashboardBuilderController::OVERVIEW_KPI_OPTIONS;
        $floatKpis = ['wo_cost', 'asset_value', 'billing_collected', 'billing_outstanding', 'billing_overdue', 'contract_value', 'contract_overdue'];
    @endphp
    <div class="kpi-grid" style="grid-template-columns:repeat({{ $config['kpi_cols'] }},minmax(0,1fr));margin-bottom:1rem;">
        @foreach($config['kpis'] as $kpiKey)
            @if(isset($ovKpiOpts[$kpiKey]))
                @php
                    $opt    = $ovKpiOpts[$kpiKey];
                    $val    = $kpiValues[$kpiKey] ?? 0;
                    $fmtVal = in_array($kpiKey, $floatKpis) ? number_format($val, 2) : number_format($val);
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
    @php $enabledCharts = collect($config['charts'])->filter(fn($c) => $c['enabled']); @endphp
    @if($enabledCharts->isNotEmpty())
    <div class="charts-grid">

        @if($config['charts']['wo_by_status']['enabled'] ?? false)
        <div class="card-soft">
            <div class="card-title">{{ __('builder.chart_wo_by_status_title') }}</div>
            @if($chartData['wo_by_status']->isEmpty())
                <div class="no-data">{{ __('builder.no_data') }}</div>
            @else
                <div id="chart_wo_by_status"></div>
            @endif
        </div>
        @endif

        @if($config['charts']['wo_by_type']['enabled'] ?? false)
        <div class="card-soft">
            <div class="card-title">{{ __('builder.chart_wo_by_type_title') }}</div>
            @if($chartData['wo_by_type']->isEmpty())
                <div class="no-data">{{ __('builder.no_data') }}</div>
            @else
                <div id="chart_wo_by_type"></div>
            @endif
        </div>
        @endif

        @if($config['charts']['prop_by_region']['enabled'] ?? false)
        <div class="card-soft">
            <div class="card-title">{{ __('builder.chart_props_by_region_title') }}</div>
            @if($chartData['prop_by_region']->isEmpty())
                <div class="no-data">{{ __('builder.no_data') }}</div>
            @else
                <div id="chart_prop_by_region"></div>
            @endif
        </div>
        @endif

        @if($config['charts']['asset_by_category']['enabled'] ?? false)
        <div class="card-soft">
            <div class="card-title">{{ __('builder.chart_assets_by_cat_title') }}</div>
            @if($chartData['asset_by_category']->isEmpty())
                <div class="no-data">{{ __('builder.no_data') }}</div>
            @else
                <div id="chart_asset_by_category"></div>
            @endif
        </div>
        @endif

        @if($config['charts']['user_by_type']['enabled'] ?? false)
        <div class="card-soft">
            <div class="card-title">{{ __('builder.chart_users_by_type_title') }}</div>
            @if($chartData['user_by_type']->isEmpty())
                <div class="no-data">{{ __('builder.no_data') }}</div>
            @else
                <div id="chart_user_by_type"></div>
            @endif
        </div>
        @endif

        @if($config['charts']['billing_summary']['enabled'] ?? false)
        <div class="card-soft">
            <div class="card-title">{{ __('builder.chart_billing_summary_title') }}</div>
            <div id="chart_billing_summary"></div>
        </div>
        @endif

        @if($config['charts']['contract_by_type']['enabled'] ?? false)
        <div class="card-soft">
            <div class="card-title">{{ __('builder.chart_contracts_by_type_title') }}</div>
            @if($chartData['contract_by_type']->isEmpty())
                <div class="no-data">{{ __('builder.no_data') }}</div>
            @else
                <div id="chart_contract_by_type"></div>
            @endif
        </div>
        @endif

    </div>
    @endif

</div>
@endsection

@section('scripts')
<script>
const PALETTE = ['#6366f1','#22c55e','#f59e0b','#0ea5e9','#a855f7','#ec4899','#14b8a6','#f97316','#10b981','#3b82f6'];
const baseOpts = {
    chart:{ fontFamily:'inherit', toolbar:{ show:false }, animations:{ easing:'easeinout', speed:600 }, dir: IS_RTL ? 'rtl' : 'ltr' },
    grid:{ borderColor:'#e2e8f0', strokeDashArray:4 },
    dataLabels:{ enabled:false },
    tooltip:{ theme:'light' }
};

function makeHBar(el, labels, values, height, palette) {
    new ApexCharts(el, {
        ...baseOpts,
        chart:{ ...baseOpts.chart, type:'bar', height: height || Math.max(250, labels.length * 36) },
        series:[{ name:'Count', data: values }],
        xaxis:{ categories: labels, labels:{ style:{ fontSize:'11px' }}},
        plotOptions:{ bar:{ horizontal:true, borderRadius:5, barHeight:'65%', distributed:true }},
        colors: (palette || PALETTE).slice(0, labels.length),
        legend:{ show:false },
    }).render();
}

function makeVBar(el, labels, values, height, palette) {
    new ApexCharts(el, {
        ...baseOpts,
        chart:{ ...baseOpts.chart, type:'bar', height: height || 280 },
        series:[{ name:'Count', data: values }],
        xaxis:{ categories: labels, labels:{ style:{ fontSize:'11px' }, rotate:-30 }},
        plotOptions:{ bar:{ borderRadius:5, columnWidth:'60%', distributed:true }},
        colors: (palette || PALETTE).slice(0, labels.length),
        legend:{ show:false },
    }).render();
}

function makePieDonut(el, labels, values, type, height) {
    new ApexCharts(el, {
        ...baseOpts,
        chart:{ ...baseOpts.chart, type: type, height: height || 300 },
        series: values, labels: labels,
        colors: PALETTE.slice(0, labels.length),
        stroke:{ width: type === 'donut' ? 0 : 2 },
        legend:{ position:'bottom', fontSize:'12px' },
        plotOptions: type === 'donut' ? { pie:{ donut:{ size:'65%', labels:{ show:true, total:{ show:true, label:'Total' }}}}} : {},
        dataLabels:{ enabled:true, style:{ fontSize:'11px', fontWeight:600, colors:['#fff'] }},
    }).render();
}

@if(!empty($config['charts']['wo_by_status']['enabled']))
@if(!$chartData['wo_by_status']->isEmpty())
(function(){
    const el = document.querySelector('#chart_wo_by_status');
    if (!el) return;
    const data   = @json($chartData['wo_by_status']);
    const labels = data.map(r => r.label);
    const values = data.map(r => r.count);
    const type   = '{{ $config['charts']['wo_by_status']['type'] }}';
    if (type === 'bar') { makeHBar(el, labels, values); }
    else { makePieDonut(el, labels, values, type, 300); }
})();
@endif
@endif

@if(!empty($config['charts']['wo_by_type']['enabled']))
@if(!$chartData['wo_by_type']->isEmpty())
(function(){
    const el = document.querySelector('#chart_wo_by_type');
    if (!el) return;
    const data   = @json($chartData['wo_by_type']);
    const labels = data.map(r => r.label);
    const values = data.map(r => r.count);
    const type   = '{{ $config['charts']['wo_by_type']['type'] }}';
    if (type === 'bar') { makeHBar(el, labels, values); }
    else { makePieDonut(el, labels, values, type, 300); }
})();
@endif
@endif

@if(!empty($config['charts']['prop_by_region']['enabled']))
@if(!$chartData['prop_by_region']->isEmpty())
(function(){
    const el = document.querySelector('#chart_prop_by_region');
    if (!el) return;
    const data   = @json($chartData['prop_by_region']);
    makeHBar(el, data.map(r => r.label), data.map(r => r.count));
})();
@endif
@endif

@if(!empty($config['charts']['asset_by_category']['enabled']))
@if(!$chartData['asset_by_category']->isEmpty())
(function(){
    const el = document.querySelector('#chart_asset_by_category');
    if (!el) return;
    const data   = @json($chartData['asset_by_category']);
    const labels = data.map(r => r.label);
    const values = data.map(r => r.count);
    const type   = '{{ $config['charts']['asset_by_category']['type'] }}';
    if (type === 'bar') { makeHBar(el, labels, values); }
    else { makePieDonut(el, labels, values, type, 300); }
})();
@endif
@endif

@if(!empty($config['charts']['user_by_type']['enabled']))
@if(!$chartData['user_by_type']->isEmpty())
(function(){
    const el = document.querySelector('#chart_user_by_type');
    if (!el) return;
    const data   = @json($chartData['user_by_type']);
    const labels = data.map(r => r.label);
    const values = data.map(r => r.count);
    const type   = '{{ $config['charts']['user_by_type']['type'] }}';
    if (type === 'bar') { makeHBar(el, labels, values); }
    else { makePieDonut(el, labels, values, type, 300); }
})();
@endif
@endif

@if(!empty($config['charts']['billing_summary']['enabled']))
(function(){
    const el = document.querySelector('#chart_billing_summary');
    if (!el) return;
    const data   = @json($chartData['billing_summary']);
    const labels = data.map(r => r.label);
    const values = data.map(r => r.value);
    new ApexCharts(el, {
        ...baseOpts,
        chart:{ ...baseOpts.chart, type:'bar', height:240 },
        series:[{ name:'Amount', data: values }],
        xaxis:{ categories: labels, labels:{ style:{ fontSize:'12px' }}},
        plotOptions:{ bar:{ borderRadius:6, columnWidth:'50%', distributed:true }},
        colors:['#10b981','#f59e0b','#ef4444'],
        legend:{ show:false },
        tooltip:{ y:{ formatter: v => new Intl.NumberFormat('en-US',{minimumFractionDigits:2,maximumFractionDigits:2}).format(v) }},
    }).render();
})();
@endif

@if(!empty($config['charts']['contract_by_type']['enabled']))
@if(!$chartData['contract_by_type']->isEmpty())
(function(){
    const el = document.querySelector('#chart_contract_by_type');
    if (!el) return;
    const data   = @json($chartData['contract_by_type']);
    const labels = data.map(r => r.label);
    const values = data.map(r => r.count);
    const type   = '{{ $config['charts']['contract_by_type']['type'] }}';
    if (type === 'bar') { makeHBar(el, labels, values); }
    else { makePieDonut(el, labels, values, type, 300); }
})();
@endif
@endif

</script>
@endsection
