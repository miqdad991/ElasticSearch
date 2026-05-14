@extends('layouts.app')
@section('title', $config['name'])

@section('styles')
    .page-bg { background: linear-gradient(180deg,#f1f5f9 0%,#e2e8f0 100%); padding: 1.25rem; border-radius: 12px; }
    .card-soft { background:#fff; border-radius:14px; box-shadow: 0 1px 2px rgba(15,23,42,.04), 0 8px 24px -12px rgba(15,23,42,.08); padding:1rem; }
    .gradient-title { background:linear-gradient(90deg,#0ea5e9,#6366f1,#d946ef); -webkit-background-clip:text; background-clip:text; color:transparent; font-weight:700; }

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
    .filter-group { display:flex; flex-direction:column; gap:.2rem; min-width:130px; flex:1; }
    .filter-group label { font-size:11px; text-transform:uppercase; letter-spacing:.05em; color:#64748b; font-weight:600; }
    .filter-group select, .filter-group input { padding:.4rem .5rem; border:1.5px solid #e2e8f0; border-radius:8px; font-size:13px; background:#f8fafc; outline:none; }
    .filter-group select:focus, .filter-group input:focus { border-color:#0ea5e9; box-shadow:0 0 0 2px rgba(14,165,233,.1); }

    /* No-data */
    .no-data { display:flex; align-items:center; justify-content:center; height:200px; color:#94a3b8; font-size:13px; }

    /* Card title */
    .card-title { font-weight:600; color:#1e293b; margin-bottom:.75rem; font-size:14px; }

    /* Back link */
    .back-link { font-size:13px; color:#0ea5e9; text-decoration:none; font-weight:600; }
    .back-link:hover { text-decoration:underline; }
@endsection

@section('content')
<div class="page-bg">

    {{-- Header --}}
    <div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:1rem;">
        <div>
            <h2 class="gradient-title" style="font-size:1.75rem;margin:0;">{{ $config['name'] }}</h2>
            <p style="color:#64748b;font-size:.875rem;margin:.25rem 0 0;">{{ __('builder.preview_props_sub') }}</p>
        </div>
        <a href="{{ route('dashboard.builder', 'properties') }}" class="back-link" style="margin-top:.25rem;">{{ __('builder.edit_dashboard') }}</a>
    </div>

    {{-- Filters --}}
    @if($config['show_filters'])
    <form method="GET" action="{{ route('dashboard.preview', 'properties') }}" class="card-soft" style="margin-bottom:1rem;">
        <div class="filter-bar">
            <div class="filter-group">
                <label>{{ __('builder.filter_property_type') }}</label>
                <select name="property_type">
                    <option value="">{{ __('builder.filter_all_types') }}</option>
                    <option value="building"  {{ ($filters['property_type'] ?? '') === 'building'  ? 'selected' : '' }}>{{ __('builder.filter_building') }}</option>
                    <option value="complex"   {{ ($filters['property_type'] ?? '') === 'complex'   ? 'selected' : '' }}>{{ __('builder.filter_complex') }}</option>
                </select>
            </div>
            <div class="filter-group">
                <label>{{ __('builder.filter_location_type') }}</label>
                <select name="location_type">
                    <option value="">{{ __('builder.filter_all') }}</option>
                    <option value="urban"  {{ ($filters['location_type'] ?? '') === 'urban'  ? 'selected' : '' }}>{{ __('builder.filter_urban') }}</option>
                    <option value="rural"  {{ ($filters['location_type'] ?? '') === 'rural'  ? 'selected' : '' }}>{{ __('builder.filter_rural') }}</option>
                </select>
            </div>
            <div class="filter-group">
                <label>{{ __('builder.filter_status') }}</label>
                <select name="status">
                    <option value="">{{ __('builder.filter_all_statuses') }}</option>
                    <option value="1" {{ ($filters['status'] ?? '') === '1' ? 'selected' : '' }}>{{ __('builder.filter_active') }}</option>
                    <option value="0" {{ ($filters['status'] ?? '') === '0' ? 'selected' : '' }}>{{ __('builder.filter_inactive') }}</option>
                </select>
            </div>
            <div class="filter-group">
                <label>{{ __('builder.filter_region') }}</label>
                <select name="region_id">
                    <option value="">{{ __('builder.filter_all_regions') }}</option>
                    @foreach($regions as $r)
                        <option value="{{ $r->region_id }}" {{ ($filters['region_id'] ?? '') == $r->region_id ? 'selected' : '' }}>{{ $r->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="filter-group">
                <label>{{ __('builder.filter_city') }}</label>
                <select name="city_id">
                    <option value="">{{ __('builder.filter_all_cities') }}</option>
                    @foreach($cities as $c)
                        <option value="{{ $c->city_id }}" {{ ($filters['city_id'] ?? '') == $c->city_id ? 'selected' : '' }}>{{ $c->name_en }}</option>
                    @endforeach
                </select>
            </div>
            <div style="display:flex;gap:.5rem;align-items:flex-end;">
                <button type="submit" class="btn btn-primary btn-sm">{{ __('builder.apply') }}</button>
                <a href="{{ route('dashboard.preview', 'properties') }}" class="btn btn-sm btn-outline-secondary">{{ __('builder.reset') }}</a>
            </div>
        </div>
    </form>
    @endif

    {{-- KPI Cards --}}
    @if(!empty($config['kpis']))
    @php $propKpiOptions = \App\Http\Controllers\DashboardBuilderController::PROPERTIES_KPI_OPTIONS; @endphp
    <div class="kpi-grid" style="grid-template-columns:repeat({{ $config['kpi_cols'] }},minmax(0,1fr));margin-bottom:1rem;">
        @foreach($config['kpis'] as $kpiKey)
            @if(isset($propKpiOptions[$kpiKey]))
                @php
                    $opt = $propKpiOptions[$kpiKey];
                    $val = $kpiValues[$kpiKey] ?? 0;
                    $fmtVal = in_array($kpiKey, ['total_budget', 'total_wo_cost'])
                        ? number_format($val, 2)
                        : number_format($val);
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
        @php $monthlyType = $config['charts']['monthly']['type']; @endphp
        <div class="card-soft {{ in_array($monthlyType, ['area','line']) ? 'chart-wide' : '' }}">
            <div class="card-title">{{ __('builder.chart_monthly_title') }}</div>
            @if($chartData['monthly']->isEmpty())
                <div class="no-data">{{ __('builder.no_data') }}</div>
            @else
                <div id="chart_monthly"></div>
            @endif
        </div>
        @endif

        @if($config['charts']['by_type']['enabled'] ?? false)
        <div class="card-soft">
            <div class="card-title">{{ __('builder.chart_by_prop_type_title') }}</div>
            @if($chartData['by_type']->isEmpty())
                <div class="no-data">{{ __('builder.no_data') }}</div>
            @else
                <div id="chart_by_type"></div>
            @endif
        </div>
        @endif

        @if($config['charts']['by_status']['enabled'] ?? false)
        <div class="card-soft">
            <div class="card-title">{{ __('builder.chart_props_by_status_title') }}</div>
            @if($chartData['by_status']->isEmpty())
                <div class="no-data">{{ __('builder.no_data') }}</div>
            @else
                <div id="chart_by_status"></div>
            @endif
        </div>
        @endif

        @if($config['charts']['by_region']['enabled'] ?? false)
        <div class="card-soft chart-wide">
            <div class="card-title">{{ __('builder.chart_by_region_title') }}</div>
            @if($chartData['by_region']->isEmpty())
                <div class="no-data">{{ __('builder.no_data') }}</div>
            @else
                <div id="chart_by_region"></div>
            @endif
        </div>
        @endif

        @if($config['charts']['by_city']['enabled'] ?? false)
        <div class="card-soft chart-wide">
            <div class="card-title">{{ __('builder.chart_by_city_title') }}</div>
            @if($chartData['by_city']->isEmpty())
                <div class="no-data">{{ __('builder.no_data') }}</div>
            @else
                <div id="chart_by_city"></div>
            @endif
        </div>
        @endif

        @if($config['charts']['top_props']['enabled'] ?? false)
        <div class="card-soft chart-wide">
            <div class="card-title">{{ __('builder.chart_top_props_title') }}</div>
            @if($chartData['top_props']->isEmpty())
                <div class="no-data">{{ __('builder.no_data') }}</div>
            @else
                <div id="chart_top_props"></div>
            @endif
        </div>
        @endif

        @if($config['charts']['by_ejar']['enabled'] ?? false)
        <div class="card-soft">
            <div class="card-title">{{ __('builder.chart_by_ejar_title') }}</div>
            @if($chartData['by_ejar']->isEmpty())
                <div class="no-data">{{ __('builder.no_data') }}</div>
            @else
                <div id="chart_by_ejar"></div>
            @endif
        </div>
        @endif

    </div>
    @endif

</div>
@endsection

@section('scripts')
<script>
const PALETTE = ['#0ea5e9','#6366f1','#10b981','#f59e0b','#ec4899','#8b5cf6','#f97316','#14b8a6','#d946ef','#22c55e'];
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

function makeBar(el, labels, values, height, horizontal, seriesName) {
    horizontal = !!horizontal;
    height = height || 280;
    new ApexCharts(el, {
        ...baseOpts,
        chart:{ ...baseOpts.chart, type:'bar', height: height },
        series:[{ name: seriesName || 'Count', data: values }],
        xaxis:{ categories: labels, labels:{ style:{ fontSize:'11px' }}},
        plotOptions:{ bar:{ horizontal: horizontal, borderRadius:5, barHeight: horizontal ? '65%' : undefined, distributed:true }},
        colors: PALETTE,
        legend:{ show:false },
    }).render();
}

@if(!empty($config['charts']['monthly']['enabled']))
@php $monthlyType = $config['charts']['monthly']['type']; @endphp
@if(!$chartData['monthly']->isEmpty())
(function(){
    const el = document.querySelector('#chart_monthly');
    if (!el) return;
    const labels = @json($chartData['monthly']->pluck('label'));
    const values = @json($chartData['monthly']->pluck('count'));
    const type   = '{{ $monthlyType }}';
    if (type === 'bar') {
        makeBar(el, labels, values, 300, false, 'Properties');
    } else {
        new ApexCharts(el, {
            ...baseOpts,
            chart:{ ...baseOpts.chart, type: type, height: 300 },
            series:[{ name:'Properties', data: values }],
            xaxis:{ categories: labels },
            stroke:{ curve:'smooth', width:2 },
            fill: type === 'area' ? { type:'gradient', gradient:{ shadeIntensity:.6, opacityFrom:.4, opacityTo:.05 }} : {},
            colors:['#0ea5e9'],
        }).render();
    }
})();
@endif
@endif

@if(!empty($config['charts']['by_type']['enabled']))
@php $byTypeType = $config['charts']['by_type']['type']; @endphp
@if(!$chartData['by_type']->isEmpty())
(function(){
    const el = document.querySelector('#chart_by_type');
    if (!el) return;
    const data   = @json($chartData['by_type']);
    const labels = data.map(r => r.label);
    const values = data.map(r => r.count);
    const type   = '{{ $byTypeType }}';
    if (type === 'bar') { makeBar(el, labels, values, 280, false); }
    else { makePieDonut(el, labels, values, type, 280); }
})();
@endif
@endif

@if(!empty($config['charts']['by_status']['enabled']))
@php $byStatusType = $config['charts']['by_status']['type']; @endphp
@if(!$chartData['by_status']->isEmpty())
(function(){
    const el = document.querySelector('#chart_by_status');
    if (!el) return;
    const data   = @json($chartData['by_status']);
    const labels = data.map(r => r.label);
    const values = data.map(r => r.count);
    const type   = '{{ $byStatusType }}';
    if (type === 'bar') { makeBar(el, labels, values, 280, false); }
    else { makePieDonut(el, labels, values, type, 280); }
})();
@endif
@endif

@if(!empty($config['charts']['by_region']['enabled']))
@if(!$chartData['by_region']->isEmpty())
(function(){
    const el = document.querySelector('#chart_by_region');
    if (!el) return;
    const data   = @json($chartData['by_region']);
    const labels = data.map(r => r.label);
    const values = data.map(r => r.count);
    makeBar(el, labels, values, Math.max(280, labels.length * 36), true);
})();
@endif
@endif

@if(!empty($config['charts']['by_city']['enabled']))
@if(!$chartData['by_city']->isEmpty())
(function(){
    const el = document.querySelector('#chart_by_city');
    if (!el) return;
    const data   = @json($chartData['by_city']);
    const labels = data.map(r => r.label);
    const values = data.map(r => r.count);
    makeBar(el, labels, values, Math.max(280, labels.length * 36), true);
})();
@endif
@endif

@if(!empty($config['charts']['top_props']['enabled']))
@if(!$chartData['top_props']->isEmpty())
(function(){
    const el = document.querySelector('#chart_top_props');
    if (!el) return;
    const data   = @json($chartData['top_props']);
    const labels = data.map(r => r.label);
    const values = data.map(r => r.count);
    makeBar(el, labels, values, Math.max(280, labels.length * 36), true, 'Contracts');
})();
@endif
@endif

@if(!empty($config['charts']['by_ejar']['enabled']))
@php $ejarType = $config['charts']['by_ejar']['type']; @endphp
@if(!$chartData['by_ejar']->isEmpty())
(function(){
    const el = document.querySelector('#chart_by_ejar');
    if (!el) return;
    const data   = @json($chartData['by_ejar']);
    const labels = data.map(r => r.label);
    const values = data.map(r => r.count);
    const type   = '{{ $ejarType }}';
    if (type === 'bar') { makeBar(el, labels, values, 280, false); }
    else { makePieDonut(el, labels, values, type, 280); }
})();
@endif
@endif

</script>
@endsection
