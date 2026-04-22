@extends('layouts.app')
@section('title', 'Properties Dashboard')

@section('styles')
    .page-bg { background: linear-gradient(180deg,#f1f5f9 0%,#e2e8f0 100%); padding: 1.25rem; border-radius: 12px; }
    .card-soft { background:#fff; border-radius:14px; box-shadow: 0 1px 2px rgba(15,23,42,.04), 0 8px 24px -12px rgba(15,23,42,.08); padding:1rem; }
    .kpi { position:relative; overflow:hidden; padding-left:1.25rem; }
    .kpi::before { content:""; position:absolute; left:0; top:0; bottom:0; width:4px; }
    .kpi-1::before { background:#0ea5e9; } .kpi-2::before { background:#10b981; }
    .kpi-3::before { background:#f59e0b; } .kpi-4::before { background:#8b5cf6; }
    .kpi-5::before { background:#22c55e; } .kpi-6::before { background:#6366f1; }
    .kpi-7::before { background:#3b82f6; } .kpi-8::before { background:#ec4899; }
    .kpi-9::before { background:#14b8a6; } .kpi-10::before { background:#f97316; }
    .kpi-11::before { background:#06b6d4; } .kpi-12::before { background:#a855f7; }
    .kpi-13::before { background:#d946ef; } .kpi-14::before { background:#ef4444; }
    .kpi-15::before { background:#eab308; } .kpi-16::before { background:#64748b; }
    .kpi-label { font-size:11px; text-transform:uppercase; letter-spacing:.06em; color:#64748b; font-weight:600; }
    .kpi-value { font-size:1.5rem; font-weight:700; color:#0f172a; margin-top:.25rem; }
    .pill { display:inline-block; padding:2px 8px; border-radius:9999px; font-size:11px; font-weight:600; }
    .grid-cards { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:.75rem; }
    .grid-charts { display:grid; grid-template-columns:1fr; gap:1rem; }
    @media (min-width: 768px) {
        .grid-cards { grid-template-columns:repeat(5,minmax(0,1fr)); }
        .grid-charts { grid-template-columns:1fr 1fr; }
        .span-2 { grid-column: span 2; }
    }
    .row-title { font-size:11px; text-transform:uppercase; letter-spacing:.08em; color:#475569; font-weight:700; margin:.75rem 0 .5rem 0; padding-left:.25rem; }
    .filter-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:.75rem; }
    @media (min-width: 768px) { .filter-grid { grid-template-columns:repeat(5,minmax(0,1fr)); } }
    .filter-grid label { font-size:11px; text-transform:uppercase; letter-spacing:.05em; color:#64748b; font-weight:600; }
    .filter-grid select { width:100%; padding:.4rem .5rem; border:1px solid #e2e8f0; border-radius:6px; font-size:13px; }
    .gradient-title { background:linear-gradient(90deg,#0ea5e9,#10b981,#f59e0b); -webkit-background-clip:text; background-clip:text; color:transparent; font-weight:700; }
@endsection

@section('content')
<div class="page-bg">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;">
        <div>
            <h2 class="gradient-title" style="font-size:1.75rem;margin:0;">Properties</h2>
            <p style="color:#64748b;font-size:.875rem;margin:.25rem 0 0;">Portfolio composition, geography & contract coverage</p>
        </div>
        <a href="{{ url('/properties') }}" class="btn btn-sm btn-outline-secondary">Reset filters</a>
    </div>

    <form method="get" class="card-soft mb-3">
        <div class="filter-grid">
            @php $simple = [
                'property_type' => ['building' => 'Building', 'complex' => 'Complex'],
                'location_type' => ['single_location' => 'Single location', 'multiple_location' => 'Multiple locations'],
                'status'        => [1 => 'Active', 0 => 'Inactive'],
            ]; @endphp
            @foreach ($simple as $key => $values)
                <div>
                    <label>{{ str_replace('_',' ',$key) }}</label>
                    <select name="{{ $key }}">
                        <option value="">— any —</option>
                        @foreach ($values as $val => $lbl)
                            <option value="{{ $val }}" @selected(($filters[$key] ?? null) == $val)>{{ $lbl }}</option>
                        @endforeach
                    </select>
                </div>
            @endforeach

            <div>
                <label>region</label>
                <select name="region_id">
                    <option value="">— any —</option>
                    @foreach ($regions as $r)
                        <option value="{{ $r->region_id }}" @selected(($filters['region_id'] ?? null) == $r->region_id)>{{ $r->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label>city</label>
                <select name="city_id">
                    <option value="">— any —</option>
                    @foreach ($cities as $c)
                        <option value="{{ $c->city_id }}" @selected(($filters['city_id'] ?? null) == $c->city_id)>{{ $c->name_en }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <button class="btn btn-primary btn-sm mt-3">Apply</button>
    </form>

    @php
        $rowGroups = [
            'Properties' => ['Total Properties','Total Buildings','Single Buildings','Complexes','Active Properties'],
            'Contracts'  => ['Total Contracts','Active Contracts','Rent Contracts','Lease Contracts','Total Budget'],
            'Operations' => ['Total Assets','Total Work Orders','Maintenance Requests','Service Providers','Total WO Cost'],
        ];
        $idx = 0;
    @endphp
    @foreach ($rowGroups as $rowTitle => $labels)
        <div class="row-title">{{ $rowTitle }}</div>
        <div class="grid-cards mb-2">
            @foreach ($labels as $label)
                @php $idx++; $value = $cards[$label] ?? 0; @endphp
                <div class="card-soft kpi kpi-{{ $idx }}">
                    <div class="kpi-label">{{ $label }}</div>
                    <div class="kpi-value">
                        {{ is_numeric($value) ? number_format($value, in_array($label,['Total Budget','Total WO Cost'])?2:0) : $value }}
                    </div>
                </div>
            @endforeach
        </div>
    @endforeach

    <div class="grid-charts mb-3">
        <div class="card-soft span-2"><h6>📈 Properties added per month</h6><div id="ch_monthly"></div></div>
        <div class="card-soft"><h6>🏘 By property type</h6><div id="ch_type"></div></div>
        <div class="card-soft"><h6>⚡ Status</h6><div id="ch_status"></div></div>
        <div class="card-soft"><h6>🗺 By region</h6><div id="ch_region"></div></div>
        <div class="card-soft"><h6>🏙 By city</h6><div id="ch_city"></div></div>
        <div class="card-soft span-2"><h6>🏆 Top properties by contract count</h6><div id="ch_top"></div></div>
    </div>

    <div class="card-soft" style="padding:0;overflow:hidden;">
        <div style="padding:.75rem 1rem;border-bottom:1px solid #e2e8f0;font-weight:600;">Latest 50 properties</div>
        <div style="overflow-x:auto;">
            <table class="table table-sm mb-0">
                <thead style="background:#f8fafc;color:#64748b;font-size:11px;text-transform:uppercase;">
                <tr>@foreach (['Name','Tag','Type','Region','City','Buildings','Floors','Units','Status','Created'] as $h)<th>{{ $h }}</th>@endforeach</tr>
                </thead>
                <tbody>
                @foreach ($rows as $r)
                    <tr>
                        <td><strong>{{ $r['property_name'] ?? '' }}</strong></td>
                        <td><code style="color:#0369a1;">{{ $r['property_tag'] ?? '—' }}</code></td>
                        <td><span class="pill" style="background:{{ ($r['property_type']??'')==='complex'?'#ede9fe;color:#6d28d9':'#e0f2fe;color:#0369a1' }}">{{ $r['property_type'] ?? '' }}</span></td>
                        <td>{{ $r['region_name'] ?? '—' }}</td>
                        <td>{{ $r['city_name'] ?? '—' }}</td>
                        <td class="text-right">{{ $r['buildings_count'] ?? 0 }}</td>
                        <td class="text-right">{{ $r['total_floors'] ?? '—' }}</td>
                        <td class="text-right">{{ $r['total_units'] ?? '—' }}</td>
                        <td><span class="pill" style="background:{{ ($r['is_active']??false)?'#d1fae5;color:#047857':'#f1f5f9;color:#475569' }}">{{ ($r['is_active']??false)?'Active':'Inactive' }}</span></td>
                        <td>{{ !empty($r['created_at']) ? \Illuminate\Support\Carbon::parse($r['created_at'])->format('Y-m-d') : '—' }}</td>
                    </tr>
                @endforeach
                @if (empty($rows) || (is_countable($rows) && count($rows) === 0))
                    <tr><td colspan="10" class="text-center text-muted py-4">No matching properties.</td></tr>
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
const PALETTE  = ['#0ea5e9','#10b981','#f59e0b','#8b5cf6','#22c55e','#6366f1','#ec4899','#14b8a6','#f97316','#ef4444'];
const GRADIENT = { type:'gradient', gradient:{ shade:'light', type:'horizontal', shadeIntensity:0.4, opacityFrom:1, opacityTo:0.85, stops:[0,100] } };
const baseChart = (extra={}) => ({
    chart:{ fontFamily:'inherit', toolbar:{ show:false }, animations:{ easing:'easeinout', speed:600 }, ...extra.chart },
    grid:{ borderColor:'#e2e8f0', strokeDashArray:4 },
    dataLabels:{ enabled:false }, legend:{ fontSize:'12px' }, tooltip:{ theme:'light' }, ...extra
});
new ApexCharts(document.querySelector('#ch_monthly'), baseChart({
    chart:{ type:'area', height:280 },
    series:[{ name:'Properties', data: charts.monthly.map(d=>d.count) }],
    xaxis:{ categories: charts.monthly.map(d=>d.label) },
    stroke:{ curve:'smooth', width:3, colors:['#0ea5e9'] },
    fill:{ type:'gradient', gradient:{ opacityFrom:.45, opacityTo:.05 } },
    colors:['#0ea5e9'], markers:{ size:4, colors:['#fff'], strokeColors:'#0ea5e9', strokeWidth:2 },
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
donut('#ch_type',   charts.by_type,   ['#0ea5e9','#8b5cf6']);
donut('#ch_status', charts.by_status, ['#10b981','#94a3b8']);
verticalBar('#ch_region', charts.by_region);
verticalBar('#ch_city',   charts.by_city);
horizontalBar('#ch_top',  charts.top_props);
</script>
@endsection
