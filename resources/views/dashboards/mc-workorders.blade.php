@extends('layouts.app')
@section('title', 'MC Workorders Dashboard')

@section('styles')
    .page-bg { background: linear-gradient(180deg,#f1f5f9 0%,#e2e8f0 100%); padding: 1.25rem; border-radius: 12px; }
    .card-soft { background:#fff; border-radius:14px; box-shadow: 0 1px 2px rgba(15,23,42,.04), 0 8px 24px -12px rgba(15,23,42,.08); padding:1rem; }
    .kpi { position:relative; overflow:hidden; padding-left:1.25rem; }
    .kpi::before { content:""; position:absolute; left:0; top:0; bottom:0; width:4px; }
    .kpi-1::before { background:#6366f1; } .kpi-2::before { background:#0ea5e9; }
    .kpi-3::before { background:#2563eb; } .kpi-4::before { background:#14b8a6; }
    .kpi-5::before { background:#dc2626; } .kpi-6::before { background:#059669; }
    .kpi-7::before { background:#f97316; } .kpi-8::before { background:#eab308; }
    .kpi-label { font-size:11px; text-transform:uppercase; letter-spacing:.06em; color:#64748b; font-weight:600; }
    .kpi-value { font-size:1.5rem; font-weight:700; color:#0f172a; margin-top:.25rem; }
    .grid-cards { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:.75rem; margin-bottom:1rem; }
    .grid-2 { display:grid; grid-template-columns:2fr 1fr; gap:1rem; margin-bottom:1rem; }
    .grid-2-equal { display:grid; grid-template-columns:1fr 1fr; gap:1rem; margin-bottom:1rem; }
    @media (max-width:900px) {
        .grid-cards { grid-template-columns:repeat(2,1fr); }
        .grid-2, .grid-2-equal { grid-template-columns:1fr; }
    }
    .filter-bar { display:flex; flex-wrap:wrap; gap:.75rem; align-items:flex-end; }
    .filter-group { display:flex; flex-direction:column; gap:.2rem; min-width:160px; flex:1; }
    .filter-group label { font-size:11px; text-transform:uppercase; letter-spacing:.05em; color:#64748b; font-weight:600; }
    .filter-group select, .filter-group input { padding:.4rem .5rem; border:1.5px solid #e2e8f0; border-radius:8px; font-size:13px; background:#f8fafc; outline:none; }
    .filter-group select:focus, .filter-group input:focus { border-color:#6366f1; box-shadow:0 0 0 2px rgba(99,102,241,.1); }
    .gradient-title { background:linear-gradient(90deg,#6366f1,#d946ef,#f43f5e); -webkit-background-clip:text; background-clip:text; color:transparent; font-weight:700; }
    .exp-table { width:100%; border-collapse:collapse; font-size:13px; }
    .exp-table th { text-align:left; padding:.55rem .65rem; color:#64748b; font-weight:600; font-size:11px; text-transform:uppercase; letter-spacing:.04em; border-bottom:1px solid #e2e8f0; background:#f8fafc; }
    .exp-table td { padding:.55rem .65rem; border-bottom:1px solid #f1f5f9; color:#334155; }
    .exp-table td.num { text-align:right; font-variant-numeric:tabular-nums; }
@endsection

@section('content')
<div class="page-bg">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;">
        <div>
            <h2 class="gradient-title" style="font-size:1.75rem;margin:0;">MC Workorders</h2>
            <p style="color:#64748b;font-size:.875rem;margin:.25rem 0 0;">Management company work orders overview</p>
        </div>
        <a href="{{ url('/mc-workorders') }}" class="btn btn-sm btn-outline-secondary">Reset filters</a>
    </div>

    {{-- Filters --}}
    <form method="GET" action="{{ url('/mc-workorders') }}" class="card-soft mb-3">
        <div class="filter-bar">
            <div class="filter-group">
                <label>From</label>
                <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}">
            </div>
            <div class="filter-group">
                <label>To</label>
                <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}">
            </div>
            <div class="filter-group">
                <label>User</label>
                <select name="user_id">
                    <option value="">All Users</option>
                    @foreach($userOptions as $u)
                        <option value="{{ $u->id }}" {{ ($filters['user_id'] ?? '') == $u->id ? 'selected' : '' }}>{{ $u->display_name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="filter-group">
                <label>Contract</label>
                <select name="contract_id">
                    <option value="">All Contracts</option>
                    @foreach($contractOptions as $c)
                        <option value="{{ $c->id }}" {{ ($filters['contract_id'] ?? '') == $c->id ? 'selected' : '' }}>{{ $c->display_name }}</option>
                    @endforeach
                </select>
            </div>
            <div style="display:flex;gap:.5rem;align-items:flex-end;">
                <button type="submit" class="btn btn-primary btn-sm">Apply</button>
                <a href="{{ url('/mc-workorders') }}" class="btn btn-sm btn-outline-secondary">Reset</a>
            </div>
        </div>
    </form>

    {{-- Stat Cards Row 1 --}}
    <div class="grid-cards">
        <div class="card-soft kpi kpi-1"><div class="kpi-label">Locations</div><div class="kpi-value">{{ number_format($totals->total_locations) }}</div></div>
        <div class="card-soft kpi kpi-2"><div class="kpi-label">Contracts</div><div class="kpi-value">{{ number_format($totals->total_contracts) }}</div></div>
        <div class="card-soft kpi kpi-3"><div class="kpi-label">Total Work Orders</div><div class="kpi-value">{{ number_format($totals->total_workorders) }}</div></div>
        <div class="card-soft kpi kpi-4"><div class="kpi-label">Total Expenses</div><div class="kpi-value">{{ number_format($totals->total_expenses, 2) }}</div></div>
    </div>

    {{-- Stat Cards Row 2 --}}
    <div class="grid-cards">
        <div class="card-soft kpi kpi-5"><div class="kpi-label">Reactive</div><div class="kpi-value">{{ number_format($totals->total_reactive) }}</div></div>
        <div class="card-soft kpi kpi-6"><div class="kpi-label">Preventive</div><div class="kpi-value">{{ number_format($totals->total_preventive) }}</div></div>
        <div class="card-soft kpi kpi-7"><div class="kpi-label">Late Execution Time</div><div class="kpi-value">{{ number_format($totals->late_execution) }}</div></div>
        <div class="card-soft kpi kpi-8"><div class="kpi-label">Late Response Time</div><div class="kpi-value">-</div></div>
    </div>

    {{-- Line chart + expenses pie --}}
    <div class="grid-2">
        <div class="card-soft">
            <h6 style="font-weight:600;color:#1e293b;margin-bottom:.75rem;">Work Orders by Category</h6>
            <div id="chartCategoryLine"></div>
        </div>
        <div class="card-soft">
            <h6 style="font-weight:600;color:#1e293b;margin-bottom:.75rem;">Total Expenses</h6>
            <div id="chartExpensesPie"></div>
        </div>
    </div>

    {{-- Location names + Status --}}
    <div class="grid-2-equal">
        <div class="card-soft">
            <h6 style="font-weight:600;color:#1e293b;margin-bottom:.75rem;">Location Names</h6>
            <div id="chartLocations"></div>
        </div>
        <div class="card-soft">
            <h6 style="font-weight:600;color:#1e293b;margin-bottom:.75rem;">Work Order Status</h6>
            <div id="chartStatus"></div>
        </div>
    </div>

    {{-- Expenses by category --}}
    <div class="card-soft" style="margin-bottom:1rem;">
        <h6 style="font-weight:600;color:#1e293b;margin-bottom:.75rem;">Expenses by Category</h6>
        <table class="exp-table">
            <thead>
                <tr><th>Category</th><th class="num">Work Orders</th><th class="num">Total Cost</th></tr>
            </thead>
            <tbody>
                @forelse($expensesByCategory as $row)
                    <tr>
                        <td>{{ $row->label }}</td>
                        <td class="num">{{ number_format($row->wo_count) }}</td>
                        <td class="num">{{ number_format($row->total, 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" style="text-align:center;padding:1.5rem;color:#94a3b8;">No expense data.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection

@section('scripts')
<script>
const PALETTE = ['#6366f1','#8b5cf6','#ec4899','#f43f5e','#f97316','#eab308','#22c55e','#14b8a6','#06b6d4','#3b82f6'];
const baseOpts = { chart:{ fontFamily:'inherit', toolbar:{ show:false }, animations:{ easing:'easeinout', speed:600 }}, grid:{ borderColor:'#e2e8f0', strokeDashArray:4 }, dataLabels:{ enabled:false }, tooltip:{ theme:'light' }};

// Category Line Chart
const lineLabels = @json($months);
const lineSeries = @json($categoryLineSeries);

new ApexCharts(document.querySelector('#chartCategoryLine'), {
    ...baseOpts,
    chart:{ ...baseOpts.chart, type:'line', height:300 },
    series: lineSeries.map((s,i) => ({ name:s.name, data:s.data })),
    xaxis:{ categories: lineLabels },
    stroke:{ curve:'smooth', width:2 },
    colors: PALETTE.slice(0, lineSeries.length),
    markers:{ size:3 },
    legend:{ position:'bottom', fontSize:'12px' },
}).render();

// Expenses Pie
const pieLabels = @json($expensesByType->pluck('label'));
const pieValues = @json($expensesByType->pluck('total'));

new ApexCharts(document.querySelector('#chartExpensesPie'), {
    ...baseOpts,
    chart:{ ...baseOpts.chart, type:'pie', height:280 },
    series: pieValues,
    labels: pieLabels,
    colors: PALETTE.slice(0, pieLabels.length),
    legend:{ position:'bottom', fontSize:'12px' },
    dataLabels:{ enabled:true, style:{ fontSize:'11px', fontWeight:600, colors:['#fff'] }},
    tooltip:{ y:{ formatter: v => new Intl.NumberFormat('en-US',{minimumFractionDigits:2,maximumFractionDigits:2}).format(v) }},
}).render();

// Locations Horizontal Bar
const locLabels = @json($locations->pluck('label'));
const locValues = @json($locations->pluck('total'));

new ApexCharts(document.querySelector('#chartLocations'), {
    ...baseOpts,
    chart:{ ...baseOpts.chart, type:'bar', height: Math.max(280, locLabels.length * 28) },
    series:[{ name:'Work Orders', data: locValues }],
    xaxis:{ categories: locLabels },
    plotOptions:{ bar:{ horizontal:true, borderRadius:6, barHeight:'70%', distributed:true }},
    colors: locLabels.map((_,i) => PALETTE[i % PALETTE.length]),
    legend:{ show:false },
}).render();

// Status Doughnut
const statusColors = { 'Open':'#3b82f6','In Progress':'#f59e0b','On Hold':'#94a3b8','Closed':'#22c55e','Deleted':'#ef4444','Re-open':'#6366f1','Warranty':'#ec4899','Scheduled':'#8b5cf6' };
const statusLabels = @json($perStatus->pluck('label'));
const statusValues = @json($perStatus->pluck('total'));
const statusPalette = statusLabels.map(l => statusColors[l] || '#64748b');

new ApexCharts(document.querySelector('#chartStatus'), {
    ...baseOpts,
    chart:{ ...baseOpts.chart, type:'donut', height:280 },
    series: statusValues,
    labels: statusLabels,
    colors: statusPalette,
    stroke:{ width:0 },
    legend:{ position:'right', fontSize:'12px' },
    plotOptions:{ pie:{ donut:{ size:'65%', labels:{ show:true, total:{ show:true, label:'Total' }}}}},
    dataLabels:{ enabled:true, style:{ fontSize:'11px', fontWeight:600, colors:['#fff'] }},
}).render();
</script>
@endsection
