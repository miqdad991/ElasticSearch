@extends('layouts.app')
@section('title', 'Work Orders Dashboard')

@section('styles')
    .page-bg { background: linear-gradient(180deg,#f1f5f9 0%,#e2e8f0 100%); padding: 1.25rem; border-radius: 12px; }
    .card-soft { background:#fff; border-radius:14px; box-shadow: 0 1px 2px rgba(15,23,42,.04), 0 8px 24px -12px rgba(15,23,42,.08); padding:1rem; }
    .kpi { position:relative; overflow:hidden; padding-left:1.25rem; }
    .kpi::before { content:""; position:absolute; left:0; top:0; bottom:0; width:4px; }
    .kpi-1::before { background:#6366f1; } .kpi-2::before { background:#10b981; }
    .kpi-3::before { background:#f59e0b; } .kpi-4::before { background:#06b6d4; }
    .kpi-5::before { background:#8b5cf6; } .kpi-6::before { background:#ec4899; }
    .kpi-7::before { background:#14b8a6; } .kpi-8::before { background:#ef4444; }
    .kpi-9::before { background:#22c55e; } .kpi-10::before { background:#f97316; }
    .kpi-label { font-size:11px; text-transform:uppercase; letter-spacing:.06em; color:#64748b; font-weight:600; }
    .kpi-value { font-size:1.5rem; font-weight:700; color:#0f172a; margin-top:.25rem; }
    .pill { display:inline-block; padding:2px 8px; border-radius:9999px; font-size:11px; font-weight:600; }
    .grid-cards { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:.75rem; }
    .grid-charts { display:grid; grid-template-columns:1fr; gap:1rem; }
    @media (min-width:768px) {
        .grid-cards { grid-template-columns:repeat(5,minmax(0,1fr)); }
        .grid-charts { grid-template-columns:1fr 1fr; }
        .span-2 { grid-column:span 2; }
    }
    .filter-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:.75rem; }
    @media (min-width:768px) { .filter-grid { grid-template-columns:repeat(6,minmax(0,1fr)); } }
    .filter-grid label { font-size:11px; text-transform:uppercase; letter-spacing:.05em; color:#64748b; font-weight:600; }
    .filter-grid select { width:100%; padding:.4rem .5rem; border:1px solid #e2e8f0; border-radius:6px; font-size:13px; }
    .gradient-title { background:linear-gradient(90deg,#6366f1,#d946ef,#f43f5e); -webkit-background-clip:text; background-clip:text; color:transparent; font-weight:700; }
@endsection

@section('content')
<div class="page-bg">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;">
        <div>
            <h2 class="gradient-title" style="font-size:1.75rem;margin:0;">Work Orders</h2>
            <p style="color:#64748b;font-size:.875rem;margin:.25rem 0 0;">Operational performance across all projects</p>
        </div>
        <a href="{{ url('/work-orders') }}" class="btn btn-sm btn-outline-secondary">Reset filters</a>
    </div>

    <form method="get" class="card-soft mb-3">
        <div class="filter-grid">
            @php $opts = [
                'service_type'      => ['hard','soft'],
                'work_order_type'   => ['preventive','reactive'],
                'workorder_journey' => ['submitted','job_execution','job_evaluation','job_approval','finished'],
                'status_code'       => [1,2,3,4,5,6,7,8],
                'priority_id'       => [1,2,3,4],
                'asset_category_id' => [1,2,3,4,5],
            ]; @endphp
            @foreach ($opts as $key => $values)
                <div>
                    <label>{{ str_replace('_',' ',$key) }}</label>
                    <select name="{{ $key }}">
                        <option value="">— any —</option>
                        @foreach ($values as $v)
                            <option value="{{ $v }}" @selected(($filters[$key] ?? null) == $v)>{{ $v }}</option>
                        @endforeach
                    </select>
                </div>
            @endforeach
        </div>
        <button class="btn btn-primary btn-sm mt-3">Apply</button>
    </form>

    <div class="grid-cards mb-3">
        @php $i=0; @endphp
        @foreach ($cards as $label => $value)
            @php $i++; @endphp
            <div class="card-soft kpi kpi-{{ $i }}">
                <div class="kpi-label">{{ $label }}</div>
                <div class="kpi-value">{{ is_numeric($value) ? number_format($value, $label==='Total Cost'?2:0) : $value }}</div>
            </div>
        @endforeach
    </div>

    <div class="grid-charts mb-3">
        <div class="card-soft span-2"><h6>📈 Monthly trend</h6><div id="ch_monthly"></div></div>
        <div class="card-soft"><h6>🛠 By service type</h6><div id="ch_service"></div></div>
        <div class="card-soft"><h6>⚙️ By WO type</h6><div id="ch_wo_type"></div></div>
        <div class="card-soft"><h6>🚦 By journey stage</h6><div id="ch_journey"></div></div>
        <div class="card-soft"><h6>📊 By status</h6><div id="ch_status"></div></div>
        <div class="card-soft"><h6>🔥 By priority</h6><div id="ch_priority"></div></div>
        <div class="card-soft"><h6>🏷 Top asset categories</h6><div id="ch_category"></div></div>
        <div class="card-soft span-2"><h6>🏢 Top buildings</h6><div id="ch_building"></div></div>
    </div>

    <div class="card-soft" style="padding:0;overflow:hidden;">
        <div style="padding:.75rem 1rem;border-bottom:1px solid #e2e8f0;font-weight:600;">Latest 50 work orders</div>
        <div style="overflow-x:auto;">
            <table class="table table-sm mb-0">
                <thead style="background:#f8fafc;color:#64748b;font-size:11px;text-transform:uppercase;">
                <tr>@foreach (['WO #','Created','Service','Type','Category','Priority','Journey','Status','Cost'] as $h)<th>{{ $h }}</th>@endforeach</tr>
                </thead>
                <tbody>
                @php
                    $statusBg = ['Open'=>['#dbeafe','#1d4ed8'],'In Progress'=>['#fef3c7','#b45309'],'On Hold'=>['#f1f5f9','#475569'],'Closed'=>['#d1fae5','#047857'],'Deleted'=>['#fee2e2','#b91c1c'],'Re-open'=>['#ffedd5','#c2410c'],'Warranty'=>['#ede9fe','#6d28d9'],'Scheduled'=>['#cffafe','#0e7490']];
                    $prioBg = ['Low'=>['#f1f5f9','#475569'],'Medium'=>['#dbeafe','#1d4ed8'],'High'=>['#fef3c7','#b45309'],'Critical'=>['#fee2e2','#b91c1c']];
                @endphp
                @foreach ($rows as $r)
                    @php
                        $sb = $statusBg[$r['status_label']??''] ?? ['#f1f5f9','#475569'];
                        $pb = $prioBg[$r['priority_level']??'']  ?? ['#f1f5f9','#475569'];
                    @endphp
                    <tr>
                        <td><code style="color:#4338ca;">{{ $r['wo_number'] ?? '' }}</code></td>
                        <td>{{ \Illuminate\Support\Carbon::parse($r['created_at'])->format('Y-m-d') }}</td>
                        <td><span class="pill" style="background:{{ ($r['service_type']??'')==='hard'?'#ffedd5;color:#c2410c':'#ccfbf1;color:#0f766e' }}">{{ $r['service_type'] ?? '' }}</span></td>
                        <td><span class="pill" style="background:{{ ($r['work_order_type']??'')==='preventive'?'#d1fae5;color:#047857':'#fee2e2;color:#b91c1c' }}">{{ $r['work_order_type'] ?? '' }}</span></td>
                        <td>{{ $r['asset_category'] ?? '' }}</td>
                        <td><span class="pill" style="background:{{ $pb[0] }};color:{{ $pb[1] }};">{{ $r['priority_level'] ?? '' }}</span></td>
                        <td>{{ $r['workorder_journey'] ?? '' }}</td>
                        <td><span class="pill" style="background:{{ $sb[0] }};color:{{ $sb[1] }};">{{ $r['status_label'] ?? '' }}</span></td>
                        <td class="text-right">{{ number_format($r['cost'] ?? 0, 2) }}</td>
                    </tr>
                @endforeach
                @if ($rows->isEmpty())
                    <tr><td colspan="9" class="text-center text-muted py-4">No matching work orders.</td></tr>
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
const PALETTE  = ['#6366f1','#10b981','#f59e0b','#ec4899','#06b6d4','#8b5cf6','#22c55e','#ef4444','#14b8a6','#f97316'];
const GRADIENT = { type:'gradient', gradient:{ shade:'light', type:'horizontal', shadeIntensity:0.4, opacityFrom:1, opacityTo:0.85, stops:[0,100] } };
const baseChart = (extra={}) => ({
    chart:{ fontFamily:'inherit', toolbar:{ show:false }, animations:{ easing:'easeinout', speed:600 }, ...extra.chart },
    grid:{ borderColor:'#e2e8f0', strokeDashArray:4 },
    dataLabels:{ enabled:false }, legend:{ fontSize:'12px' }, tooltip:{ theme:'light' }, ...extra
});
new ApexCharts(document.querySelector('#ch_monthly'), baseChart({
    chart:{ type:'area', height:280 },
    series:[{ name:'Work Orders', data: charts.monthly.map(d=>d.count) }],
    xaxis:{ categories: charts.monthly.map(d=>d.label) },
    stroke:{ curve:'smooth', width:3, colors:['#6366f1'] },
    fill:{ type:'gradient', gradient:{ opacityFrom:.45, opacityTo:.05 } },
    colors:['#6366f1'], markers:{ size:4, colors:['#fff'], strokeColors:'#6366f1', strokeWidth:2 },
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
donut('#ch_service',  charts.by_service,  ['#f97316','#14b8a6']);
donut('#ch_wo_type',  charts.by_wo_type,  ['#10b981','#ef4444']);
verticalBar('#ch_journey',  charts.by_journey);
donut('#ch_status',    charts.by_status);
verticalBar('#ch_priority', charts.by_priority, ['#94a3b8','#3b82f6','#f59e0b','#ef4444']);
horizontalBar('#ch_category', charts.by_category);
horizontalBar('#ch_building', charts.by_building);
</script>
@endsection
