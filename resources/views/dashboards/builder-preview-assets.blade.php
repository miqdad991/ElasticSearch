@extends('layouts.app')
@section('title', $config['name'])

@section('styles')
    .page-bg { background: linear-gradient(180deg,#f1f5f9 0%,#e2e8f0 100%); padding: 1.25rem; border-radius: 12px; }
    .card-soft { background:#fff; border-radius:14px; box-shadow: 0 1px 2px rgba(15,23,42,.04), 0 8px 24px -12px rgba(15,23,42,.08); padding:1rem; }
    .gradient-title { background:linear-gradient(90deg,#14b8a6,#6366f1,#f59e0b); -webkit-background-clip:text; background-clip:text; color:transparent; font-weight:700; }

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
    .filter-group { display:flex; flex-direction:column; gap:.2rem; min-width:130px; flex:1; }
    .filter-group label { font-size:11px; text-transform:uppercase; letter-spacing:.05em; color:#64748b; font-weight:600; }
    .filter-group select { padding:.4rem .5rem; border:1.5px solid #e2e8f0; border-radius:8px; font-size:13px; background:#f8fafc; outline:none; }
    .filter-group select:focus { border-color:#14b8a6; box-shadow:0 0 0 2px rgba(20,184,166,.1); }

    .bld-table { width:100%; border-collapse:collapse; font-size:13px; }
    .bld-table th { text-align:left; padding:.5rem .65rem; color:#64748b; font-weight:600; font-size:11px; text-transform:uppercase; letter-spacing:.04em; border-bottom:1px solid #e2e8f0; background:#f8fafc; white-space:nowrap; }
    .bld-table td { padding:.5rem .65rem; border-bottom:1px solid #f1f5f9; color:#334155; white-space:nowrap; }
    .bld-table td.num { text-align:right; font-variant-numeric:tabular-nums; }
    .pill { display:inline-block; padding:2px 8px; border-radius:9999px; font-size:11px; font-weight:600; }

    .no-data { display:flex; align-items:center; justify-content:center; height:200px; color:#94a3b8; font-size:13px; }
    .card-title { font-weight:600; color:#1e293b; margin-bottom:.75rem; font-size:14px; }
    .back-link { font-size:13px; color:#14b8a6; text-decoration:none; font-weight:600; }
    .back-link:hover { text-decoration:underline; }
@endsection

@section('content')
<div class="page-bg">

    {{-- Header --}}
    <div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:1rem;">
        <div>
            <h2 class="gradient-title" style="font-size:1.75rem;margin:0;">{{ $config['name'] }}</h2>
            <p style="color:#64748b;font-size:.875rem;margin:.25rem 0 0;">{{ __('builder.preview_assets_sub') }}</p>
        </div>
        <a href="{{ route('dashboard.builder', 'assets') }}" class="back-link" style="margin-top:.25rem;">{{ __('builder.edit_dashboard') }}</a>
    </div>

    {{-- Filters --}}
    @if($config['show_filters'])
    <form method="GET" action="{{ route('dashboard.preview', 'assets') }}" class="card-soft" style="margin-bottom:1rem;">
        <div class="filter-bar">
            <div class="filter-group">
                <label>{{ __('builder.filter_category') }}</label>
                <select name="asset_category_id">
                    <option value="">{{ __('builder.filter_all_categories') }}</option>
                    @foreach($categories as $c)
                        <option value="{{ $c->asset_category_id }}" {{ ($filters['asset_category_id'] ?? '') == $c->asset_category_id ? 'selected' : '' }}>
                            {{ $c->asset_category }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="filter-group">
                <label>{{ __('builder.filter_status') }}</label>
                <select name="asset_status_id">
                    <option value="">{{ __('builder.filter_all_statuses') }}</option>
                    @foreach($statuses as $s)
                        <option value="{{ $s->asset_status_id }}" {{ ($filters['asset_status_id'] ?? '') == $s->asset_status_id ? 'selected' : '' }}>
                            {{ $s->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="filter-group">
                <label>{{ __('builder.filter_building') }}</label>
                <select name="building_id">
                    <option value="">{{ __('builder.filter_all_buildings') }}</option>
                    @foreach($buildings as $b)
                        <option value="{{ $b->building_id }}" {{ ($filters['building_id'] ?? '') == $b->building_id ? 'selected' : '' }}>
                            {{ $b->building_name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="filter-group">
                <label>{{ __('builder.filter_has_status') }}</label>
                <select name="has_status">
                    <option value="">{{ __('builder.filter_all') }}</option>
                    <option value="true"  {{ ($filters['has_status'] ?? '') === 'true'  ? 'selected' : '' }}>{{ __('builder.filter_yes') }}</option>
                    <option value="false" {{ ($filters['has_status'] ?? '') === 'false' ? 'selected' : '' }}>{{ __('builder.filter_no') }}</option>
                </select>
            </div>
            <div class="filter-group">
                <label>{{ __('builder.filter_warranty') }}</label>
                <select name="under_warranty">
                    <option value="">{{ __('builder.filter_all') }}</option>
                    <option value="true"  {{ ($filters['under_warranty'] ?? '') === 'true'  ? 'selected' : '' }}>{{ __('builder.filter_active') }}</option>
                    <option value="false" {{ ($filters['under_warranty'] ?? '') === 'false' ? 'selected' : '' }}>{{ __('builder.filter_expired') }}</option>
                </select>
            </div>
            <div style="display:flex;gap:.5rem;align-items:flex-end;">
                <button type="submit" class="btn btn-primary btn-sm">{{ __('builder.apply') }}</button>
                <a href="{{ route('dashboard.preview', 'assets') }}" class="btn btn-sm btn-outline-secondary">{{ __('builder.reset') }}</a>
            </div>
        </div>
    </form>
    @endif

    {{-- KPI Cards --}}
    @if(!empty($config['kpis']))
    @php
        $assetsKpiOpts = \App\Http\Controllers\DashboardBuilderController::ASSETS_KPI_OPTIONS;
        $currencyKpis  = ['total_value'];
    @endphp
    <div class="kpi-grid" style="grid-template-columns:repeat({{ $config['kpi_cols'] }},minmax(0,1fr));margin-bottom:1rem;">
        @foreach($config['kpis'] as $kpiKey)
            @if(isset($assetsKpiOpts[$kpiKey]))
                @php
                    $opt    = $assetsKpiOpts[$kpiKey];
                    $val    = $kpiValues[$kpiKey] ?? 0;
                    $fmtVal = in_array($kpiKey, $currencyKpis) ? number_format($val, 2) : number_format($val);
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

        @if($config['charts']['monthly']['enabled'] ?? false)
        @php $monthlyType = $config['charts']['monthly']['type']; @endphp
        <div class="card-soft chart-wide">
            <div class="card-title">{{ __('builder.chart_assets_monthly_title') }}</div>
            @if($chartData['monthly']->isEmpty())
                <div class="no-data">{{ __('builder.no_data') }}</div>
            @else
                <div id="chart_monthly"></div>
            @endif
        </div>
        @endif

        @if($config['charts']['by_category']['enabled'] ?? false)
        @php $byCatType = $config['charts']['by_category']['type']; @endphp
        <div class="card-soft">
            <div class="card-title">{{ __('builder.chart_by_category_title') }}</div>
            @if($chartData['by_category']->isEmpty())
                <div class="no-data">{{ __('builder.no_data') }}</div>
            @else
                <div id="chart_by_category"></div>
            @endif
        </div>
        @endif

        @if($config['charts']['by_status']['enabled'] ?? false)
        @php $byStatusType = $config['charts']['by_status']['type']; @endphp
        <div class="card-soft">
            <div class="card-title">{{ __('builder.chart_props_by_status_title') }}</div>
            @if($chartData['by_status']->isEmpty())
                <div class="no-data">{{ __('builder.no_data') }}</div>
            @else
                <div id="chart_by_status"></div>
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

        @if($config['charts']['by_name']['enabled'] ?? false)
        <div class="card-soft">
            <div class="card-title">{{ __('builder.chart_by_asset_name_title') }}</div>
            @if($chartData['by_name']->isEmpty())
                <div class="no-data">{{ __('builder.no_data') }}</div>
            @else
                <div id="chart_by_name"></div>
            @endif
        </div>
        @endif

        @if($config['charts']['by_manufac']['enabled'] ?? false)
        <div class="card-soft">
            <div class="card-title">{{ __('builder.chart_top_manufacturers_title') }}</div>
            @if($chartData['by_manufac']->isEmpty())
                <div class="no-data">{{ __('builder.no_data') }}</div>
            @else
                <div id="chart_by_manufac"></div>
            @endif
        </div>
        @endif

    </div>
    @endif

    {{-- Assets Table --}}
    @if($config['show_table'])
    <div class="card-soft" style="padding:0;overflow:hidden;margin-bottom:1rem;">
        <div style="padding:.75rem 1rem;border-bottom:1px solid #e2e8f0;font-weight:600;font-size:14px;color:#1e293b;">
            {{ __('builder.table_assets') }}
        </div>
        <div style="overflow-x:auto;">
            <table class="bld-table">
                <thead>
                    <tr>
                        <th>{{ __('builder.th_tag') }}</th><th>{{ __('builder.th_name') }}</th><th>{{ __('builder.filter_category') }}</th><th>{{ __('builder.filter_status') }}</th>
                        <th>{{ __('builder.filter_building') }}</th><th>{{ __('builder.th_manufacturer') }}</th><th>{{ __('builder.filter_warranty') }}</th><th class="num">{{ __('builder.th_value_sar') }}</th><th>{{ __('builder.th_created') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $r)
                        <tr>
                            <td><code style="color:#0f766e;font-size:11px;">{{ $r['asset_tag'] ?? '' }}</code></td>
                            <td>{{ $r['asset_name'] ?? '—' }}</td>
                            <td>{{ $r['asset_category'] ?? '—' }}</td>
                            <td>
                                @if(!empty($r['asset_status_name']))
                                    <span class="pill" style="background:#dbeafe;color:#1d4ed8;">{{ $r['asset_status_name'] }}</span>
                                @else
                                    <span class="pill" style="background:#f1f5f9;color:#475569;">{{ __('builder.no_status_pill') }}</span>
                                @endif
                            </td>
                            <td>{{ $r['building_name'] ?? '—' }}</td>
                            <td>{{ $r['manufacturer_name'] ?? '—' }}</td>
                            <td>
                                <span class="pill" style="background:{{ ($r['under_warranty'] ?? false) ? '#d1fae5;color:#047857' : '#f1f5f9;color:#475569' }}">
                                    {{ ($r['under_warranty'] ?? false) ? __('builder.warranty_active') : __('builder.warranty_expired') }}
                                </span>
                            </td>
                            <td class="num">{{ number_format($r['purchase_amount'] ?? 0, 2) }}</td>
                            <td>{{ !empty($r['created_at']) ? \Carbon\Carbon::parse($r['created_at'])->format('Y-m-d') : '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="9" style="text-align:center;color:#94a3b8;padding:2rem;">{{ __('builder.no_assets') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @endif

</div>
@endsection

@section('scripts')
<script>
const PALETTE = ['#14b8a6','#6366f1','#f59e0b','#22c55e','#a855f7','#ec4899','#0ea5e9','#ef4444','#f97316','#10b981'];
const baseOpts = {
    chart:{ fontFamily:'inherit', toolbar:{ show:false }, animations:{ easing:'easeinout', speed:600 }, dir: IS_RTL ? 'rtl' : 'ltr' },
    grid:{ borderColor:'#e2e8f0', strokeDashArray:4 },
    dataLabels:{ enabled:false },
    tooltip:{ theme:'light' }
};

function makePieDonut(el, labels, values, type, height) {
    new ApexCharts(el, {
        ...baseOpts,
        chart:{ ...baseOpts.chart, type: type, height: height || 300 },
        series: values, labels: labels,
        colors: PALETTE.slice(0, labels.length),
        stroke:{ width: type === 'donut' ? 0 : 2 },
        legend:{ position:'bottom', fontSize:'12px' },
        plotOptions: type === 'donut' ? { pie:{ donut:{ size:'68%', labels:{ show:true, total:{ show:true, label:'Total' }}}}} : {},
        dataLabels:{ enabled:true, style:{ fontSize:'11px', fontWeight:600, colors:['#fff'] }},
    }).render();
}

function makeBar(el, labels, values, height, horizontal) {
    new ApexCharts(el, {
        ...baseOpts,
        chart:{ ...baseOpts.chart, type:'bar', height: height || 280 },
        series:[{ name:'Count', data: values }],
        xaxis:{ categories: labels, labels:{ style:{ fontSize:'11px' }, rotate: horizontal ? 0 : -25 }},
        plotOptions:{ bar:{ horizontal: !!horizontal, borderRadius:5, barHeight: horizontal ? '65%' : undefined, columnWidth:'55%', distributed:true }},
        colors: PALETTE, legend:{ show:false },
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
        makeBar(el, labels, values, 300, false);
    } else {
        new ApexCharts(el, {
            ...baseOpts,
            chart:{ ...baseOpts.chart, type: type, height: 300 },
            series:[{ name:'Assets', data: values }],
            xaxis:{ categories: labels },
            stroke:{ curve:'smooth', width: type === 'area' ? 3 : 2 },
            fill: type === 'area' ? { type:'gradient', gradient:{ opacityFrom:.45, opacityTo:.05 }} : {},
            colors:['#14b8a6'],
            markers: type === 'area' ? { size:4, colors:['#fff'], strokeColors:'#14b8a6', strokeWidth:2 } : {},
        }).render();
    }
})();
@endif
@endif

@if(!empty($config['charts']['by_category']['enabled']))
@php $byCatType = $config['charts']['by_category']['type']; @endphp
@if(!$chartData['by_category']->isEmpty())
(function(){
    const el = document.querySelector('#chart_by_category');
    if (!el) return;
    const data   = @json($chartData['by_category']);
    const labels = data.map(r => r.label);
    const values = data.map(r => r.count);
    const type   = '{{ $byCatType }}';
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

@if(!empty($config['charts']['by_building']['enabled']))
@if(!$chartData['by_building']->isEmpty())
(function(){
    const el = document.querySelector('#chart_by_building');
    if (!el) return;
    const data   = @json($chartData['by_building']);
    const labels = data.map(r => r.label);
    const values = data.map(r => r.count);
    makeBar(el, labels, values, Math.max(280, labels.length * 34), true);
})();
@endif
@endif

@if(!empty($config['charts']['by_name']['enabled']))
@if(!$chartData['by_name']->isEmpty())
(function(){
    const el = document.querySelector('#chart_by_name');
    if (!el) return;
    const data   = @json($chartData['by_name']);
    const labels = data.map(r => r.label);
    const values = data.map(r => r.count);
    makeBar(el, labels, values, Math.max(280, labels.length * 34), true);
})();
@endif
@endif

@if(!empty($config['charts']['by_manufac']['enabled']))
@if(!$chartData['by_manufac']->isEmpty())
(function(){
    const el = document.querySelector('#chart_by_manufac');
    if (!el) return;
    const data   = @json($chartData['by_manufac']);
    const labels = data.map(r => r.label);
    const values = data.map(r => r.count);
    makeBar(el, labels, values, Math.max(280, labels.length * 34), true);
})();
@endif
@endif

</script>
@endsection
