@extends('layouts.app')
@section('title', 'Contracts Dashboard')

@section('styles')
    .page-bg { background: linear-gradient(180deg,#f1f5f9 0%,#e2e8f0 100%); padding:1.25rem; border-radius:12px; }
    .card-soft { background:#fff; border-radius:14px; box-shadow:0 1px 2px rgba(15,23,42,.04),0 8px 24px -12px rgba(15,23,42,.08); padding:1rem; }
    .kpi { position:relative; overflow:hidden; padding-left:1.25rem; }
    .kpi::before { content:""; position:absolute; left:0; top:0; bottom:0; width:4px; }
    .row1::before { background:#6366f1; } .row2::before { background:#22c55e; } .row3::before { background:#f59e0b; }
    .kpi-label { font-size:11px; text-transform:uppercase; color:#64748b; font-weight:600; }
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
    .gradient-title { background:linear-gradient(90deg,#6366f1,#22c55e,#f59e0b); -webkit-background-clip:text; background-clip:text; color:transparent; font-weight:700; }
    .progress-bar { height:6px; background:#e2e8f0; border-radius:9999px; overflow:hidden; }
    .progress-bar > span { display:block; height:100%; border-radius:9999px; }
@endsection

@section('content')
<div class="page-bg">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;">
        <div>
            <h2 class="gradient-title" style="font-size:1.75rem;margin:0;">Execution Contracts</h2>
            <p style="color:#64748b;font-size:.875rem;margin:.25rem 0 0;">Service provider spend, payment tracking & WO extras</p>
        </div>
        <a href="{{ url('/contracts') }}" class="btn btn-sm btn-outline-secondary">Reset filters</a>
    </div>

    <form method="get" class="card-soft mb-3">
        <div class="filter-grid">
            @php $opts = [
                'service_provider_id' => [1,2,3,4,5,6],
                'contract_type_id'    => [1,2,3,4,5,6,7,8],
                'status'              => [0,1],
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
        $rowCards = [
            'row1' => ['Total Contracts','Total Value','Average Value','Active'],
            'row2' => ['Scheduled Total','Paid','Pending','Overdue'],
            'row3' => ['Subcontracts','Expired','Closed WOs','WO Extras Total'],
        ];
    @endphp
    @foreach ($rowCards as $cls => $labels)
        <div class="grid-cards mb-3">
            @foreach ($labels as $label)
                <div class="card-soft kpi {{ $cls }}">
                    <div class="kpi-label">{{ $label }}</div>
                    <div class="kpi-value">
                        {{ is_numeric($cards[$label]) ? number_format($cards[$label], in_array($label,['Total Contracts','Active','Subcontracts','Expired','Closed WOs'])?0:2) : $cards[$label] }}
                    </div>
                </div>
            @endforeach
        </div>
    @endforeach

    <div class="grid-charts mb-3">
        <div class="card-soft"><h6>📄 Value by contract type</h6><div id="ch_type"></div></div>
        <div class="card-soft"><h6>🏢 Top service providers by value</h6><div id="ch_sp"></div></div>
        <div class="card-soft span-2"><h6>🚨 Top contracts by overdue amount</h6><div id="ch_overdue"></div></div>
    </div>

    <div class="card-soft" style="padding:0;overflow:hidden;">
        <div style="padding:.75rem 1rem;border-bottom:1px solid #e2e8f0;font-weight:600;">Top 30 contracts by value</div>
        <div style="overflow-x:auto;">
            <table class="table table-sm mb-0">
                <thead style="background:#f8fafc;color:#64748b;font-size:11px;text-transform:uppercase;">
                <tr>@foreach (['Contract','Type','Service Provider','Start','End','Value','Paid %','Overdue','Status'] as $h)<th>{{ $h }}</th>@endforeach</tr>
                </thead>
                <tbody>
                @foreach ($rows as $r)
                    @php
                        $value = (float) ($r['contract_value'] ?? 0);
                        $paid  = (float) ($r['paid_total']     ?? 0);
                        $pct   = $value > 0 ? min(100, round($paid / $value * 100)) : 0;
                    @endphp
                    <tr>
                        <td>
                            <code style="color:#4338ca;">{{ $r['contract_number'] ?? '' }}</code>
                            @if (!empty($r['is_subcontract'])) <span class="pill" style="background:#ede9fe;color:#6d28d9;">sub</span> @endif
                        </td>
                        <td>{{ $r['contract_type_name'] ?? '—' }}</td>
                        <td>{{ $r['service_provider_name'] ?? '—' }}</td>
                        <td>{{ $r['start_date'] ?? '' }}</td>
                        <td>{{ $r['end_date'] ?? '' }}</td>
                        <td class="text-right">{{ number_format($value, 2) }}</td>
                        <td style="min-width:140px;">
                            <div style="display:flex;align-items:center;gap:.5rem;">
                                <div class="progress-bar" style="flex:1;"><span style="width:{{ $pct }}%;background:linear-gradient(90deg,#10b981,#22c55e);"></span></div>
                                <span style="font-size:11px;font-weight:600;">{{ $pct }}%</span>
                            </div>
                        </td>
                        <td class="text-right">
                            @if (!empty($r['overdue_total']) && $r['overdue_total'] > 0)
                                <span class="pill" style="background:#fee2e2;color:#b91c1c;">{{ number_format($r['overdue_total'], 0) }}</span>
                            @else
                                <span style="color:#94a3b8;">—</span>
                            @endif
                        </td>
                        <td>
                            @if (!empty($r['is_expired']))
                                <span class="pill" style="background:#f1f5f9;color:#475569;">Expired</span>
                            @elseif (!empty($r['is_active']))
                                <span class="pill" style="background:#d1fae5;color:#047857;">Active</span>
                            @else
                                <span class="pill" style="background:#fef3c7;color:#b45309;">Inactive</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
                @if ($rows->isEmpty()) <tr><td colspan="9" class="text-center text-muted py-4">No contracts.</td></tr> @endif
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
const charts = @json($charts);
const PALETTE = ['#6366f1','#22c55e','#f59e0b','#ef4444','#06b6d4','#a855f7','#ec4899','#14b8a6','#f97316','#10b981'];
const GRADIENT = { type:'gradient', gradient:{ shade:'light', type:'horizontal', shadeIntensity:0.4, opacityFrom:1, opacityTo:0.85, stops:[0,100] } };
const base = (extra={}) => ({
    chart:{ fontFamily:'inherit', toolbar:{ show:false }, animations:{ easing:'easeinout', speed:600 }, ...extra.chart },
    grid:{ borderColor:'#e2e8f0', strokeDashArray:4 },
    dataLabels:{ enabled:false }, legend:{ fontSize:'12px' }, tooltip:{ theme:'light' }, ...extra
});
const hbar = (id, data, palette) => new ApexCharts(document.querySelector(id), base({
    chart:{ type:'bar', height: Math.max(260, data.length*36) },
    series:[{ name:'Value', data: data.map(d=>d.count) }],
    xaxis:{ categories: data.map(d=>d.label) },
    plotOptions:{ bar:{ horizontal:true, borderRadius:6, barHeight:'70%', distributed:true } },
    colors: data.map((_,i)=>(palette||PALETTE)[i%(palette||PALETTE).length]),
    legend:{ show:false }, fill: GRADIENT,
})).render();
hbar('#ch_type',    charts.by_type);
hbar('#ch_sp',      charts.top_sp);
hbar('#ch_overdue', charts.top_overdue, ['#ef4444','#f97316','#f59e0b','#dc2626','#b91c1c']);
</script>
@endsection
