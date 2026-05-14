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
    .filter-group select, .filter-group input { padding:.4rem .5rem; border:1.5px solid #e2e8f0; border-radius:8px; font-size:13px; background:#f8fafc; outline:none; }
    .filter-group select:focus, .filter-group input:focus { border-color:#6366f1; box-shadow:0 0 0 2px rgba(99,102,241,.1); }

    .bld-table { width:100%; border-collapse:collapse; font-size:13px; }
    .bld-table th { text-align:left; padding:.5rem .65rem; color:#64748b; font-weight:600; font-size:11px; text-transform:uppercase; letter-spacing:.04em; border-bottom:1px solid #e2e8f0; background:#f8fafc; white-space:nowrap; }
    .bld-table td { padding:.5rem .65rem; border-bottom:1px solid #f1f5f9; color:#334155; white-space:nowrap; }
    .bld-table td.num { text-align:right; font-variant-numeric:tabular-nums; }
    .pill { display:inline-block; padding:2px 8px; border-radius:9999px; font-size:11px; font-weight:600; }
    .progress-bar { height:6px; background:#e2e8f0; border-radius:9999px; overflow:hidden; }
    .progress-bar > span { display:block; height:100%; border-radius:9999px; }

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
            <p style="color:#64748b;font-size:.875rem;margin:.25rem 0 0;">{{ __('builder.preview_contracts_sub') }}</p>
        </div>
        <a href="{{ route('dashboard.builder', 'contracts') }}" class="back-link" style="margin-top:.25rem;">{{ __('builder.edit_dashboard') }}</a>
    </div>

    {{-- Filters --}}
    @if($config['show_filters'])
    <form method="GET" action="{{ route('dashboard.preview', 'contracts') }}" class="card-soft" style="margin-bottom:1rem;">
        <div class="filter-bar">
            <div class="filter-group">
                <label>{{ __('builder.filter_status') }}</label>
                <select name="status">
                    <option value="">{{ __('builder.filter_all') }}</option>
                    <option value="1" {{ ($filters['status'] ?? '') === '1' ? 'selected' : '' }}>{{ __('builder.filter_active') }}</option>
                    <option value="0" {{ ($filters['status'] ?? '') === '0' ? 'selected' : '' }}>{{ __('builder.filter_inactive') }}</option>
                </select>
            </div>
            <div class="filter-group">
                <label>{{ __('builder.filter_service_provider_id') }}</label>
                <input type="number" name="service_provider_id" min="1"
                       value="{{ $filters['service_provider_id'] ?? '' }}" placeholder="e.g. 3">
            </div>
            <div class="filter-group">
                <label>{{ __('builder.filter_contract_type_id') }}</label>
                <input type="number" name="contract_type_id" min="1"
                       value="{{ $filters['contract_type_id'] ?? '' }}" placeholder="e.g. 2">
            </div>
            <div style="display:flex;gap:.5rem;align-items:flex-end;">
                <button type="submit" class="btn btn-primary btn-sm">{{ __('builder.apply') }}</button>
                <a href="{{ route('dashboard.preview', 'contracts') }}" class="btn btn-sm btn-outline-secondary">{{ __('builder.reset') }}</a>
            </div>
        </div>
    </form>
    @endif

    {{-- KPI Cards --}}
    @if(!empty($config['kpis']))
    @php
        $contractsKpiOpts = \App\Http\Controllers\DashboardBuilderController::CONTRACTS_KPI_OPTIONS;
        $intKpis  = ['total_contracts', 'active', 'expired', 'subcontracts', 'closed_wo'];
    @endphp
    <div class="kpi-grid" style="grid-template-columns:repeat({{ $config['kpi_cols'] }},minmax(0,1fr));margin-bottom:1rem;">
        @foreach($config['kpis'] as $kpiKey)
            @if(isset($contractsKpiOpts[$kpiKey]))
                @php
                    $opt    = $contractsKpiOpts[$kpiKey];
                    $val    = $kpiValues[$kpiKey] ?? 0;
                    $fmtVal = in_array($kpiKey, $intKpis) ? number_format($val) : number_format($val, 2);
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

        @if($config['charts']['by_type']['enabled'] ?? false)
        @php $byTypeType = $config['charts']['by_type']['type']; @endphp
        <div class="card-soft">
            <div class="card-title">{{ __('builder.chart_value_by_type_title') }}</div>
            @if($chartData['by_type']->isEmpty())
                <div class="no-data">{{ __('builder.no_data') }}</div>
            @else
                <div id="chart_by_type"></div>
            @endif
        </div>
        @endif

        @if($config['charts']['top_sp']['enabled'] ?? false)
        <div class="card-soft">
            <div class="card-title">{{ __('builder.chart_top_sp_title') }}</div>
            @if($chartData['top_sp']->isEmpty())
                <div class="no-data">{{ __('builder.no_data') }}</div>
            @else
                <div id="chart_top_sp"></div>
            @endif
        </div>
        @endif

        @if($config['charts']['top_overdue']['enabled'] ?? false)
        <div class="card-soft chart-wide">
            <div class="card-title">{{ __('builder.chart_top_overdue_con_title') }}</div>
            @if($chartData['top_overdue']->isEmpty())
                <div class="no-data">{{ __('builder.no_data') }}</div>
            @else
                <div id="chart_top_overdue"></div>
            @endif
        </div>
        @endif

    </div>
    @endif

    {{-- Contracts Table --}}
    @if($config['show_table'])
    <div class="card-soft" style="padding:0;overflow:hidden;margin-bottom:1rem;">
        <div style="padding:.75rem 1rem;border-bottom:1px solid #e2e8f0;font-weight:600;font-size:14px;color:#1e293b;">
            {{ __('builder.table_contracts') }}
        </div>
        <div style="overflow-x:auto;">
            <table class="bld-table">
                <thead>
                    <tr>
                        <th>{{ __('builder.th_contract') }}</th><th>{{ __('builder.th_type') }}</th><th>{{ __('builder.th_service_provider') }}</th>
                        <th>{{ __('builder.th_start') }}</th><th>{{ __('builder.th_end') }}</th><th class="num">{{ __('builder.th_value') }}</th>
                        <th style="min-width:130px;">{{ __('builder.th_paid_pct') }}</th><th class="num">{{ __('builder.th_overdue_col') }}</th><th>{{ __('builder.filter_status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $r)
                        @php
                            $value = (float)($r['contract_value'] ?? 0);
                            $paid  = (float)($r['paid_total']     ?? 0);
                            $pct   = $value > 0 ? min(100, round($paid / $value * 100)) : 0;
                        @endphp
                        <tr>
                            <td>
                                <code style="color:#4338ca;font-size:11px;">{{ $r['contract_number'] ?? '' }}</code>
                                @if(!empty($r['is_subcontract']))
                                    <span class="pill" style="background:#ede9fe;color:#6d28d9;margin-left:4px;">{{ __('builder.pill_sub') }}</span>
                                @endif
                            </td>
                            <td>{{ $r['contract_type_name'] ?? '—' }}</td>
                            <td>{{ $r['service_provider_name'] ?? '—' }}</td>
                            <td>{{ $r['start_date'] ?? '' }}</td>
                            <td>{{ $r['end_date'] ?? '' }}</td>
                            <td class="num">{{ number_format($value, 2) }}</td>
                            <td>
                                <div style="display:flex;align-items:center;gap:.5rem;">
                                    <div class="progress-bar" style="flex:1;">
                                        <span style="width:{{ $pct }}%;background:linear-gradient(90deg,#10b981,#22c55e);"></span>
                                    </div>
                                    <span style="font-size:11px;font-weight:600;white-space:nowrap;">{{ $pct }}%</span>
                                </div>
                            </td>
                            <td class="num">
                                @if(!empty($r['overdue_total']) && $r['overdue_total'] > 0)
                                    <span class="pill" style="background:#fee2e2;color:#b91c1c;">
                                        {{ number_format($r['overdue_total'], 0) }}
                                    </span>
                                @else
                                    <span style="color:#94a3b8;">—</span>
                                @endif
                            </td>
                            <td>
                                @if(!empty($r['is_expired']))
                                    <span class="pill" style="background:#f1f5f9;color:#475569;">{{ __('builder.pill_expired') }}</span>
                                @elseif(!empty($r['is_active']))
                                    <span class="pill" style="background:#d1fae5;color:#047857;">{{ __('builder.pill_active') }}</span>
                                @else
                                    <span class="pill" style="background:#fef3c7;color:#b45309;">{{ __('builder.pill_inactive') }}</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" style="text-align:center;color:#94a3b8;padding:2rem;">{{ __('builder.no_contracts') }}</td></tr>
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
const PALETTE  = ['#6366f1','#22c55e','#f59e0b','#0ea5e9','#a855f7','#ec4899','#14b8a6','#f97316','#10b981','#3b82f6'];
const DANGER   = ['#ef4444','#f97316','#f59e0b','#dc2626','#b91c1c','#7f1d1d','#fca5a5','#fed7aa','#fde68a'];
const baseOpts = {
    chart:{ fontFamily:'inherit', toolbar:{ show:false }, animations:{ easing:'easeinout', speed:600 }, dir: IS_RTL ? 'rtl' : 'ltr' },
    grid:{ borderColor:'#e2e8f0', strokeDashArray:4 },
    dataLabels:{ enabled:false },
    tooltip:{ theme:'light', y:{ formatter: v => new Intl.NumberFormat('en-US',{minimumFractionDigits:2,maximumFractionDigits:2}).format(v) }}
};

function makeHBar(el, labels, values, height, palette) {
    new ApexCharts(el, {
        ...baseOpts,
        chart:{ ...baseOpts.chart, type:'bar', height: height || Math.max(280, labels.length * 36) },
        series:[{ name:'Value', data: values }],
        xaxis:{ categories: labels, labels:{ style:{ fontSize:'11px' }}},
        plotOptions:{ bar:{ horizontal:true, borderRadius:5, barHeight:'65%', distributed:true }},
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

@if(!empty($config['charts']['by_type']['enabled']))
@php $byTypeType = $config['charts']['by_type']['type']; @endphp
@if(!$chartData['by_type']->isEmpty())
(function(){
    const el = document.querySelector('#chart_by_type');
    if (!el) return;
    const data   = @json($chartData['by_type']);
    const labels = data.map(r => r.label);
    const values = data.map(r => r.value);
    const type   = '{{ $byTypeType }}';
    if (type === 'bar') { makeHBar(el, labels, values); }
    else { makePieDonut(el, labels, values, type, 300); }
})();
@endif
@endif

@if(!empty($config['charts']['top_sp']['enabled']))
@if(!$chartData['top_sp']->isEmpty())
(function(){
    const el = document.querySelector('#chart_top_sp');
    if (!el) return;
    const data   = @json($chartData['top_sp']);
    const labels = data.map(r => r.label);
    const values = data.map(r => r.value);
    makeHBar(el, labels, values);
})();
@endif
@endif

@if(!empty($config['charts']['top_overdue']['enabled']))
@if(!$chartData['top_overdue']->isEmpty())
(function(){
    const el = document.querySelector('#chart_top_overdue');
    if (!el) return;
    const data   = @json($chartData['top_overdue']);
    const labels = data.map(r => r.label);
    const values = data.map(r => r.value);
    makeHBar(el, labels, values, Math.max(280, labels.length * 36), DANGER);
})();
@endif
@endif

</script>
@endsection
