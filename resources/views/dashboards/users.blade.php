@extends('layouts.app')
@section('title', 'Users Dashboard')

@section('styles')
    .page-bg { background: linear-gradient(180deg,#f1f5f9 0%,#e2e8f0 100%); padding: 1.25rem; border-radius: 12px; }
    .card-soft { background:#fff; border-radius:14px; box-shadow: 0 1px 2px rgba(15,23,42,.04), 0 8px 24px -12px rgba(15,23,42,.08); padding:1rem; }
    .kpi { position:relative; overflow:hidden; padding-left:1.25rem; }
    .kpi::before { content:""; position:absolute; left:0; top:0; bottom:0; width:4px; }
    .kpi-1::before { background:#6366f1; } .kpi-2::before { background:#22c55e; }
    .kpi-3::before { background:#94a3b8; } .kpi-4::before { background:#ef4444; }
    .kpi-label { font-size:11px; text-transform:uppercase; letter-spacing:.06em; color:#64748b; font-weight:600; }
    .kpi-value { font-size:1.5rem; font-weight:700; color:#0f172a; margin-top:.25rem; }
    .pill { display:inline-block; padding:2px 8px; border-radius:9999px; font-size:11px; font-weight:600; }
    .grid-cards { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:.75rem; }
    .grid-charts { display:grid; grid-template-columns:1fr; gap:1rem; }
    @media (min-width:768px) {
        .grid-cards { grid-template-columns:repeat(4,minmax(0,1fr)); }
        .grid-charts { grid-template-columns:1fr 1fr; }
        .span-2 { grid-column:span 2; }
    }
    .filter-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:.75rem; }
    @media (min-width:768px) { .filter-grid { grid-template-columns:repeat(4,minmax(0,1fr)); } }
    .filter-grid label { font-size:11px; text-transform:uppercase; letter-spacing:.05em; color:#64748b; font-weight:600; }
    .filter-grid select { width:100%; padding:.4rem .5rem; border:1px solid #e2e8f0; border-radius:6px; font-size:13px; }
    .gradient-title { background:linear-gradient(90deg,#6366f1,#22c55e,#f59e0b); -webkit-background-clip:text; background-clip:text; color:transparent; font-weight:700; }
@endsection

@section('content')
<div class="page-bg">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;">
        <div>
            <h2 class="gradient-title" style="font-size:1.75rem;margin:0;">Users</h2>
            <p style="color:#64748b;font-size:.875rem;margin:.25rem 0 0;">Headcount, roles, activation & project scope</p>
        </div>
        <a href="{{ url('/users') }}" class="btn btn-sm btn-outline-secondary">Reset filters</a>
    </div>

    <form method="get" class="card-soft mb-3">
        <div class="filter-grid">
            @php $opts = [
                'user_type' => ['admin','admin_employee','building_manager','building_manager_employee','sp_admin','supervisor','sp_worker','tenant','team_leader'],
                'is_active' => ['true','false'],
                'is_deleted'=> ['true','false'],
                'project_id'=> [67, 68],
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
                <div class="kpi-value">{{ number_format($value) }}</div>
            </div>
        @endforeach
    </div>

    <div class="grid-charts mb-3">
        <div class="card-soft span-2"><h6>📈 Users onboarded per month</h6><div id="ch_monthly"></div></div>
        <div class="card-soft"><h6>👥 By user type</h6><div id="ch_type"></div></div>
        <div class="card-soft"><h6>🏙 By city</h6><div id="ch_city"></div></div>
        <div class="card-soft span-2"><h6>📁 By project</h6><div id="ch_project"></div></div>
    </div>

    <div class="card-soft" style="padding:0;overflow:hidden;">
        <div style="padding:.75rem 1rem;border-bottom:1px solid #e2e8f0;font-weight:600;">Latest 50 users</div>
        <div style="overflow-x:auto;">
            <table class="table table-sm mb-0">
                <thead style="background:#f8fafc;color:#64748b;font-size:11px;text-transform:uppercase;">
                <tr>@foreach (['Name','Email','Phone','Type','City','Active','Created','Last login'] as $h)<th>{{ $h }}</th>@endforeach</tr>
                </thead>
                <tbody>
                @php
                    $typeColor = [
                        'admin'=>['#ede9fe','#6d28d9'],'admin_employee'=>['#e0e7ff','#4338ca'],
                        'building_manager'=>['#dbeafe','#1d4ed8'],'building_manager_employee'=>['#cffafe','#0e7490'],
                        'sp_admin'=>['#fef3c7','#b45309'],'supervisor'=>['#ffedd5','#c2410c'],
                        'sp_worker'=>['#fee2e2','#b91c1c'],'tenant'=>['#d1fae5','#047857'],
                        'team_leader'=>['#fce7f3','#be185d'],'super_admin'=>['#ede9fe','#6d28d9'],
                    ];
                @endphp
                @foreach ($rows as $r)
                    @php $tc = $typeColor[$r['user_type'] ?? ''] ?? ['#f1f5f9','#475569']; @endphp
                    <tr>
                        <td><strong>{{ $r['full_name'] ?? '' }}</strong></td>
                        <td><code>{{ $r['email'] ?? '' }}</code></td>
                        <td>{{ $r['phone'] ?? '—' }}</td>
                        <td><span class="pill" style="background:{{ $tc[0] }};color:{{ $tc[1] }};">{{ $r['user_type'] ?? '' }}</span></td>
                        <td>{{ $r['city_name'] ?? '—' }}</td>
                        <td><span class="pill" style="background:{{ ($r['is_active']??false)?'#d1fae5;color:#047857':'#f1f5f9;color:#475569' }}">{{ ($r['is_active']??false)?'Active':'Inactive' }}</span></td>
                        <td>{{ \Illuminate\Support\Carbon::parse($r['created_at'])->format('Y-m-d') }}</td>
                        <td>{{ !empty($r['last_login_at']) ? \Illuminate\Support\Carbon::parse($r['last_login_at'])->format('Y-m-d H:i') : '—' }}</td>
                    </tr>
                @endforeach
                @if ($rows->isEmpty())
                    <tr><td colspan="8" class="text-center text-muted py-4">No matching users.</td></tr>
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
const PALETTE = ['#6366f1','#22c55e','#f59e0b','#ec4899','#14b8a6','#8b5cf6','#ef4444','#0ea5e9','#f97316','#10b981'];
const GRADIENT = { type:'gradient', gradient:{ shade:'light', type:'horizontal', shadeIntensity:0.4, opacityFrom:1, opacityTo:0.85, stops:[0,100] } };
const baseChart = (extra={}) => ({
    chart:{ fontFamily:'inherit', toolbar:{ show:false }, animations:{ easing:'easeinout', speed:600 }, ...extra.chart },
    grid:{ borderColor:'#e2e8f0', strokeDashArray:4 },
    dataLabels:{ enabled:false }, legend:{ fontSize:'12px' }, tooltip:{ theme:'light' }, ...extra
});
new ApexCharts(document.querySelector('#ch_monthly'), baseChart({
    chart:{ type:'area', height:280 },
    series:[{ name:'Users', data: charts.monthly.map(d=>d.count) }],
    xaxis:{ categories: charts.monthly.map(d=>d.label) },
    stroke:{ curve:'smooth', width:3, colors:['#6366f1'] },
    fill:{ type:'gradient', gradient:{ opacityFrom:.45, opacityTo:.05 } },
    colors:['#6366f1'], markers:{ size:4, colors:['#fff'], strokeColors:'#6366f1', strokeWidth:2 },
})).render();
const donut = (id, data, palette) => new ApexCharts(document.querySelector(id), baseChart({
    chart:{ type:'donut', height:280 },
    series: data.map(d=>d.count), labels: data.map(d=>d.label),
    colors: palette || PALETTE, stroke:{ width:0 },
    legend:{ position:'bottom', fontSize:'12px' },
    plotOptions:{ pie:{ donut:{ size:'68%', labels:{ show:true, total:{ show:true, label:'Total' } } } } },
    dataLabels:{ enabled:true, style:{ fontSize:'11px', fontWeight:600, colors:['#fff'] } },
})).render();
const horizontalBar = (id, data) => new ApexCharts(document.querySelector(id), baseChart({
    chart:{ type:'bar', height: Math.max(260, data.length*36) },
    series:[{ name:'Count', data: data.map(d=>d.count) }],
    xaxis:{ categories: data.map(d=>d.label) },
    plotOptions:{ bar:{ horizontal:true, borderRadius:6, barHeight:'70%', distributed:true } },
    colors: data.map((_,i)=>PALETTE[i%PALETTE.length]),
    legend:{ show:false }, fill: GRADIENT,
})).render();
donut('#ch_type',  charts.by_type);
donut('#ch_city',  charts.by_city);
horizontalBar('#ch_project', charts.by_project);
</script>
@endsection
