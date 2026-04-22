@extends('layouts.app')
@section('title', 'Assets Dashboard')

@section('styles')
    .page-bg { background: linear-gradient(180deg,#f1f5f9 0%,#e2e8f0 100%); padding: 1.25rem; border-radius: 12px; }
    .card-soft { background:#fff; border-radius:14px; box-shadow: 0 1px 2px rgba(15,23,42,.04), 0 8px 24px -12px rgba(15,23,42,.08); padding:1rem; }
    .kpi { position:relative; overflow:hidden; padding-left:1.25rem; }
    .kpi::before { content:""; position:absolute; left:0; top:0; bottom:0; width:4px; }
    .kpi-1::before { background:#14b8a6; } .kpi-2::before { background:#6366f1; }
    .kpi-3::before { background:#f59e0b; } .kpi-4::before { background:#22c55e; }
    .kpi-5::before { background:#94a3b8; } .kpi-6::before { background:#a855f7; }
    .kpi-7::before { background:#ef4444; }
    .kpi-label { font-size:11px; text-transform:uppercase; letter-spacing:.06em; color:#64748b; font-weight:600; }
    .kpi-value { font-size:1.5rem; font-weight:700; color:#0f172a; margin-top:.25rem; }
    .pill { display:inline-block; padding:2px 8px; border-radius:9999px; font-size:11px; font-weight:600; }
    .grid-cards { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:.75rem; }
    .grid-charts { display:grid; grid-template-columns:1fr; gap:1rem; }
    @media (min-width:768px) {
        .grid-cards { grid-template-columns:repeat(7,minmax(0,1fr)); }
        .grid-charts { grid-template-columns:1fr 1fr; }
        .span-2 { grid-column:span 2; }
    }
    .filter-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:.75rem; }
    @media (min-width:768px) { .filter-grid { grid-template-columns:repeat(5,minmax(0,1fr)); } }
    .filter-grid label { font-size:11px; text-transform:uppercase; letter-spacing:.05em; color:#64748b; font-weight:600; }
    .filter-grid select { width:100%; padding:.4rem .5rem; border:1px solid #e2e8f0; border-radius:6px; font-size:13px; }
    .gradient-title { background:linear-gradient(90deg,#14b8a6,#6366f1,#f59e0b); -webkit-background-clip:text; background-clip:text; color:transparent; font-weight:700; }
@endsection

@section('content')
<div class="page-bg">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;">
        <div>
            <h2 class="gradient-title" style="font-size:1.75rem;margin:0;">Assets</h2>
            <p style="color:#64748b;font-size:.875rem;margin:.25rem 0 0;">Inventory, warranty & maintenance cost rollup</p>
        </div>
        <a href="{{ url('/assets') }}" class="btn btn-sm btn-outline-secondary">Reset filters</a>
    </div>

    <form method="get" class="card-soft mb-3">
        <div class="filter-grid">
            <div>
                <label>category</label>
                <select name="asset_category_id">
                    <option value="">— any —</option>
                    @foreach ($categories as $c)
                        <option value="{{ $c->asset_category_id }}" @selected(($filters['asset_category_id'] ?? null) == $c->asset_category_id)>{{ $c->asset_category }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label>status</label>
                <select name="asset_status_id">
                    <option value="">— any —</option>
                    @foreach ($statuses as $s)
                        <option value="{{ $s->asset_status_id }}" @selected(($filters['asset_status_id'] ?? null) == $s->asset_status_id)>{{ $s->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label>building</label>
                <select name="building_id">
                    <option value="">— any —</option>
                    @foreach ($buildings as $b)
                        <option value="{{ $b->building_id }}" @selected(($filters['building_id'] ?? null) == $b->building_id)>{{ $b->building_name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label>has status</label>
                <select name="has_status">
                    <option value="">— any —</option>
                    <option value="true"  @selected(($filters['has_status'] ?? null) === 'true')>Yes</option>
                    <option value="false" @selected(($filters['has_status'] ?? null) === 'false')>No</option>
                </select>
            </div>
            <div>
                <label>under warranty</label>
                <select name="under_warranty">
                    <option value="">— any —</option>
                    <option value="true"  @selected(($filters['under_warranty'] ?? null) === 'true')>Yes</option>
                    <option value="false" @selected(($filters['under_warranty'] ?? null) === 'false')>No</option>
                </select>
            </div>
        </div>
        <button class="btn btn-primary btn-sm mt-3">Apply</button>
    </form>

    <div class="grid-cards mb-3">
        @php $i=0; @endphp
        @foreach ($cards as $label => $value)
            @php $i++; @endphp
            <div class="card-soft kpi kpi-{{ $i }}">
                <div class="kpi-label">{{ $label }}</div>
                <div class="kpi-value">{{ is_numeric($value) ? number_format($value, str_contains($label,'Value')?2:0) : $value }}</div>
            </div>
        @endforeach
    </div>

    <div class="grid-charts mb-3">
        <div class="card-soft span-2"><h6>📈 Assets added per month</h6><div id="ch_monthly"></div></div>
        <div class="card-soft"><h6>🏷 By category</h6><div id="ch_category"></div></div>
        <div class="card-soft"><h6>⚡ By status</h6><div id="ch_status"></div></div>
        <div class="card-soft"><h6>🏢 By building</h6><div id="ch_building"></div></div>
        <div class="card-soft"><h6>🔖 By asset name</h6><div id="ch_name"></div></div>
        <div class="card-soft span-2"><h6>🏭 Top manufacturers</h6><div id="ch_manufac"></div></div>
    </div>

    <div class="card-soft" style="padding:0;overflow:hidden;">
        <div style="padding:.75rem 1rem;border-bottom:1px solid #e2e8f0;font-weight:600;">Latest 50 assets</div>
        <div style="overflow-x:auto;">
            <table class="table table-sm mb-0">
                <thead style="background:#f8fafc;color:#64748b;font-size:11px;text-transform:uppercase;">
                <tr>@foreach (['Tag','Name','Category','Status','Building','Manufacturer','Warranty','Value','Created'] as $h)<th>{{ $h }}</th>@endforeach</tr>
                </thead>
                <tbody>
                @foreach ($rows as $r)
                    <tr>
                        <td><code style="color:#0f766e;">{{ $r['asset_tag'] ?? '' }}</code></td>
                        <td>{{ $r['asset_name'] ?? '—' }}</td>
                        <td>{{ $r['asset_category'] ?? '—' }}</td>
                        <td>
                            @if (!empty($r['asset_status_name']))
                                <span class="pill" style="background:#dbeafe;color:#1d4ed8;">{{ $r['asset_status_name'] }}</span>
                            @else
                                <span class="pill" style="background:#f1f5f9;color:#475569;">none</span>
                            @endif
                        </td>
                        <td>{{ $r['building_name'] ?? '—' }}</td>
                        <td>{{ $r['manufacturer_name'] ?? '—' }}</td>
                        <td>
                            @if ($r['under_warranty'] ?? false)
                                <span class="pill" style="background:#d1fae5;color:#047857;">Active</span>
                            @else
                                <span class="pill" style="background:#f1f5f9;color:#475569;">Expired</span>
                            @endif
                        </td>
                        <td class="text-right">{{ number_format($r['purchase_amount'] ?? 0, 2) }}</td>
                        <td>{{ \Illuminate\Support\Carbon::parse($r['created_at'])->format('Y-m-d') }}</td>
                    </tr>
                @endforeach
                @if ($rows->isEmpty())
                    <tr><td colspan="9" class="text-center text-muted py-4">No matching assets.</td></tr>
                @endif
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
const charts = @json($charts);
const PALETTE = ['#14b8a6','#6366f1','#f59e0b','#22c55e','#a855f7','#ec4899','#0ea5e9','#ef4444','#f97316','#10b981'];
const GRADIENT = { type:'gradient', gradient:{ shade:'light', type:'horizontal', shadeIntensity:0.4, opacityFrom:1, opacityTo:0.85, stops:[0,100] } };
const baseChart = (extra={}) => ({
    chart:{ fontFamily:'inherit', toolbar:{ show:false }, animations:{ easing:'easeinout', speed:600 }, ...extra.chart },
    grid:{ borderColor:'#e2e8f0', strokeDashArray:4 },
    dataLabels:{ enabled:false }, legend:{ fontSize:'12px' }, tooltip:{ theme:'light' }, ...extra
});
new ApexCharts(document.querySelector('#ch_monthly'), baseChart({
    chart:{ type:'area', height:280 },
    series:[{ name:'Assets', data: charts.monthly.map(d=>d.count) }],
    xaxis:{ categories: charts.monthly.map(d=>d.label) },
    stroke:{ curve:'smooth', width:3, colors:['#14b8a6'] },
    fill:{ type:'gradient', gradient:{ opacityFrom:.45, opacityTo:.05 } },
    colors:['#14b8a6'], markers:{ size:4, colors:['#fff'], strokeColors:'#14b8a6', strokeWidth:2 },
})).render();
const verticalBar = (id, data, palette) => new ApexCharts(document.querySelector(id), baseChart({
    chart:{ type:'bar', height:260 },
    series:[{ name:'Count', data: data.map(d=>d.count) }],
    xaxis:{ categories: data.map(d=>d.label), labels:{ rotate:-25 } },
    plotOptions:{ bar:{ borderRadius:6, columnWidth:'55%', distributed:true } },
    colors: data.map((_,i)=>(palette||PALETTE)[i%(palette||PALETTE).length]),
    legend:{ show:false }, fill: GRADIENT,
})).render();
const horizontalBar = (id, data) => new ApexCharts(document.querySelector(id), baseChart({
    chart:{ type:'bar', height: Math.max(260, data.length*36) },
    series:[{ name:'Count', data: data.map(d=>d.count) }],
    xaxis:{ categories: data.map(d=>d.label) },
    plotOptions:{ bar:{ horizontal:true, borderRadius:6, barHeight:'70%', distributed:true } },
    colors: data.map((_,i)=>PALETTE[i%PALETTE.length]),
    legend:{ show:false }, fill: GRADIENT,
})).render();
const donut = (id, data, palette) => new ApexCharts(document.querySelector(id), baseChart({
    chart:{ type:'donut', height:280 },
    series: data.map(d=>d.count), labels: data.map(d=>d.label),
    colors: palette || PALETTE, stroke:{ width:0 },
    legend:{ position:'bottom', fontSize:'12px' },
    plotOptions:{ pie:{ donut:{ size:'68%', labels:{ show:true, total:{ show:true, label:'Total' } } } } },
    dataLabels:{ enabled:true, style:{ fontSize:'11px', fontWeight:600, colors:['#fff'] } },
})).render();
verticalBar('#ch_category', charts.by_category);
donut('#ch_status',         charts.by_status);
horizontalBar('#ch_building', charts.by_building);
verticalBar('#ch_name',      charts.by_name);
horizontalBar('#ch_manufac', charts.by_manufac);
</script>
@endsection
