@extends('layouts.app')
@section('title', $config['name'])

@section('styles')
    .page-bg { background: linear-gradient(180deg,#f1f5f9 0%,#e2e8f0 100%); padding: 1.25rem; border-radius: 12px; }
    .card-soft { background:#fff; border-radius:14px; box-shadow: 0 1px 2px rgba(15,23,42,.04), 0 8px 24px -12px rgba(15,23,42,.08); padding:1rem; }
    .gradient-title { background:linear-gradient(90deg,#6366f1,#22c55e,#f59e0b); -webkit-background-clip:text; background-clip:text; color:transparent; font-weight:700; }

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
    .filter-group select:focus { border-color:#6366f1; box-shadow:0 0 0 2px rgba(99,102,241,.1); }

    .bld-table { width:100%; border-collapse:collapse; font-size:13px; }
    .bld-table th { text-align:left; padding:.5rem .65rem; color:#64748b; font-weight:600; font-size:11px; text-transform:uppercase; letter-spacing:.04em; border-bottom:1px solid #e2e8f0; background:#f8fafc; white-space:nowrap; }
    .bld-table td { padding:.5rem .65rem; border-bottom:1px solid #f1f5f9; color:#334155; white-space:nowrap; }
    .pill { display:inline-block; padding:2px 8px; border-radius:9999px; font-size:11px; font-weight:600; }

    .no-data { display:flex; align-items:center; justify-content:center; height:200px; color:#94a3b8; font-size:13px; }
    .card-title { font-weight:600; color:#1e293b; margin-bottom:.75rem; font-size:14px; }
    .back-link { font-size:13px; color:#6366f1; text-decoration:none; font-weight:600; }
    .back-link:hover { text-decoration:underline; }
@endsection

@section('content')
<div class="page-bg">

    {{-- Header --}}
    <div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:1rem;">
        <div>
            <h2 class="gradient-title" style="font-size:1.75rem;margin:0;">{{ $config['name'] }}</h2>
            <p style="color:#64748b;font-size:.875rem;margin:.25rem 0 0;">{{ __('builder.preview_users_sub') }}</p>
        </div>
        <a href="{{ route('dashboard.builder', 'users') }}" class="back-link" style="margin-top:.25rem;">{{ __('builder.edit_dashboard') }}</a>
    </div>

    {{-- Filters --}}
    @if($config['show_filters'])
    <form method="GET" action="{{ route('dashboard.preview', 'users') }}" class="card-soft" style="margin-bottom:1rem;">
        <div class="filter-bar">
            <div class="filter-group">
                <label>{{ __('builder.filter_user_type') }}</label>
                <select name="user_type">
                    <option value="">{{ __('builder.filter_all_types') }}</option>
                    @foreach(['admin','admin_employee','building_manager','building_manager_employee','sp_admin','supervisor','sp_worker','tenant','team_leader'] as $t)
                        <option value="{{ $t }}" {{ ($filters['user_type'] ?? '') === $t ? 'selected' : '' }}>
                            {{ ucwords(str_replace('_', ' ', $t)) }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="filter-group">
                <label>{{ __('builder.filter_status') }}</label>
                <select name="is_active">
                    <option value="">{{ __('builder.filter_all') }}</option>
                    <option value="true"  {{ ($filters['is_active'] ?? '') === 'true'  ? 'selected' : '' }}>{{ __('builder.filter_active') }}</option>
                    <option value="false" {{ ($filters['is_active'] ?? '') === 'false' ? 'selected' : '' }}>{{ __('builder.filter_inactive') }}</option>
                </select>
            </div>
            <div class="filter-group">
                <label>{{ __('builder.filter_deleted') }}</label>
                <select name="is_deleted">
                    <option value="">{{ __('builder.filter_all') }}</option>
                    <option value="false" {{ ($filters['is_deleted'] ?? '') === 'false' ? 'selected' : '' }}>{{ __('builder.filter_not_deleted') }}</option>
                    <option value="true"  {{ ($filters['is_deleted'] ?? '') === 'true'  ? 'selected' : '' }}>{{ __('builder.filter_deleted') }}</option>
                </select>
            </div>
            <div style="display:flex;gap:.5rem;align-items:flex-end;">
                <button type="submit" class="btn btn-primary btn-sm">{{ __('builder.apply') }}</button>
                <a href="{{ route('dashboard.preview', 'users') }}" class="btn btn-sm btn-outline-secondary">{{ __('builder.reset') }}</a>
            </div>
        </div>
    </form>
    @endif

    {{-- KPI Cards --}}
    @if(!empty($config['kpis']))
    @php $usersKpiOptions = \App\Http\Controllers\DashboardBuilderController::USERS_KPI_OPTIONS; @endphp
    <div class="kpi-grid" style="grid-template-columns:repeat({{ $config['kpi_cols'] }},minmax(0,1fr));margin-bottom:1rem;">
        @foreach($config['kpis'] as $kpiKey)
            @if(isset($usersKpiOptions[$kpiKey]))
                @php $opt = $usersKpiOptions[$kpiKey]; @endphp
                <div class="card-soft kpi" style="--kpi-color:{{ $opt['color'] }};">
                    <style>.kpi[style*="{{ $opt['color'] }}"]:before { background:{{ $opt['color'] }}; }</style>
                    <div class="kpi-label">{{ $opt['label'] }}</div>
                    <div class="kpi-value">{{ number_format($kpiValues[$kpiKey] ?? 0) }}</div>
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
            <div class="card-title">{{ __('builder.chart_onboarding_trend_title') }}</div>
            @if($chartData['monthly']->isEmpty())
                <div class="no-data">{{ __('builder.no_data') }}</div>
            @else
                <div id="chart_monthly"></div>
            @endif
        </div>
        @endif

        @if($config['charts']['by_type']['enabled'] ?? false)
        @php $byTypeType = $config['charts']['by_type']['type']; @endphp
        <div class="card-soft">
            <div class="card-title">{{ __('builder.chart_by_user_type_title') }}</div>
            @if($chartData['by_type']->isEmpty())
                <div class="no-data">{{ __('builder.no_data') }}</div>
            @else
                <div id="chart_by_type"></div>
            @endif
        </div>
        @endif

        @if($config['charts']['by_city']['enabled'] ?? false)
        @php $byCityType = $config['charts']['by_city']['type']; @endphp
        <div class="card-soft">
            <div class="card-title">{{ __('builder.chart_by_city_title') }}</div>
            @if($chartData['by_city']->isEmpty())
                <div class="no-data">{{ __('builder.no_data') }}</div>
            @else
                <div id="chart_by_city"></div>
            @endif
        </div>
        @endif

        @if($config['charts']['by_project']['enabled'] ?? false)
        <div class="card-soft chart-wide">
            <div class="card-title">{{ __('builder.chart_by_project_title') }}</div>
            @if($chartData['by_project']->isEmpty())
                <div class="no-data">{{ __('builder.no_data') }}</div>
            @else
                <div id="chart_by_project"></div>
            @endif
        </div>
        @endif

    </div>
    @endif

    {{-- Users Table --}}
    @if($config['show_table'])
    @php
        $typeColor = [
            'admin'=>['#ede9fe','#6d28d9'],'admin_employee'=>['#e0e7ff','#4338ca'],
            'building_manager'=>['#dbeafe','#1d4ed8'],'building_manager_employee'=>['#cffafe','#0e7490'],
            'sp_admin'=>['#fef3c7','#b45309'],'supervisor'=>['#ffedd5','#c2410c'],
            'sp_worker'=>['#fee2e2','#b91c1c'],'tenant'=>['#d1fae5','#047857'],
            'team_leader'=>['#fce7f3','#be185d'],'super_admin'=>['#ede9fe','#6d28d9'],
        ];
    @endphp
    <div class="card-soft" style="padding:0;overflow:hidden;margin-bottom:1rem;">
        <div style="padding:.75rem 1rem;border-bottom:1px solid #e2e8f0;font-weight:600;font-size:14px;color:#1e293b;">
            {{ __('builder.table_users') }}
        </div>
        <div style="overflow-x:auto;">
            <table class="bld-table">
                <thead>
                    <tr>
                        <th>{{ __('builder.th_name') }}</th><th>{{ __('builder.th_email') }}</th><th>{{ __('builder.th_phone') }}</th><th>{{ __('builder.th_type') }}</th>
                        <th>{{ __('builder.th_city') }}</th><th>{{ __('builder.filter_status') }}</th><th>{{ __('builder.th_created') }}</th><th>{{ __('builder.th_last_login') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $r)
                        @php $tc = $typeColor[$r['user_type'] ?? ''] ?? ['#f1f5f9','#475569']; @endphp
                        <tr>
                            <td><strong>{{ $r['full_name'] ?? '' }}</strong></td>
                            <td><code style="font-size:11px;">{{ $r['email'] ?? '' }}</code></td>
                            <td>{{ $r['phone'] ?? '—' }}</td>
                            <td>
                                <span class="pill" style="background:{{ $tc[0] }};color:{{ $tc[1] }};">
                                    {{ $r['user_type'] ?? '' }}
                                </span>
                            </td>
                            <td>{{ $r['city_name'] ?? '—' }}</td>
                            <td>
                                <span class="pill" style="background:{{ ($r['is_active'] ?? false) ? '#d1fae5;color:#047857' : '#f1f5f9;color:#475569' }}">
                                    {{ ($r['is_active'] ?? false) ? __('builder.pill_active') : __('builder.pill_inactive') }}
                                </span>
                            </td>
                            <td>{{ !empty($r['created_at']) ? \Carbon\Carbon::parse($r['created_at'])->format('Y-m-d') : '—' }}</td>
                            <td>{{ !empty($r['last_login_at']) ? \Carbon\Carbon::parse($r['last_login_at'])->format('Y-m-d H:i') : '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8" style="text-align:center;color:#94a3b8;padding:2rem;">{{ __('builder.no_users') }}</td></tr>
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
const PALETTE = ['#6366f1','#22c55e','#f59e0b','#ec4899','#14b8a6','#8b5cf6','#ef4444','#0ea5e9','#f97316','#10b981'];
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
        chart:{ ...baseOpts.chart, type:'bar', height: height || 300 },
        series:[{ name:'Count', data: values }],
        xaxis:{ categories: labels, labels:{ style:{ fontSize:'11px' }}},
        plotOptions:{ bar:{ horizontal: !!horizontal, borderRadius:5, barHeight: horizontal ? '65%' : undefined, distributed:true }},
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
            series:[{ name:'Users', data: values }],
            xaxis:{ categories: labels },
            stroke:{ curve:'smooth', width: type === 'area' ? 3 : 2 },
            fill: type === 'area' ? { type:'gradient', gradient:{ opacityFrom:.45, opacityTo:.05 }} : {},
            colors:['#6366f1'],
            markers: type === 'area' ? { size:4, colors:['#fff'], strokeColors:'#6366f1', strokeWidth:2 } : {},
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
    if (type === 'bar') { makeBar(el, labels, values, 300, true); }
    else { makePieDonut(el, labels, values, type, 300); }
})();
@endif
@endif

@if(!empty($config['charts']['by_city']['enabled']))
@php $byCityType = $config['charts']['by_city']['type']; @endphp
@if(!$chartData['by_city']->isEmpty())
(function(){
    const el = document.querySelector('#chart_by_city');
    if (!el) return;
    const data   = @json($chartData['by_city']);
    const labels = data.map(r => r.label);
    const values = data.map(r => r.count);
    const type   = '{{ $byCityType }}';
    if (type === 'bar') { makeBar(el, labels, values, Math.max(280, labels.length * 32), true); }
    else { makePieDonut(el, labels, values, type, 300); }
})();
@endif
@endif

@if(!empty($config['charts']['by_project']['enabled']))
@if(!$chartData['by_project']->isEmpty())
(function(){
    const el = document.querySelector('#chart_by_project');
    if (!el) return;
    const data   = @json($chartData['by_project']);
    const labels = data.map(r => r.label);
    const values = data.map(r => r.count);
    makeBar(el, labels, values, Math.max(260, labels.length * 36), true);
})();
@endif
@endif

</script>
@endsection
