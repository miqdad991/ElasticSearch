@extends('layouts.app')
@section('title', 'Billing Dashboard')

@section('styles')
    .page-bg { background: linear-gradient(180deg,#f1f5f9 0%,#e2e8f0 100%); padding:1.25rem; border-radius:12px; }
    .card-soft { background:#fff; border-radius:14px; box-shadow:0 1px 2px rgba(15,23,42,.04),0 8px 24px -12px rgba(15,23,42,.08); padding:1rem; }
    .kpi { position:relative; overflow:hidden; padding-left:1.25rem; }
    .kpi::before { content:""; position:absolute; left:0; top:0; bottom:0; width:4px; }
    .row1::before { background:#6366f1; } .row2::before { background:#10b981; }
    .row3::before { background:#f59e0b; } .row4::before { background:#ef4444; }
    .kpi-label { font-size:11px; text-transform:uppercase; letter-spacing:.06em; color:#64748b; font-weight:600; }
    .kpi-value { font-size:1.25rem; font-weight:700; color:#0f172a; margin-top:.25rem; }
    .pill { display:inline-block; padding:2px 8px; border-radius:9999px; font-size:11px; font-weight:600; }
    .grid-cards { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:.75rem; }
    @media (min-width:768px) { .grid-cards { grid-template-columns:repeat(4,minmax(0,1fr)); } }
    .grid-charts { display:grid; grid-template-columns:1fr; gap:1rem; }
    @media (min-width:768px) { .grid-charts { grid-template-columns:1fr 1fr; } .span-2 { grid-column:span 2; } }
    .filter-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:.75rem; }
    @media (min-width:768px) { .filter-grid { grid-template-columns:repeat(3,minmax(0,1fr)); } }
    .filter-grid label { font-size:11px; text-transform:uppercase; color:#64748b; font-weight:600; }
    .filter-grid select { width:100%; padding:.4rem .5rem; border:1px solid #e2e8f0; border-radius:6px; font-size:13px; }
    .gradient-title { background:linear-gradient(90deg,#6366f1,#10b981,#ef4444); -webkit-background-clip:text; background-clip:text; color:transparent; font-weight:700; }
@endsection

@section('content')
<div class="page-bg">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;">
        <div>
            <h2 class="gradient-title" style="font-size:1.75rem;margin:0;">Billing &amp; Receivables</h2>
            <p style="color:#64748b;font-size:.875rem;margin:.25rem 0 0;">Lease/rent contracts, installments, aging & collections</p>
        </div>
        <a href="{{ url('/billing') }}" class="btn btn-sm btn-outline-secondary">Reset filters</a>
    </div>

    <form method="get" class="card-soft mb-3">
        <div class="filter-grid">
            @php $opts = [
                'contract_type'    => ['rent','lease'],
                'ejar_sync_status' => ['synced_successfully','pending_sync','failed_sync','not_synced'],
                'project_id'       => [67,68],
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

    @php
        $rows = [
            'row1' => ['Total Contracts','Total Contract Value','Rent','Lease'],
            'row2' => ['Security Deposits','Late Fees','Brokerage Fees','Retainer Fees'],
            'row3' => ['Collected','Outstanding','Overdue Amount','Payment Due (contracts)'],
        ];
    @endphp
    @foreach ($rows as $cls => $labels)
        <div class="grid-cards mb-3">
            @foreach ($labels as $label)
                <div class="card-soft kpi {{ $cls }}">
                    <div class="kpi-label">{{ $label }}</div>
                    <div class="kpi-value">
                        {{ is_numeric($cards[$label]) ? number_format($cards[$label], str_contains($label,'Contracts')||$label==='Rent'||$label==='Lease'?0:2) : $cards[$label] }}
                    </div>
                </div>
            @endforeach
        </div>
    @endforeach

    <div class="grid-charts mb-3">
        <div class="card-soft span-2"><h6>📈 Collections vs Outstanding per month</h6><div id="ch_monthly"></div></div>
        <div class="card-soft"><h6>⏳ Aging buckets</h6><div id="ch_aging"></div></div>
        <div class="card-soft"><h6>📄 Contracts by type</h6><div id="ch_type"></div></div>
        <div class="card-soft"><h6>🔌 Ejar sync status</h6><div id="ch_ejar"></div></div>
        <div class="card-soft"><h6>💳 Payment methods</h6><div id="ch_ptype"></div></div>
        <div class="card-soft span-2"><h6>🏆 Top 10 tenants by outstanding</h6><div id="ch_tenants"></div></div>
    </div>

    <div class="grid-charts">
        <div class="card-soft" style="padding:0;overflow:hidden;">
            <div style="padding:.75rem 1rem;border-bottom:1px solid #e2e8f0;font-weight:600;">Overdue installments</div>
            <div style="overflow-x:auto;">
                <table class="table table-sm mb-0">
                    <thead style="background:#f8fafc;color:#64748b;font-size:11px;text-transform:uppercase;">
                    <tr>@foreach (['Ref','Contract','Type','Tenant','Due','Days','Amount'] as $h)<th>{{ $h }}</th>@endforeach</tr>
                    </thead>
                    <tbody>
                    @foreach ($overdueRows as $r)
                        <tr>
                            <td><code>{{ $r['payment_ref'] ?? '—' }}</code></td>
                            <td>{{ $r['contract_reference'] ?? '' }}</td>
                            <td><span class="pill" style="background:{{ ($r['contract_type']??'')==='rent'?'#dbeafe;color:#1d4ed8':'#ede9fe;color:#6d28d9' }}">{{ $r['contract_type'] ?? '' }}</span></td>
                            <td>{{ $r['tenant_name'] ?? '—' }}</td>
                            <td>{{ $r['payment_due_date'] ?? '' }}</td>
                            <td><span class="pill" style="background:#fee2e2;color:#b91c1c;">{{ $r['days_overdue'] ?? 0 }} d</span></td>
                            <td class="text-right">{{ number_format($r['amount'] ?? 0, 2) }}</td>
                        </tr>
                    @endforeach
                    @if ($overdueRows->isEmpty()) <tr><td colspan="7" class="text-center text-muted py-4">None.</td></tr> @endif
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card-soft" style="padding:0;overflow:hidden;">
            <div style="padding:.75rem 1rem;border-bottom:1px solid #e2e8f0;font-weight:600;">Upcoming installments</div>
            <div style="overflow-x:auto;">
                <table class="table table-sm mb-0">
                    <thead style="background:#f8fafc;color:#64748b;font-size:11px;text-transform:uppercase;">
                    <tr>@foreach (['Ref','Contract','Type','Tenant','Due','Amount'] as $h)<th>{{ $h }}</th>@endforeach</tr>
                    </thead>
                    <tbody>
                    @foreach ($upcomingRows as $r)
                        <tr>
                            <td><code>{{ $r['payment_ref'] ?? '—' }}</code></td>
                            <td>{{ $r['contract_reference'] ?? '' }}</td>
                            <td><span class="pill" style="background:{{ ($r['contract_type']??'')==='rent'?'#dbeafe;color:#1d4ed8':'#ede9fe;color:#6d28d9' }}">{{ $r['contract_type'] ?? '' }}</span></td>
                            <td>{{ $r['tenant_name'] ?? '—' }}</td>
                            <td>{{ $r['payment_due_date'] ?? '' }}</td>
                            <td class="text-right">{{ number_format($r['amount'] ?? 0, 2) }}</td>
                        </tr>
                    @endforeach
                    @if ($upcomingRows->isEmpty()) <tr><td colspan="6" class="text-center text-muted py-4">None.</td></tr> @endif
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
const charts = @json($charts);
const PALETTE = ['#6366f1','#10b981','#f59e0b','#ef4444','#06b6d4','#a855f7','#22c55e','#f97316','#ec4899','#14b8a6'];
const GRADIENT = { type:'gradient', gradient:{ shade:'light', type:'horizontal', shadeIntensity:0.4, opacityFrom:1, opacityTo:0.85, stops:[0,100] } };
const base = (extra={}) => ({
    chart:{ fontFamily:'inherit', toolbar:{ show:false }, animations:{ easing:'easeinout', speed:600 }, ...extra.chart },
    grid:{ borderColor:'#e2e8f0', strokeDashArray:4 },
    dataLabels:{ enabled:false }, legend:{ fontSize:'12px' }, tooltip:{ theme:'light' }, ...extra
});
new ApexCharts(document.querySelector('#ch_monthly'), base({
    chart:{ type:'bar', stacked:true, height:300 },
    series:[
        { name:'Collected',   data: charts.monthly.map(d=>d.paid) },
        { name:'Outstanding', data: charts.monthly.map(d=>d.unpaid) },
    ],
    xaxis:{ categories: charts.monthly.map(d=>d.label) },
    colors:['#10b981','#ef4444'],
    plotOptions:{ bar:{ borderRadius:4, columnWidth:'60%' } },
    legend:{ position:'top' },
})).render();
const donut = (id, data, palette) => new ApexCharts(document.querySelector(id), base({
    chart:{ type:'donut', height:280 },
    series: data.map(d=>d.count), labels: data.map(d=>d.label),
    colors: palette || PALETTE, stroke:{ width:0 },
    legend:{ position:'bottom' },
    plotOptions:{ pie:{ donut:{ size:'65%', labels:{ show:true, total:{ show:true, label:'Total' } } } } },
    dataLabels:{ enabled:true, style:{ fontSize:'11px', fontWeight:600, colors:['#fff'] } },
})).render();
const vbar = (id, data, palette) => new ApexCharts(document.querySelector(id), base({
    chart:{ type:'bar', height:260 },
    series:[{ name:'Value', data: data.map(d=>d.count) }],
    xaxis:{ categories: data.map(d=>d.label), labels:{ rotate:-25 } },
    plotOptions:{ bar:{ borderRadius:6, columnWidth:'55%', distributed:true } },
    colors: data.map((_,i)=>(palette||PALETTE)[i%(palette||PALETTE).length]),
    legend:{ show:false }, fill: GRADIENT,
})).render();
const hbar = (id, data) => new ApexCharts(document.querySelector(id), base({
    chart:{ type:'bar', height: Math.max(260, data.length*36) },
    series:[{ name:'Value', data: data.map(d=>d.count) }],
    xaxis:{ categories: data.map(d=>d.label) },
    plotOptions:{ bar:{ horizontal:true, borderRadius:6, barHeight:'70%', distributed:true } },
    colors: data.map((_,i)=>PALETTE[i%PALETTE.length]),
    legend:{ show:false }, fill: GRADIENT,
})).render();
vbar('#ch_aging',  charts.aging, ['#10b981','#3b82f6','#f59e0b','#ef4444','#7f1d1d','#94a3b8']);
donut('#ch_type',  charts.by_type, ['#3b82f6','#8b5cf6']);
donut('#ch_ejar',  charts.by_ejar);
donut('#ch_ptype', charts.by_ptype);
hbar('#ch_tenants', charts.top_tenants);
</script>
@endsection
