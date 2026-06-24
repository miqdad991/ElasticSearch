@extends('layouts.app')
@section('title', __('depreciation.page_title'))

@section('styles')
    .page-bg { background: linear-gradient(180deg,#f1f5f9 0%,#e2e8f0 100%); padding: 1.25rem; border-radius: 12px; }
    .card-soft { background:#fff; border-radius:14px; box-shadow: 0 1px 2px rgba(15,23,42,.04), 0 8px 24px -12px rgba(15,23,42,.08); padding:1rem; }
    .kpi { position:relative; overflow:hidden; padding-left:1.25rem; }
    .kpi::before { content:""; position:absolute; left:0; top:0; bottom:0; width:4px; background:#2563eb; }
    .kpi-label { font-size:11px; text-transform:uppercase; letter-spacing:.06em; color:#64748b; font-weight:600; }
    .kpi-value { font-size:1.5rem; font-weight:700; color:#0f172a; margin-top:.25rem; }
    .kpi-num { display:inline-flex; align-items:center; justify-content:center; width:22px; height:22px; border-radius:9999px; background:#2563eb; color:#fff; font-size:12px; font-weight:700; margin-right:.4rem; }
    .kpi-sub { font-size:11px; color:#94a3b8; margin-top:.15rem; }
    .grid-cards { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:.75rem; }
    .grid-charts { display:grid; grid-template-columns:1fr; gap:1rem; }
    @media (min-width:768px) {
        .grid-cards { grid-template-columns:repeat(4,minmax(0,1fr)); }
        .grid-charts { grid-template-columns:1fr 1fr; }
    }
    .filter-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:.75rem; }
    @media (min-width:768px) { .filter-grid { grid-template-columns:repeat(4,minmax(0,1fr)); } }
    .filter-grid label { font-size:11px; text-transform:uppercase; letter-spacing:.05em; color:#64748b; font-weight:600; }
    .filter-grid select, .filter-grid input { width:100%; padding:.4rem .5rem; border:1px solid #e2e8f0; border-radius:6px; font-size:13px; }
    .gradient-title { background:linear-gradient(90deg,#2563eb,#0ea5e9,#14b8a6); -webkit-background-clip:text; background-clip:text; color:transparent; font-weight:700; }
    .dep-table { width:100%; border-collapse:collapse; font-size:12px; white-space:nowrap; }
    .dep-table th { text-align:left; padding:.5rem .6rem; color:#64748b; font-weight:600; font-size:10px; text-transform:uppercase; letter-spacing:.03em; border-bottom:1px solid #e2e8f0; background:#f8fafc; position:sticky; top:0; }
    .dep-table td { padding:.45rem .6rem; border-bottom:1px solid #f1f5f9; color:#334155; }
    .dep-table td.num { text-align:right; font-variant-numeric:tabular-nums; }
    .pill { display:inline-block; padding:2px 8px; border-radius:9999px; font-size:11px; font-weight:600; }
    .muted { color:#94a3b8; }
@endsection

@section('content')
@php
    $money = fn ($v) => number_format((float) ($v ?? 0), 2);
    $date  = fn ($v) => $v ? \Illuminate\Support\Carbon::parse($v)->format('Y-m-d') : '—';
@endphp
<div class="page-bg">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;">
        <div>
            <h2 class="gradient-title" style="font-size:1.75rem;margin:0;">{{ __('depreciation.heading') }}</h2>
            <p style="color:#64748b;font-size:.875rem;margin:.25rem 0 0;">{{ __('depreciation.subtitle') }} · {{ __('depreciation.as_of') }} {{ $reportingDate }}</p>
        </div>
        <a href="{{ url('/assets-depreciation') }}" class="btn btn-sm btn-outline-secondary">{{ __('depreciation.reset') }}</a>
    </div>

    <form method="get" class="card-soft mb-3">
        <div class="filter-grid">
            <div>
                <label>{{ __('depreciation.f_property') }}</label>
                <select name="property_id">
                    <option value="">{{ __('depreciation.any') }}</option>
                    @foreach ($options['properties'] as $p)
                        <option value="{{ $p->property_id }}" @selected(($filters['property_id'] ?? null) == $p->property_id)>{{ $p->property_name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label>{{ __('depreciation.f_zone') }}</label>
                <select name="region_id">
                    <option value="">{{ __('depreciation.any') }}</option>
                    @foreach ($options['zones'] as $z)
                        <option value="{{ $z->region_id }}" @selected(($filters['region_id'] ?? null) == $z->region_id)>{{ $z->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label>{{ __('depreciation.f_unit') }}</label>
                <input type="number" name="unit_id" value="{{ $filters['unit_id'] ?? '' }}" placeholder="—">
            </div>
            <div>
                <label>{{ __('depreciation.f_supplier') }}</label>
                <select name="supplier">
                    <option value="">{{ __('depreciation.any') }}</option>
                    @foreach ($options['suppliers'] as $s)
                        <option value="{{ $s }}" @selected(($filters['supplier'] ?? null) === $s)>{{ $s }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label>{{ __('depreciation.f_service_type') }}</label>
                <select name="service_type">
                    <option value="">{{ __('depreciation.any') }}</option>
                    @foreach ($serviceTypes as $t)
                        <option value="{{ $t }}" @selected(($filters['service_type'] ?? null) === $t)>{{ __('depreciation.st_' . $t) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label>{{ __('depreciation.f_method') }}</label>
                <select name="depreciation_method">
                    <option value="">{{ __('depreciation.any') }}</option>
                    @foreach ($options['methods'] as $m)
                        <option value="{{ $m }}" @selected(($filters['depreciation_method'] ?? null) === $m)>{{ $m }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label>{{ __('depreciation.f_status') }}</label>
                <select name="asset_status_id">
                    <option value="">{{ __('depreciation.any') }}</option>
                    @foreach ($options['statuses'] as $st)
                        <option value="{{ $st->asset_status_id }}" @selected(($filters['asset_status_id'] ?? null) == $st->asset_status_id)>{{ $st->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label>{{ __('depreciation.f_date') }}</label>
                <input type="date" name="date" value="{{ $reportingDate }}">
            </div>
        </div>
        <button class="btn btn-primary btn-sm mt-3">{{ __('depreciation.apply') }}</button>
    </form>

    <div class="grid-cards mb-3">
        <div class="card-soft kpi">
            <div class="kpi-label"><span class="kpi-num">1</span>{{ __('depreciation.kpi_purchase') }}</div>
            <div class="kpi-value">{{ $money($kpis['total_purchase']) }}</div>
        </div>
        <div class="card-soft kpi">
            <div class="kpi-label"><span class="kpi-num">2</span>{{ __('depreciation.kpi_nbv') }}</div>
            <div class="kpi-value">{{ $money($kpis['total_nbv']) }}</div>
            <div class="kpi-sub">{{ __('depreciation.as_of') }} {{ $reportingDate }}</div>
        </div>
        <div class="card-soft kpi">
            <div class="kpi-label"><span class="kpi-num">3</span>{{ __('depreciation.kpi_accum') }}</div>
            <div class="kpi-value">{{ $money($kpis['total_accum']) }}</div>
        </div>
        <div class="card-soft kpi">
            <div class="kpi-label"><span class="kpi-num">4</span>{{ __('depreciation.kpi_avg_life') }}</div>
            <div class="kpi-value">{{ $kpis['avg_useful_life'] }} <span style="font-size:.9rem;color:#64748b;">{{ __('depreciation.kpi_years') }}</span></div>
        </div>
    </div>

    <div class="grid-charts mb-3">
        <div class="card-soft"><h6>{{ __('depreciation.ch_by_property') }}</h6><div id="ch_by_property"></div></div>
        <div class="card-soft"><h6>{{ __('depreciation.ch_remaining_life') }}</h6><div id="ch_remaining_life"></div></div>
    </div>

    <div class="card-soft" style="padding:0;overflow:hidden;">
        <div style="padding:.75rem 1rem;border-bottom:1px solid #e2e8f0;font-weight:600;">{{ __('depreciation.tbl_title') }}</div>
        <div style="overflow-x:auto;max-height:600px;">
            <table class="dep-table">
                <thead>
                <tr>
                    <th>{{ __('depreciation.col_property') }}</th>
                    <th>{{ __('depreciation.col_zone') }}</th>
                    <th>{{ __('depreciation.col_unit') }}</th>
                    <th>{{ __('depreciation.col_asset_name') }}</th>
                    <th>{{ __('depreciation.col_service_type') }}</th>
                    <th>{{ __('depreciation.col_asset_number') }}</th>
                    <th>{{ __('depreciation.col_asset_tag') }}</th>
                    <th>{{ __('depreciation.col_supplier') }}</th>
                    <th class="num">{{ __('depreciation.col_purchase') }}</th>
                    <th>{{ __('depreciation.col_install_date') }}</th>
                    <th>{{ __('depreciation.col_dep_period') }}</th>
                    <th class="num">{{ __('depreciation.col_useful_life') }}</th>
                    <th>{{ __('depreciation.col_method') }}</th>
                    <th class="num">{{ __('depreciation.col_rate') }}</th>
                    <th class="num">{{ __('depreciation.col_residual') }}</th>
                    <th class="num">{{ __('depreciation.col_annual') }}</th>
                    <th class="num">{{ __('depreciation.col_accum') }}</th>
                    <th class="num">{{ __('depreciation.col_nbv') }}</th>
                    <th>{{ __('depreciation.col_last_maint') }}</th>
                    <th class="num">{{ __('depreciation.col_maint_ytd') }}</th>
                    <th class="num">{{ __('depreciation.col_wo_count') }}</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($rows as $r)
                    <tr>
                        <td>{{ $r->property_name ?? '—' }}</td>
                        <td>{{ $r->zone_name ?? '—' }}</td>
                        <td>{{ $r->unit_id ?? '—' }}</td>
                        <td>{{ $r->asset_name ?? '—' }}</td>
                        <td>{{ $r->service_type ?? '—' }}</td>
                        <td>{{ $r->asset_number ?? '—' }}</td>
                        <td><code style="color:#0f766e;">{{ $r->asset_tag ?? '' }}</code></td>
                        <td>{{ $r->supplier_name ?? '—' }}</td>
                        <td class="num">{{ $money($r->purchase_price) }}</td>
                        <td>{{ $date($r->installation_date) }}</td>
                        <td>{{ $date($r->depreciation_start_date) }} → {{ $date($r->depreciation_end_date) }}</td>
                        <td class="num">{{ $r->useful_life_years !== null ? rtrim(rtrim(number_format($r->useful_life_years, 2), '0'), '.') : '—' }}</td>
                        <td>{{ $r->depreciation_method ?? '—' }}</td>
                        <td class="num">{{ $r->depreciation_rate !== null ? number_format($r->depreciation_rate, 2) : '—' }}</td>
                        <td class="num">{{ $money($r->residual_value) }}</td>
                        <td class="num">{{ $money($r->annual_dep) }}</td>
                        <td class="num">{{ $money($r->accumulated_dep) }}</td>
                        <td class="num" style="font-weight:600;">{{ $money($r->nbv) }}</td>
                        <td class="muted">{{ __('depreciation.pending') }}</td>
                        <td class="num muted">—</td>
                        <td class="num muted">—</td>
                    </tr>
                @empty
                    <tr><td colspan="21" class="text-center muted py-4">{{ __('depreciation.empty') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
const byProperty = @json($charts['by_property']);
const remaining  = @json($charts['remaining_life']);
const baseChart = (extra={}) => ({
    chart:{ fontFamily:'inherit', toolbar:{ show:false }, animations:{ easing:'easeinout', speed:600 }, dir: IS_RTL ? 'rtl' : 'ltr', ...extra.chart },
    grid:{ borderColor:'#e2e8f0', strokeDashArray:4 },
    dataLabels:{ enabled:false }, legend:{ fontSize:'12px' }, tooltip:{ theme:'light' }, ...extra
});

// 1) Depreciation Expense by Property — current year vs accumulated.
new ApexCharts(document.querySelector('#ch_by_property'), baseChart({
    chart:{ type:'bar', height:320 },
    series:[
        { name:'{{ __('depreciation.js_current') }}', data: byProperty.map(d=>d.current) },
        { name:'{{ __('depreciation.js_accum') }}',   data: byProperty.map(d=>d.accumulated) },
    ],
    xaxis:{ categories: byProperty.map(d=>d.label), labels:{ rotate:-25, style:{ fontSize:'11px' } } },
    yaxis:{ labels:{ formatter:v=>Intl.NumberFormat(undefined,{notation:'compact'}).format(v) } },
    colors:['#0ea5e9','#2563eb'],
    plotOptions:{ bar:{ borderRadius:4, columnWidth:'60%' } },
    legend:{ show:true, position:'top' },
    tooltip:{ shared:false, intersect:true, custom: ({ seriesIndex, dataPointIndex }) => {
        const d = byProperty[dataPointIndex] || {};
        const money = v => Intl.NumberFormat(undefined,{minimumFractionDigits:2,maximumFractionDigits:2}).format(v||0);
        return '<div style="padding:8px 12px;font-size:12px;">'
            + '<div style="font-weight:700;margin-bottom:4px;">'+ (d.label||'') +'</div>'
            + '<div><span style="color:#0ea5e9;">●</span> {{ __('depreciation.js_current') }}: <b>'+ money(d.current) +' SAR</b></div>'
            + '<div><span style="color:#2563eb;">●</span> {{ __('depreciation.js_accum') }}: <b>'+ money(d.accumulated) +' SAR</b></div>'
            + '<div style="margin-top:4px;color:#64748b;">{{ __('depreciation.js_assets') }}: <b>'+ (d.count||0) +'</b></div>'
            + '</div>';
    } },
})).render();

// 2) Assets by Remaining Useful Life — bubble per bucket.
new ApexCharts(document.querySelector('#ch_remaining_life'), baseChart({
    chart:{ type:'bubble', height:320 },
    series:[{ name:'{{ __('depreciation.js_assets') }}', data: remaining.map((d,i)=>({ x:i+1, y:d.count, z:d.count })) }],
    xaxis:{ tickAmount:4, min:0, max:5, labels:{ formatter:(v)=>{ const d=remaining[Math.round(v)-1]; return d?d.label:''; } } },
    yaxis:{ min:0, labels:{ formatter:v=>Math.round(v) } },
    fill:{ opacity:0.8 }, colors:['#2563eb'],
    plotOptions:{ bubble:{ minBubbleRadius:12, maxBubbleRadius:48 } },
    tooltip:{ custom: ({ dataPointIndex }) => {
        const d = remaining[dataPointIndex] || {};
        return '<div style="padding:8px 12px;font-size:12px;">'
            + '<div style="font-weight:700;margin-bottom:4px;">'+ (d.label||'') +' {{ __('depreciation.kpi_years') }}</div>'
            + '<div>{{ __('depreciation.js_assets') }}: <b>'+ (d.count||0) +'</b></div>'
            + '<div style="color:#64748b;">{{ __('depreciation.js_share') }}: <b>'+ (d.percent||0) +'%</b></div>'
            + '</div>';
    } },
})).render();
</script>
@endsection
