@extends('layouts.app')
@section('title', 'MC Following Dashboard')

@section('styles')
    .page-bg { background: linear-gradient(180deg,#f1f5f9 0%,#e2e8f0 100%); padding: 1.25rem; border-radius: 12px; }
    .card-soft { background:#fff; border-radius:14px; box-shadow: 0 1px 2px rgba(15,23,42,.04), 0 8px 24px -12px rgba(15,23,42,.08); padding:1rem; }
    .kpi { position:relative; overflow:hidden; padding-left:1.25rem; }
    .kpi::before { content:""; position:absolute; left:0; top:0; bottom:0; width:4px; }
    .kpi-1::before { background:#6366f1; } .kpi-2::before { background:#22c55e; }
    .kpi-3::before { background:#f59e0b; } .kpi-4::before { background:#0ea5e9; }
    .kpi-label { font-size:11px; text-transform:uppercase; letter-spacing:.06em; color:#64748b; font-weight:600; }
    .kpi-value { font-size:1.5rem; font-weight:700; color:#0f172a; margin-top:.25rem; }
    .grid-cards { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:.75rem; margin-bottom:1rem; }
    .grid-full { display:grid; grid-template-columns:1fr; gap:1rem; margin-bottom:1rem; }
    .grid-2-equal { display:grid; grid-template-columns:1fr 1fr; gap:1rem; margin-bottom:1rem; }
    @media (max-width:900px) {
        .grid-cards { grid-template-columns:repeat(2,1fr); }
        .grid-2-equal { grid-template-columns:1fr; }
    }
    .filter-bar { display:flex; flex-wrap:wrap; gap:.75rem; align-items:flex-end; }
    .filter-group { display:flex; flex-direction:column; gap:.2rem; min-width:160px; flex:1; }
    .filter-group label { font-size:11px; text-transform:uppercase; letter-spacing:.05em; color:#64748b; font-weight:600; }
    .filter-group select, .filter-group input { padding:.4rem .5rem; border:1.5px solid #e2e8f0; border-radius:8px; font-size:13px; background:#f8fafc; outline:none; }
    .filter-group select:focus, .filter-group input:focus { border-color:#6366f1; box-shadow:0 0 0 2px rgba(99,102,241,.1); }
    .gradient-title { background:linear-gradient(90deg,#6366f1,#d946ef,#f43f5e); -webkit-background-clip:text; background-clip:text; color:transparent; font-weight:700; }
    .uc-table { width:100%; border-collapse:collapse; font-size:13px; }
    .uc-table th { text-align:left; padding:.55rem .65rem; color:#64748b; font-weight:600; font-size:11px; text-transform:uppercase; letter-spacing:.04em; border-bottom:1px solid #e2e8f0; background:#f8fafc; white-space:nowrap; }
    .uc-table td { padding:.55rem .65rem; border-bottom:1px solid #f1f5f9; color:#334155; white-space:nowrap; }
    .uc-table td.num { text-align:right; font-variant-numeric:tabular-nums; }
    .uc-table tr:hover td { background:#fafbfe; }
    .pill { display:inline-block; padding:2px 8px; border-radius:9999px; font-size:11px; font-weight:600; }
    .type-pill { display:inline-block; padding:.15rem .5rem; border-radius:6px; font-size:11px; font-weight:500; background:#f1f5f9; color:#475569; }
    .pct-pill { display:inline-block; padding:.15rem .5rem; border-radius:6px; font-size:12px; font-weight:600; }
    .pct-good { background:#dcfce7; color:#15803d; }
    .pct-mid { background:#fef3c7; color:#b45309; }
    .pct-low { background:#fee2e2; color:#dc2626; }
    .table-scroll { overflow-x:auto; }
@endsection

@section('content')
<div class="page-bg">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;">
        <div>
            <h2 class="gradient-title" style="font-size:1.75rem;margin:0;">MC Following</h2>
            <p style="color:#64748b;font-size:.875rem;margin:.25rem 0 0;">Preventive work orders completion tracking</p>
        </div>
        <a href="{{ url('/mc-following') }}" class="btn btn-sm btn-outline-secondary">Reset filters</a>
    </div>

    {{-- Filters --}}
    <form method="GET" action="{{ url('/mc-following') }}" class="card-soft mb-3">
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
                <label>Location</label>
                <select name="location_id">
                    <option value="">All Locations</option>
                    @foreach($locationOptions as $loc)
                        <option value="{{ $loc->id }}" {{ ($filters['location_id'] ?? '') == $loc->id ? 'selected' : '' }}>{{ $loc->building_name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="filter-group">
                <label>Service Provider / Supervisor</label>
                <select name="user_id">
                    <option value="">All Users</option>
                    @foreach($userOptions as $u)
                        <option value="{{ $u->id }}" {{ ($filters['user_id'] ?? '') == $u->id ? 'selected' : '' }}>{{ $u->display_name }} — {{ $u->type_label }}</option>
                    @endforeach
                </select>
            </div>
            <div style="display:flex;gap:.5rem;align-items:flex-end;">
                <button type="submit" class="btn btn-primary btn-sm">Apply</button>
                <a href="{{ url('/mc-following') }}" class="btn btn-sm btn-outline-secondary">Reset</a>
            </div>
        </div>
    </form>

    {{-- Stat Cards --}}
    <div class="grid-cards">
        <div class="card-soft kpi kpi-1"><div class="kpi-label">Locations</div><div class="kpi-value">{{ number_format($totals->total_locations) }}</div></div>
        <div class="card-soft kpi kpi-2"><div class="kpi-label">Released to System (Closed)</div><div class="kpi-value">{{ number_format($totals->total_closed) }}</div></div>
        <div class="card-soft kpi kpi-3"><div class="kpi-label">Not Yet Released</div><div class="kpi-value">{{ number_format($totals->total_not_closed) }}</div></div>
        <div class="card-soft kpi kpi-4"><div class="kpi-label">Completion %</div><div class="kpi-value">{{ number_format($completionPct, 1) }}%</div></div>
    </div>

    {{-- Line chart (full width) --}}
    <div class="grid-full">
        <div class="card-soft">
            <h6 style="font-weight:600;color:#1e293b;margin-bottom:.75rem;">User Completion Status (Work Orders per Status over Time)</h6>
            <div id="chartStatusLine"></div>
        </div>
    </div>

    {{-- Locations + Status pie --}}
    <div class="grid-2-equal">
        <div class="card-soft">
            <h6 style="font-weight:600;color:#1e293b;margin-bottom:.75rem;">Work Orders by Location</h6>
            <div id="chartLocation"></div>
        </div>
        <div class="card-soft">
            <h6 style="font-weight:600;color:#1e293b;margin-bottom:.75rem;">Completion Preventive Scheduled by Status</h6>
            <div id="chartStatusPie"></div>
        </div>
    </div>

    {{-- Per-user completion table --}}
    <div class="card-soft" style="margin-bottom:1rem;">
        <h6 style="font-weight:600;color:#1e293b;margin-bottom:.75rem;">User Completion Status</h6>
        <div class="table-scroll">
            <table class="uc-table">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Role</th>
                        <th class="num">Total</th>
                        @foreach($statusLabels as $code => $label)
                            <th class="num">{{ $label }}</th>
                        @endforeach
                        <th class="num">Completion %</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($perUser as $row)
                        @php $pctClass = $row->completion_pct >= 75 ? 'pct-good' : ($row->completion_pct >= 40 ? 'pct-mid' : 'pct-low'); @endphp
                        <tr>
                            <td>{{ $row->user_name }}</td>
                            <td><span class="type-pill">{{ $row->type_label }}</span></td>
                            <td class="num">{{ number_format($row->total) }}</td>
                            @foreach($statusLabels as $code => $label)
                                <td class="num">{{ number_format($row->by_status[$code] ?? 0) }}</td>
                            @endforeach
                            <td class="num"><span class="pct-pill {{ $pctClass }}">{{ number_format($row->completion_pct, 1) }}%</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="{{ 4 + count($statusLabels) }}" style="text-align:center;padding:1.5rem;color:#94a3b8;">No user completion data.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
const PALETTE = ['#6366f1','#8b5cf6','#ec4899','#f43f5e','#f97316','#eab308','#22c55e','#14b8a6','#06b6d4','#3b82f6'];
const statusColors = { 1:'#3b82f6', 2:'#f59e0b', 3:'#94a3b8', 4:'#22c55e', 5:'#ef4444', 6:'#6366f1', 7:'#ec4899', 8:'#8b5cf6' };
const baseOpts = { chart:{ fontFamily:'inherit', toolbar:{ show:false }, animations:{ easing:'easeinout', speed:600 }}, grid:{ borderColor:'#e2e8f0', strokeDashArray:4 }, dataLabels:{ enabled:false }, tooltip:{ theme:'light' }};

// Line: WOs per status over time
const lineLabels = @json($months);
const lineSeries = @json($lineSeries);

new ApexCharts(document.querySelector('#chartStatusLine'), {
    ...baseOpts,
    chart:{ ...baseOpts.chart, type:'line', height:320 },
    series: lineSeries.map(s => ({ name:s.name, data:s.data })),
    xaxis:{ categories: lineLabels },
    stroke:{ curve:'smooth', width:2 },
    colors: lineSeries.map(s => statusColors[s.status] || '#64748b'),
    markers:{ size:3 },
    legend:{ position:'bottom', fontSize:'12px' },
}).render();

// Location horizontal bar
const locLabels = @json($perLocation->pluck('label'));
const locValues = @json($perLocation->pluck('total'));

new ApexCharts(document.querySelector('#chartLocation'), {
    ...baseOpts,
    chart:{ ...baseOpts.chart, type:'bar', height: Math.max(280, locLabels.length * 28) },
    series:[{ name:'Work Orders', data: locValues }],
    xaxis:{ categories: locLabels },
    plotOptions:{ bar:{ horizontal:true, borderRadius:6, barHeight:'70%', distributed:true }},
    colors: locLabels.map((_,i) => PALETTE[i % PALETTE.length]),
    legend:{ show:false },
}).render();

// Status pie
const pieRows = @json($perStatus);
const pieLabels = pieRows.map(r => r.label);
const pieValues = pieRows.map(r => r.total);
const pieBg = pieRows.map(r => statusColors[r.status] || '#94a3b8');

new ApexCharts(document.querySelector('#chartStatusPie'), {
    ...baseOpts,
    chart:{ ...baseOpts.chart, type:'pie', height:280 },
    series: pieValues,
    labels: pieLabels,
    colors: pieBg,
    stroke:{ width:0 },
    legend:{ position:'bottom', fontSize:'12px' },
    dataLabels:{ enabled:true, style:{ fontSize:'11px', fontWeight:600, colors:['#fff'] }},
}).render();
</script>
@endsection
