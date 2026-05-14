@extends('layouts.app')
@section('title', $config['name'])

@section('styles')
    .page-bg { background: linear-gradient(180deg,#f1f5f9 0%,#e2e8f0 100%); padding: 1.25rem; border-radius: 12px; }
    .card-soft { background:#fff; border-radius:14px; box-shadow: 0 1px 2px rgba(15,23,42,.04), 0 8px 24px -12px rgba(15,23,42,.08); padding:1rem; }
    .gradient-title { background:linear-gradient(90deg,#10b981,#06b6d4,#6366f1); -webkit-background-clip:text; background-clip:text; color:transparent; font-weight:700; }

    /* KPI grid */
    .kpi-grid { display:grid; gap:.75rem; margin-bottom:1rem; }
    .kpi { position:relative; overflow:hidden; padding-left:1.25rem; }
    .kpi::before { content:""; position:absolute; left:0; top:0; bottom:0; width:4px; border-radius:4px 0 0 4px; }
    .kpi-label { font-size:11px; text-transform:uppercase; letter-spacing:.06em; color:#64748b; font-weight:600; }
    .kpi-value { font-size:1.5rem; font-weight:700; color:#0f172a; margin-top:.25rem; }

    /* Charts */
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
    .filter-group select { padding:.4rem .5rem; border:1.5px solid #e2e8f0; border-radius:8px; font-size:13px; background:#f8fafc; outline:none; }
    .filter-group select:focus { border-color:#10b981; box-shadow:0 0 0 2px rgba(16,185,129,.1); }

    /* Tables */
    .bld-table { width:100%; border-collapse:collapse; font-size:13px; }
    .bld-table th { text-align:left; padding:.5rem .65rem; color:#64748b; font-weight:600; font-size:11px; text-transform:uppercase; letter-spacing:.04em; border-bottom:1px solid #e2e8f0; background:#f8fafc; }
    .bld-table td { padding:.5rem .65rem; border-bottom:1px solid #f1f5f9; color:#334155; }
    .bld-table td.num { text-align:right; font-variant-numeric:tabular-nums; }
    .pill { display:inline-block; padding:2px 8px; border-radius:9999px; font-size:11px; font-weight:600; }

    /* No data */
    .no-data { display:flex; align-items:center; justify-content:center; height:200px; color:#94a3b8; font-size:13px; }
    .card-title { font-weight:600; color:#1e293b; margin-bottom:.75rem; font-size:14px; }
    .back-link { font-size:13px; color:#10b981; text-decoration:none; font-weight:600; }
    .back-link:hover { text-decoration:underline; }
@endsection

@section('content')
<div class="page-bg">

    {{-- Header --}}
    <div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:1rem;">
        <div>
            <h2 class="gradient-title" style="font-size:1.75rem;margin:0;">{{ $config['name'] }}</h2>
            <p style="color:#64748b;font-size:.875rem;margin:.25rem 0 0;">{{ __('builder.preview_billing_sub') }}</p>
        </div>
        <a href="{{ route('dashboard.builder', 'billing') }}" class="back-link" style="margin-top:.25rem;">{{ __('builder.edit_dashboard') }}</a>
    </div>

    {{-- Filters --}}
    @if($config['show_filters'])
    <form method="GET" action="{{ route('dashboard.preview', 'billing') }}" class="card-soft" style="margin-bottom:1rem;">
        <div class="filter-bar">
            <div class="filter-group">
                <label>{{ __('builder.filter_contract_type') }}</label>
                <select name="contract_type">
                    <option value="">{{ __('builder.filter_all_types') }}</option>
                    <option value="rent"  {{ ($filters['contract_type'] ?? '') === 'rent'  ? 'selected' : '' }}>{{ __('builder.filter_rent') }}</option>
                    <option value="lease" {{ ($filters['contract_type'] ?? '') === 'lease' ? 'selected' : '' }}>{{ __('builder.filter_lease') }}</option>
                </select>
            </div>
            <div class="filter-group">
                <label>{{ __('builder.filter_ejar_status') }}</label>
                <select name="ejar_sync_status">
                    <option value="">All Statuses</option>
                    @foreach(['synced_successfully','pending_sync','failed_sync','not_synced'] as $s)
                        <option value="{{ $s }}" {{ ($filters['ejar_sync_status'] ?? '') === $s ? 'selected' : '' }}>
                            {{ ucwords(str_replace('_', ' ', $s)) }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div style="display:flex;gap:.5rem;align-items:flex-end;">
                <button type="submit" class="btn btn-primary btn-sm">{{ __('builder.apply') }}</button>
                <a href="{{ route('dashboard.preview', 'billing') }}" class="btn btn-sm btn-outline-secondary">{{ __('builder.reset') }}</a>
            </div>
        </div>
    </form>
    @endif

    {{-- KPI Cards --}}
    @if(!empty($config['kpis']))
    @php $billingKpiOptions = \App\Http\Controllers\DashboardBuilderController::BILLING_KPI_OPTIONS; @endphp
    @php $currencyKpis = ['total_value','security_deposits','late_fees','brokerage_fees','retainer_fees','collected','outstanding','overdue_amount','payment_due']; @endphp
    <div class="kpi-grid" style="grid-template-columns:repeat({{ $config['kpi_cols'] }},minmax(0,1fr));margin-bottom:1rem;">
        @foreach($config['kpis'] as $kpiKey)
            @if(isset($billingKpiOptions[$kpiKey]))
                @php
                    $opt    = $billingKpiOptions[$kpiKey];
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
        <div class="card-soft chart-wide">
            <div class="card-title">{{ __('builder.chart_monthly_billing_title') }}</div>
            @if($chartData['monthly']->isEmpty())
                <div class="no-data">{{ __('builder.no_data') }}</div>
            @else
                <div id="chart_monthly"></div>
            @endif
        </div>
        @endif

        @if($config['charts']['aging']['enabled'] ?? false)
        <div class="card-soft">
            <div class="card-title">{{ __('builder.chart_aging_title') }}</div>
            @if($chartData['aging']->isEmpty())
                <div class="no-data">{{ __('builder.no_data') }}</div>
            @else
                <div id="chart_aging"></div>
            @endif
        </div>
        @endif

        @if($config['charts']['by_type']['enabled'] ?? false)
        <div class="card-soft">
            <div class="card-title">{{ __('builder.chart_by_contract_type_title') }}</div>
            @if($chartData['by_type']->isEmpty())
                <div class="no-data">{{ __('builder.no_data') }}</div>
            @else
                <div id="chart_by_type"></div>
            @endif
        </div>
        @endif

        @if($config['charts']['by_ejar']['enabled'] ?? false)
        <div class="card-soft">
            <div class="card-title">{{ __('builder.chart_ejar_sync_title') }}</div>
            @if($chartData['by_ejar']->isEmpty())
                <div class="no-data">{{ __('builder.no_data') }}</div>
            @else
                <div id="chart_by_ejar"></div>
            @endif
        </div>
        @endif

        @if($config['charts']['by_ptype']['enabled'] ?? false)
        <div class="card-soft">
            <div class="card-title">{{ __('builder.chart_payment_methods_title') }}</div>
            @if($chartData['by_ptype']->isEmpty())
                <div class="no-data">{{ __('builder.no_data') }}</div>
            @else
                <div id="chart_by_ptype"></div>
            @endif
        </div>
        @endif

        @if($config['charts']['top_tenants']['enabled'] ?? false)
        <div class="card-soft chart-wide">
            <div class="card-title">{{ __('builder.chart_top_tenants_title') }}</div>
            @if($chartData['top_tenants']->isEmpty())
                <div class="no-data">{{ __('builder.no_data') }}</div>
            @else
                <div id="chart_top_tenants"></div>
            @endif
        </div>
        @endif

    </div>
    @endif

    {{-- Overdue & Upcoming Tables --}}
    @if($config['show_table'])
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1rem;">

        <div class="card-soft" style="padding:0;overflow:hidden;">
            <div style="padding:.75rem 1rem;border-bottom:1px solid #e2e8f0;font-weight:600;font-size:14px;color:#1e293b;">
                {{ __('builder.table_overdue') }}
            </div>
            <div style="overflow-x:auto;">
                <table class="bld-table">
                    <thead>
                        <tr>
                            <th>{{ __('builder.th_ref') }}</th><th>{{ __('builder.th_contract') }}</th><th>{{ __('builder.th_type') }}</th><th>{{ __('builder.th_tenant') }}</th><th>{{ __('builder.th_due_date') }}</th><th>{{ __('builder.th_days') }}</th><th class="num">{{ __('builder.th_amount') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($overdueRows as $r)
                            <tr>
                                <td><code style="font-size:11px;">{{ $r['payment_ref'] ?? '—' }}</code></td>
                                <td>{{ $r['contract_reference'] ?? '' }}</td>
                                <td>
                                    <span class="pill" style="background:{{ ($r['contract_type']??'')==='rent'?'#dbeafe;color:#1d4ed8':'#ede9fe;color:#6d28d9' }}">
                                        {{ $r['contract_type'] ?? '' }}
                                    </span>
                                </td>
                                <td>{{ $r['tenant_name'] ?? '—' }}</td>
                                <td>{{ $r['payment_due_date'] ?? '' }}</td>
                                <td><span class="pill" style="background:#fee2e2;color:#b91c1c;">{{ $r['days_overdue'] ?? 0 }}d</span></td>
                                <td class="num">{{ number_format($r['amount'] ?? 0, 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="7" style="text-align:center;color:#94a3b8;padding:2rem;">{{ __('builder.no_overdue') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card-soft" style="padding:0;overflow:hidden;">
            <div style="padding:.75rem 1rem;border-bottom:1px solid #e2e8f0;font-weight:600;font-size:14px;color:#1e293b;">
                {{ __('builder.table_upcoming') }}
            </div>
            <div style="overflow-x:auto;">
                <table class="bld-table">
                    <thead>
                        <tr>
                            <th>{{ __('builder.th_ref') }}</th><th>{{ __('builder.th_contract') }}</th><th>{{ __('builder.th_type') }}</th><th>{{ __('builder.th_tenant') }}</th><th>{{ __('builder.th_due_date') }}</th><th class="num">{{ __('builder.th_amount') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($upcomingRows as $r)
                            <tr>
                                <td><code style="font-size:11px;">{{ $r['payment_ref'] ?? '—' }}</code></td>
                                <td>{{ $r['contract_reference'] ?? '' }}</td>
                                <td>
                                    <span class="pill" style="background:{{ ($r['contract_type']??'')==='rent'?'#dbeafe;color:#1d4ed8':'#ede9fe;color:#6d28d9' }}">
                                        {{ $r['contract_type'] ?? '' }}
                                    </span>
                                </td>
                                <td>{{ $r['tenant_name'] ?? '—' }}</td>
                                <td>{{ $r['payment_due_date'] ?? '' }}</td>
                                <td class="num">{{ number_format($r['amount'] ?? 0, 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" style="text-align:center;color:#94a3b8;padding:2rem;">{{ __('builder.no_upcoming') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    </div>
    @endif

</div>
@endsection

@section('scripts')
<script>
const PALETTE = ['#10b981','#6366f1','#f59e0b','#ef4444','#06b6d4','#8b5cf6','#f97316','#3b82f6','#ec4899','#14b8a6'];
const baseOpts = {
    chart:{ fontFamily:'inherit', toolbar:{ show:false }, animations:{ easing:'easeinout', speed:600 }, dir: IS_RTL ? 'rtl' : 'ltr' },
    grid:{ borderColor:'#e2e8f0', strokeDashArray:4 },
    dataLabels:{ enabled:false },
    tooltip:{ theme:'light' }
};
const fmtCurrency = v => new Intl.NumberFormat('en-US',{minimumFractionDigits:2,maximumFractionDigits:2}).format(v);

function makePieDonut(el, labels, values, type, height) {
    new ApexCharts(el, {
        ...baseOpts,
        chart:{ ...baseOpts.chart, type: type, height: height || 280 },
        series: values, labels: labels,
        colors: PALETTE.slice(0, labels.length),
        stroke:{ width: type === 'donut' ? 0 : 2 },
        legend:{ position:'bottom', fontSize:'12px' },
        plotOptions: type === 'donut' ? { pie:{ donut:{ size:'65%', labels:{ show:true, total:{ show:true, label:'Total' }}}}} : {},
        dataLabels:{ enabled:true, style:{ fontSize:'11px', fontWeight:600, colors:['#fff'] }},
    }).render();
}

function makeBar(el, labels, values, height, horizontal, seriesName, tooltipFmt) {
    const opts = {
        ...baseOpts,
        chart:{ ...baseOpts.chart, type:'bar', height: height || 280 },
        series:[{ name: seriesName || 'Count', data: values }],
        xaxis:{ categories: labels, labels:{ style:{ fontSize:'11px' }}},
        plotOptions:{ bar:{ horizontal: !!horizontal, borderRadius:5, barHeight: horizontal ? '65%' : undefined, distributed:true }},
        colors: PALETTE, legend:{ show:false },
    };
    if (tooltipFmt) opts.tooltip = { y:{ formatter: tooltipFmt }};
    new ApexCharts(el, opts).render();
}

@if(!empty($config['charts']['monthly']['enabled']))
@if(!$chartData['monthly']->isEmpty())
(function(){
    const el = document.querySelector('#chart_monthly');
    if (!el) return;
    const data = @json($chartData['monthly']);
    new ApexCharts(el, {
        ...baseOpts,
        chart:{ ...baseOpts.chart, type:'bar', stacked:true, height:300 },
        series:[
            { name:'Collected',   data: data.map(d => d.paid)   },
            { name:'Outstanding', data: data.map(d => d.unpaid) },
        ],
        xaxis:{ categories: data.map(d => d.label) },
        colors:['#10b981','#ef4444'],
        plotOptions:{ bar:{ borderRadius:4, columnWidth:'60%' }},
        legend:{ position:'top' },
        tooltip:{ y:{ formatter: fmtCurrency }},
    }).render();
})();
@endif
@endif

@if(!empty($config['charts']['aging']['enabled']))
@php $agingType = $config['charts']['aging']['type']; @endphp
@if(!$chartData['aging']->isEmpty())
(function(){
    const el = document.querySelector('#chart_aging');
    if (!el) return;
    const data   = @json($chartData['aging']);
    const labels = data.map(r => r.label);
    const values = data.map(r => r.count);
    const type   = '{{ $agingType }}';
    if (type === 'bar') { makeBar(el, labels, values, 280, false); }
    else { makePieDonut(el, labels, values, type, 280); }
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

@if(!empty($config['charts']['by_ptype']['enabled']))
@php $ptypeType = $config['charts']['by_ptype']['type']; @endphp
@if(!$chartData['by_ptype']->isEmpty())
(function(){
    const el = document.querySelector('#chart_by_ptype');
    if (!el) return;
    const data   = @json($chartData['by_ptype']);
    const labels = data.map(r => r.label);
    const values = data.map(r => r.count);
    const type   = '{{ $ptypeType }}';
    if (type === 'bar') { makeBar(el, labels, values, 280, false); }
    else { makePieDonut(el, labels, values, type, 280); }
})();
@endif
@endif

@if(!empty($config['charts']['top_tenants']['enabled']))
@if(!$chartData['top_tenants']->isEmpty())
(function(){
    const el = document.querySelector('#chart_top_tenants');
    if (!el) return;
    const data   = @json($chartData['top_tenants']);
    const labels = data.map(r => r.label);
    const values = data.map(r => r.amount);
    makeBar(el, labels, values, Math.max(280, labels.length * 36), true, 'Outstanding', fmtCurrency);
})();
@endif
@endif

</script>
@endsection
