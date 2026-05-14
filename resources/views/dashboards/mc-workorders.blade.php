@extends('layouts.app')
@section('title', __('mc_wo.page_title'))

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

    /* Map */
    .map-card { padding:0; overflow:hidden; }
    .map-card-header { padding:1rem 1.25rem; border-bottom:1px solid #e2e8f0; }
    .map-card-header h6 { font-weight:600; color:#1e293b; margin:0; }
    #map_container { width:100%; height:520px; }
    .map-pin-div { cursor:pointer; box-shadow:0 4px 12px rgba(15,23,42,.15); white-space:nowrap; }
    .map-pin-div .pin-icon { width:32px; height:32px; border-radius:50%; background:#6366f1; display:flex; align-items:center; justify-content:center; }
    .map-pin-div .pin-icon img { width:18px; filter:brightness(0) invert(1); }
    .map-pin-div .pin-count { font-weight:700; color:#1e293b; font-size:14px; }
    .map-fs-overlay { position:fixed; inset:0; background:rgba(15,23,42,.55); z-index:9999; display:flex; align-items:center; justify-content:center; padding:24px; }
    .map-fs-inner { background:#fff; border-radius:14px; max-width:760px; width:100%; max-height:90vh; overflow:auto; padding:1.25rem 1.5rem; position:relative; }
    .map-fs-close { position:absolute; top:12px; right:12px; width:32px; height:32px; border-radius:50%; border:1px solid #e2e8f0; background:#fff; cursor:pointer; font-size:16px; line-height:1; }
    .map-popup h4 { font-size:18px; font-weight:700; color:#0f172a; margin:0 0 .5rem; }
    .map-popup .meta { display:flex; flex-wrap:wrap; gap:.75rem; margin:.5rem 0 1rem; color:#475569; font-size:13px; }
    .map-popup .meta span { background:#f1f5f9; padding:.25rem .6rem; border-radius:999px; }
    .map-popup .kpi-row { display:grid; grid-template-columns:repeat(3,1fr); gap:.5rem; margin:.75rem 0; }
    .map-popup .kpi-row .kpi-box { background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:.5rem .75rem; }
    .map-popup .kpi-row .kpi-box .v { font-size:18px; font-weight:700; color:#0f172a; }
    .map-popup .kpi-row .kpi-box .l { font-size:11px; color:#64748b; text-transform:uppercase; letter-spacing:.04em; }
    .map-popup .tabs { display:flex; gap:.25rem; border-bottom:1px solid #e2e8f0; margin:1rem 0 .75rem; }
    .map-popup .tabs button { background:none; border:none; padding:.5rem .75rem; cursor:pointer; font-size:13px; color:#64748b; border-bottom:2px solid transparent; }
    .map-popup .tabs button.active { color:#6366f1; border-bottom-color:#6366f1; font-weight:600; }
    .map-popup .tab-panel { display:none; }
    .map-popup .tab-panel.active { display:block; }
    .map-popup .cat-row { display:flex; justify-content:space-between; align-items:center; margin:.5rem 0; font-size:13px; }
    .map-popup .cat-row .bar { flex:1; height:8px; background:#f1f5f9; border-radius:999px; margin:0 .75rem; overflow:hidden; }
    .map-popup .cat-row .bar > span { display:block; height:100%; background:linear-gradient(90deg,#6366f1,#8b5cf6); }
    .map-popup .building-nav { display:flex; justify-content:space-between; align-items:center; margin-top:.75rem; font-size:12px; color:#64748b; }
    .map-popup .building-nav button { background:#f8fafc; border:1px solid #e2e8f0; border-radius:999px; padding:.25rem .75rem; cursor:pointer; }
    .map-popup .building-nav button:disabled { opacity:.4; cursor:not-allowed; }
@endsection

@section('content')
<div class="page-bg">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;">
        <div>
            <h2 class="gradient-title" style="font-size:1.75rem;margin:0;">{{ __('mc_wo.heading') }}</h2>
            <p style="color:#64748b;font-size:.875rem;margin:.25rem 0 0;">{{ __('mc_wo.subtitle') }}</p>
        </div>
        <a href="{{ url('/mc-workorders') }}" class="btn btn-sm btn-outline-secondary">{{ __('mc_wo.reset') }}</a>
    </div>

    {{-- Filters --}}
    <form method="GET" action="{{ url('/mc-workorders') }}" class="card-soft mb-3">
        <div class="filter-bar">
            <div class="filter-group">
                <label>{{ __('mc_wo.f_from') }}</label>
                <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}">
            </div>
            <div class="filter-group">
                <label>{{ __('mc_wo.f_to') }}</label>
                <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}">
            </div>
            <div class="filter-group">
                <label>{{ __('mc_wo.f_user') }}</label>
                <select name="user_id">
                    <option value="">{{ __('mc_wo.opt_all_users') }}</option>
                    @foreach($userOptions as $u)
                        <option value="{{ $u->id }}" {{ ($filters['user_id'] ?? '') == $u->id ? 'selected' : '' }}>{{ $u->display_name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="filter-group">
                <label>{{ __('mc_wo.f_contract') }}</label>
                <select name="contract_id">
                    <option value="">{{ __('mc_wo.opt_all_contracts') }}</option>
                    @foreach($contractOptions as $c)
                        <option value="{{ $c->id }}" {{ ($filters['contract_id'] ?? '') == $c->id ? 'selected' : '' }}>{{ $c->display_name }}</option>
                    @endforeach
                </select>
            </div>
            <div style="display:flex;gap:.5rem;align-items:flex-end;">
                <button type="submit" class="btn btn-primary btn-sm">{{ __('mc_wo.apply') }}</button>
                <a href="{{ url('/mc-workorders') }}" class="btn btn-sm btn-outline-secondary">{{ __('mc_wo.reset_short') }}</a>
            </div>
        </div>
    </form>

    {{-- Stat Cards Row 1 --}}
    <div class="grid-cards">
        <div class="card-soft kpi kpi-1"><div class="kpi-label">{{ __('mc_wo.k_locations') }}</div><div class="kpi-value">{{ number_format($totals->total_locations) }}</div></div>
        <div class="card-soft kpi kpi-2"><div class="kpi-label">{{ __('mc_wo.k_contracts') }}</div><div class="kpi-value">{{ number_format($totals->total_contracts) }}</div></div>
        <div class="card-soft kpi kpi-3"><div class="kpi-label">{{ __('mc_wo.k_total_wo') }}</div><div class="kpi-value">{{ number_format($totals->total_workorders) }}</div></div>
        <div class="card-soft kpi kpi-4"><div class="kpi-label">{{ __('mc_wo.k_total_exp') }}</div><div class="kpi-value">{{ number_format($totals->total_expenses, 2) }}</div></div>
    </div>

    {{-- Stat Cards Row 2 --}}
    <div class="grid-cards">
        <div class="card-soft kpi kpi-5"><div class="kpi-label">{{ __('mc_wo.k_reactive') }}</div><div class="kpi-value">{{ number_format($totals->total_reactive) }}</div></div>
        <div class="card-soft kpi kpi-6"><div class="kpi-label">{{ __('mc_wo.k_preventive') }}</div><div class="kpi-value">{{ number_format($totals->total_preventive) }}</div></div>
        <div class="card-soft kpi kpi-7"><div class="kpi-label">{{ __('mc_wo.k_late_exec') }}</div><div class="kpi-value">{{ number_format($totals->late_execution) }}</div></div>
        <div class="card-soft kpi kpi-8"><div class="kpi-label">{{ __('mc_wo.k_late_resp') }}</div><div class="kpi-value">-</div></div>
    </div>

    {{-- Line chart + expenses pie --}}
    <div class="grid-2">
        <div class="card-soft">
            <h6 style="font-weight:600;color:#1e293b;margin-bottom:.75rem;">{{ __('mc_wo.ch_category') }}</h6>
            <div id="chartCategoryLine"></div>
        </div>
        <div class="card-soft">
            <h6 style="font-weight:600;color:#1e293b;margin-bottom:.75rem;">{{ __('mc_wo.ch_expenses') }}</h6>
            <div id="chartExpensesPie"></div>
        </div>
    </div>

    {{-- Location names + Status --}}
    <div class="grid-2-equal">
        <div class="card-soft">
            <h6 style="font-weight:600;color:#1e293b;margin-bottom:.75rem;">{{ __('mc_wo.ch_locations') }}</h6>
            <div id="chartLocations"></div>
        </div>
        <div class="card-soft">
            <h6 style="font-weight:600;color:#1e293b;margin-bottom:.75rem;">{{ __('mc_wo.ch_status') }}</h6>
            <div id="chartStatus"></div>
        </div>
    </div>

    {{-- Expenses by category --}}
    <div class="card-soft" style="margin-bottom:1rem;">
        <h6 style="font-weight:600;color:#1e293b;margin-bottom:.75rem;">{{ __('mc_wo.ch_exp_by_cat') }}</h6>
        <table class="exp-table">
            <thead>
                <tr><th>{{ __('mc_wo.col_category') }}</th><th class="num">{{ __('mc_wo.col_wo_count') }}</th><th class="num">{{ __('mc_wo.col_total_cost') }}</th></tr>
            </thead>
            <tbody>
                @forelse($expensesByCategory as $row)
                    <tr>
                        <td>{{ $row->label }}</td>
                        <td class="num">{{ number_format($row->wo_count) }}</td>
                        <td class="num">{{ number_format($row->total, 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" style="text-align:center;padding:1.5rem;color:#94a3b8;">{{ __('mc_wo.empty_exp') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Properties map --}}
    <div class="card-soft map-card">
        <div class="map-card-header">
            <h6>{{ __('mc_wo.ch_map') }}</h6>
        </div>
        <div id="map_container"></div>
    </div>
</div>
@endsection

@section('scripts')
@php
    $mcWoTr = [
        'col_wo_count'        => __('mc_wo.col_wo_count'),
        'chart_total'         => __('mc_wo.chart_total'),
        'p_type'              => __('mc_wo.p_type'),
        'p_buildings'         => __('mc_wo.p_buildings'),
        'p_total_wos'         => __('mc_wo.p_total_wos'),
        'p_preventive'        => __('mc_wo.p_preventive'),
        'p_reactive'          => __('mc_wo.p_reactive'),
        'tab_all'             => __('mc_wo.tab_all'),
        'tab_7d'              => __('mc_wo.tab_7d'),
        'tab_30d'             => __('mc_wo.tab_30d'),
        'pm_rm_split'         => __('mc_wo.pm_rm_split'),
        'no_service_dist'     => __('mc_wo.no_service_dist'),
        'no_wos_window'       => __('mc_wo.no_wos_window'),
        'nav_prev'            => __('mc_wo.nav_prev'),
        'nav_next'            => __('mc_wo.nav_next'),
        'p_building_fallback' => __('mc_wo.p_building_fallback'),
    ];
@endphp
<script>
const MC_WO_TR = @json($mcWoTr);
const PALETTE = ['#6366f1','#8b5cf6','#ec4899','#f43f5e','#f97316','#eab308','#22c55e','#14b8a6','#06b6d4','#3b82f6'];
const baseOpts = { chart:{ fontFamily:'inherit', toolbar:{ show:false }, animations:{ easing:'easeinout', speed:600 }, dir: IS_RTL ? 'rtl' : 'ltr' }, grid:{ borderColor:'#e2e8f0', strokeDashArray:4 }, dataLabels:{ enabled:false }, tooltip:{ theme:'light' }};

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
    series:[{ name: MC_WO_TR.col_wo_count, data: locValues }],
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
    plotOptions:{ pie:{ donut:{ size:'65%', labels:{ show:true, total:{ show:true, label: MC_WO_TR.chart_total }}}}},
    dataLabels:{ enabled:true, style:{ fontSize:'11px', fontWeight:600, colors:['#fff'] }},
}).render();
</script>

{{-- Google Maps (properties) --}}
<script>
const PROPERTY_MAP_GROUPS = @json(array_values($propertyMapData));
const MAP_BUILDING_ICON   = "{{ asset('img/svg/map-building.svg') }}";

let _mapInfoWindows = [];

function initialize_map() {
    const container = document.getElementById('map_container');
    if (!container) return;

    const map = new google.maps.Map(container, {
        center: { lat: 24.7136, lng: 46.6753 },
        zoom: 5,
        streetViewControl: false,
        mapTypeControl: false,
    });

    if (!PROPERTY_MAP_GROUPS.length) return;

    const bounds = new google.maps.LatLngBounds();
    let extended = false;

    PROPERTY_MAP_GROUPS.forEach((group, j) => {
        if (!group.length) return;
        const lat = Number(group[0].latitude);
        const lng = Number(group[0].longitude);
        if (!Number.isFinite(lat) || !Number.isFinite(lng)) return;

        // small offset so overlapping pins don't stack
        const offset = 0.00002;
        const pos = new google.maps.LatLng(lat + j * offset, lng + j * offset);
        bounds.extend(pos);
        extended = true;

        const infoHtml = renderGroupPopup(group);
        const infoWindow = new google.maps.InfoWindow({ content: infoHtml });
        _mapInfoWindows.push(infoWindow);

        new CustomDivMarker(pos, map, {
            title: group.length,
            infoWindow,
        });
    });

    if (extended) {
        map.fitBounds(bounds);
        google.maps.event.addListenerOnce(map, 'idle', () => {
            if (map.getZoom() > 14) map.setZoom(14);
        });
    }
}

class CustomDivMarker extends google.maps.OverlayView {
    constructor(position, map, point) {
        super();
        this.position = position;
        this.map = map;
        this.point = point;
        this.div = null;
        this.setMap(map);
    }
    onAdd() {
        const div = document.createElement('div');
        div.className = 'd-flex gap-10 align-items-center bg-white p-2 radius-xl map-pin-div';
        div.innerHTML = `
            <div class="pin-icon"><img src="${MAP_BUILDING_ICON}" alt=""></div>
            <div class="pin-count">${this.point.title ?? 'N/A'}</div>
        `;
        div.style.position = 'absolute';
        div.style.pointerEvents = 'auto';
        div.addEventListener('click', () => {
            _mapInfoWindows.forEach(w => w.close());
            this.map.setCenter(this.position);
            if (this.map.getZoom() < 9) this.map.setZoom(9);
            openFullscreenPopup(this.point.infoWindow.getContent());
        });
        this.div = div;
        this.getPanes().overlayMouseTarget.appendChild(div);
    }
    draw() {
        const projection = this.getProjection();
        if (!projection || !this.div) return;
        const pos = projection.fromLatLngToDivPixel(this.position);
        if (!pos) return;
        this.div.style.left = pos.x + 'px';
        this.div.style.top  = pos.y + 'px';
        this.div.style.transform = 'translate(-50%, -100%)';
    }
    onRemove() { if (this.div) { this.div.remove(); this.div = null; } }
}

function renderGroupPopup(group) {
    const total = group.length;
    let html = `<div class="map-popup" data-total="${total}">`;
    group.forEach((b, i) => {
        html += renderBuildingPanel(b, i, total);
    });
    html += `</div>`;
    return html;
}

function renderBuildingPanel(b, idx, total) {
    const pct = (n, d) => d > 0 ? Math.round((n / d) * 100) : 0;

    const all  = { total: b.total_work_orders, reactive: b.reactive_work_orders_count,    preventive: b.preventive_work_orders_count };
    const d7   = { total: b.total_work_orders_last_7_days,  reactive: b.reactive_work_orders_count_last_7_days,  preventive: b.preventive_work_orders_count_last_7_days };
    const d30  = { total: b.total_work_orders_last_30_days, reactive: b.reactive_work_orders_count_last_30_days, preventive: b.preventive_work_orders_count_last_30_days };

    const cats = b.work_orders_by_category || {};
    const catHtml = (windowKey) => {
        const entries = Object.entries(cats);
        if (!entries.length) return `<div style="color:#94a3b8;font-size:13px;padding:.5rem 0;">${MC_WO_TR.no_service_dist}</div>`;
        const sum = entries.reduce((s, [,v]) => s + Number(v[windowKey] || 0), 0);
        if (!sum) return `<div style="color:#94a3b8;font-size:13px;padding:.5rem 0;">${MC_WO_TR.no_wos_window}</div>`;
        return entries.map(([cat, v]) => {
            const val = Number(v[windowKey] || 0);
            const p = pct(val, sum);
            return `<div class="cat-row"><span>${cat}</span><span class="bar"><span style="width:${p}%"></span></span><strong>${val}</strong></div>`;
        }).join('');
    };

    const tabId = `b${b.building_id}_${idx}`;

    return `
        <div class="building-panel" data-idx="${idx}" style="display:${idx === 0 ? 'block' : 'none'}">
            <h4>${escapeHtml(b.property_tag || MC_WO_TR.p_building_fallback)}</h4>
            <div class="meta">
                <span>${MC_WO_TR.p_type}: ${escapeHtml(b.property_type || '—')}</span>
                <span>${MC_WO_TR.p_buildings}: ${b.buildings_count ?? 0}</span>
                ${b.location ? `<span>${escapeHtml(b.location)}</span>` : ''}
            </div>
            <div class="kpi-row">
                <div class="kpi-box"><div class="v">${all.total}</div><div class="l">${MC_WO_TR.p_total_wos}</div></div>
                <div class="kpi-box"><div class="v">${all.preventive}</div><div class="l">${MC_WO_TR.p_preventive}</div></div>
                <div class="kpi-box"><div class="v">${all.reactive}</div><div class="l">${MC_WO_TR.p_reactive}</div></div>
            </div>
            <div class="tabs" data-tabs="${tabId}">
                <button class="active" data-tab="all">${MC_WO_TR.tab_all}</button>
                <button data-tab="d7">${MC_WO_TR.tab_7d}</button>
                <button data-tab="d30">${MC_WO_TR.tab_30d}</button>
            </div>
            <div class="tab-panel active" data-panel="all">
                <div class="cat-row"><span>${MC_WO_TR.pm_rm_split}</span><strong>${pct(all.preventive, all.total)}% / ${pct(all.reactive, all.total)}%</strong></div>
                ${catHtml('total')}
            </div>
            <div class="tab-panel" data-panel="d7">
                <div class="cat-row"><span>${MC_WO_TR.pm_rm_split}</span><strong>${pct(d7.preventive, d7.total)}% / ${pct(d7.reactive, d7.total)}%</strong></div>
                ${catHtml('total_last_7_days')}
            </div>
            <div class="tab-panel" data-panel="d30">
                <div class="cat-row"><span>${MC_WO_TR.pm_rm_split}</span><strong>${pct(d30.preventive, d30.total)}% / ${pct(d30.reactive, d30.total)}%</strong></div>
                ${catHtml('total_last_30_days')}
            </div>
            <div class="building-nav">
                <button data-nav="prev" ${idx === 0 ? 'disabled' : ''}>${MC_WO_TR.nav_prev}</button>
                <span>${idx + 1} / ${total}</span>
                <button data-nav="next" ${idx === total - 1 ? 'disabled' : ''}>${MC_WO_TR.nav_next}</button>
            </div>
        </div>
    `;
}

function escapeHtml(s) {
    return String(s ?? '').replace(/[&<>"']/g, m => ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[m]));
}

function openFullscreenPopup(contentHtml) {
    closeFullscreenPopup();
    const overlay = document.createElement('div');
    overlay.className = 'map-fs-overlay';
    overlay.innerHTML = `
        <div class="map-fs-inner">
            <button class="map-fs-close" aria-label="Close">&times;</button>
            <div class="map-fs-body">${contentHtml}</div>
        </div>
    `;
    overlay.addEventListener('click', e => { if (e.target === overlay) closeFullscreenPopup(); });
    overlay.querySelector('.map-fs-close').addEventListener('click', closeFullscreenPopup);

    overlay.querySelectorAll('[data-tabs]').forEach(bar => {
        bar.addEventListener('click', e => {
            const btn = e.target.closest('button[data-tab]');
            if (!btn) return;
            const panel = bar.closest('.building-panel');
            bar.querySelectorAll('button').forEach(b => b.classList.toggle('active', b === btn));
            panel.querySelectorAll('.tab-panel').forEach(p => p.classList.toggle('active', p.dataset.panel === btn.dataset.tab));
        });
    });

    overlay.querySelectorAll('button[data-nav]').forEach(btn => {
        btn.addEventListener('click', e => {
            const panels = overlay.querySelectorAll('.building-panel');
            let cur = 0;
            panels.forEach((p, i) => { if (p.style.display !== 'none') cur = i; });
            const next = btn.dataset.nav === 'next' ? cur + 1 : cur - 1;
            if (next < 0 || next >= panels.length) return;
            panels.forEach((p, i) => { p.style.display = i === next ? 'block' : 'none'; });
        });
    });

    document.body.appendChild(overlay);
}

function closeFullscreenPopup() {
    document.querySelectorAll('.map-fs-overlay').forEach(o => o.remove());
}
</script>
<script async defer src="https://maps.google.com/maps/api/js?key=AIzaSyDWLZhSsgHS_tooyDVeSNY5HNY1ZwPMZ2o&callback=initialize_map"></script>
@endsection
