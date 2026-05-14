@extends('layouts.app')
@section('title', __('mc_fm.page_title'))

@section('styles')
    .fmd-bg { background: linear-gradient(180deg,#f1f5f9 0%,#e2e8f0 100%); padding: 1.25rem; border-radius: 12px; }
    .fmd-card { background:#fff; border-radius:14px; box-shadow: 0 1px 2px rgba(15,23,42,.04), 0 8px 24px -12px rgba(15,23,42,.08); padding:1rem; display:flex; flex-direction:column; }
    .fmd-card .title { font-size:12px; color:#64748b; font-weight:600; margin:0 0 .5rem; text-transform:uppercase; letter-spacing:.04em; }

    /* Top bar */
    .fmd-head { display:flex; flex-direction:column; align-items:flex-start; margin-bottom:1rem; gap:.75rem; }
    .fmd-head h2 { font-size:1.5rem; font-weight:700; color:#0f172a; margin:0; }
    .fmd-head .crumb { color:#64748b; font-size:.8rem; margin-top:.1rem; }
    .fmd-head .crumb a { color:#6366f1; text-decoration:none; }
    .fmd-filters { display:flex; gap:.5rem; flex-wrap:wrap; align-items:flex-end; }
    .fmd-fi { display:flex; flex-direction:column; gap:.2rem; }
    .fmd-fi label { font-size:10px; text-transform:uppercase; letter-spacing:.06em; color:#94a3b8; font-weight:600; }
    .fmd-sel-wrap, .fmd-date-wrap { position:relative; display:flex; align-items:center; }
    .fmd-sel-wrap .fi-icon, .fmd-date-wrap .fi-icon { position:absolute; left:.55rem; width:14px; height:14px; color:#94a3b8; pointer-events:none; }
    .fmd-sel-wrap select, .fmd-date-wrap input[type=date] { background:#fff; border:1px solid #e2e8f0; border-radius:8px; padding:.4rem .75rem .4rem 1.9rem; font-size:13px; color:#475569; outline:none; height:34px; }
    .fmd-sel-wrap select { appearance:none; -webkit-appearance:none; min-width:148px; padding-right:1.75rem; cursor:pointer; }
    .fmd-sel-wrap::after { content:''; position:absolute; right:.6rem; top:50%; transform:translateY(-25%); border:4px solid transparent; border-top:5px solid #94a3b8; pointer-events:none; }
    .fmd-sel-wrap select:focus, .fmd-date-wrap input:focus { border-color:#6366f1; box-shadow:0 0 0 2px rgba(99,102,241,.1); }

    /* Main 3-column grid */
    .fmd-grid { display:grid; grid-template-columns: 1.1fr 2.4fr 1.1fr; gap:1rem; }
    @media (max-width:1200px) { .fmd-grid { grid-template-columns: 1fr 1fr; } .fmd-center { grid-column: 1 / -1; } }
    @media (max-width:720px) { .fmd-grid { grid-template-columns: 1fr; } }

    .fmd-left, .fmd-center, .fmd-right { display:flex; flex-direction:column; gap:1rem; }

    /* Left column */
    .fmd-bar-card h6 { margin:0 0 .5rem; font-weight:600; color:#0f172a; font-size:13px; }
    .fmd-donut-wrap { position:relative; height:180px; }
    .fmd-donut-center { position:absolute; inset:0; display:flex; flex-direction:column; align-items:center; justify-content:center; text-align:center; pointer-events:none; }
    .fmd-donut-center .v { font-size:1.4rem; font-weight:700; color:#0f172a; }
    .fmd-donut-center .l { font-size:11px; color:#64748b; text-transform:uppercase; letter-spacing:.04em; }
    .fmd-legend { margin-top:.75rem; display:flex; flex-direction:column; gap:.4rem; padding:0 .75rem; }
    .fmd-legend .row { display:flex; align-items:center; font-size:12px; color:#334155; gap:.5rem; }
    .fmd-legend .dot { width:10px; height:10px; border-radius:2px; flex-shrink:0; }
    .fmd-legend .name { flex:1; }
    .fmd-legend .val { font-weight:600; color:#0f172a; font-variant-numeric:tabular-nums; }
    .fmd-legend .pct { color:#64748b; min-width:42px; text-align:right; }

    /* Center / map card */
    .fmd-map-card { padding:0; overflow:hidden; }
    .fmd-map-head { padding:1rem 1.25rem .5rem; }
    #fmd_map { width:100%; height:420px; }
    .fmd-location-strip { padding:.6rem 1.25rem; border-top:1px solid #e2e8f0; }
    .fmd-location-strip .lbl { font-size:10px; text-transform:uppercase; letter-spacing:.06em; color:#94a3b8; font-weight:600; display:block; margin-bottom:.4rem; }
    .fmd-loc-tags { display:flex; flex-wrap:wrap; gap:.3rem; max-height:72px; overflow-y:auto; }
    .fmd-loc-tag { background:#f1f5f9; border:1px solid #e2e8f0; border-radius:999px; padding:.15rem .65rem; font-size:11px; color:#475569; white-space:nowrap; }
    .fmd-counters { display:grid; grid-template-columns:repeat(4,1fr); text-align:center; border-top:1px solid #e2e8f0; }
    .fmd-counters > div { padding:.85rem .5rem; border-right:1px solid #e2e8f0; }
    .fmd-counters > div:last-child { border-right:none; }
    .fmd-counters .v { font-size:1.25rem; font-weight:700; color:#0f172a; font-variant-numeric:tabular-nums; }
    .fmd-counters .l { font-size:11px; color:#64748b; text-transform:uppercase; letter-spacing:.05em; margin-top:.15rem; }

    /* Right column */
    .fmd-logo-card { align-items:center; text-align:center; padding:1rem; }
    .fmd-logo-card img { max-width:100%; max-height:72px; object-fit:contain; }

    .fmd-stat { display:flex; align-items:center; gap:.75rem; padding:.85rem 1rem; }
    .fmd-stat .icon { width:36px; height:36px; border-radius:8px; display:flex; align-items:center; justify-content:center; flex-shrink:0; background:#f1f5f9; }
    .fmd-stat .icon svg { width:18px; height:18px; }
    .fmd-stat .body { flex:1; }
    .fmd-stat .body .v { font-size:1.15rem; font-weight:700; color:#0f172a; font-variant-numeric:tabular-nums; line-height:1; }
    .fmd-stat .body .l { font-size:11px; color:#64748b; margin-top:.25rem; text-transform:uppercase; letter-spacing:.04em; }
    .fmd-stat.red    .icon { background:#fef2f2; color:#dc2626; }
    .fmd-stat.amber  .icon { background:#fff7ed; color:#ea580c; }
    .fmd-stat.green  .icon { background:#ecfdf5; color:#059669; }
    .fmd-stat.indigo .icon { background:#eef2ff; color:#6366f1; }
    .fmd-stat.red    .body .v { color:#dc2626; }
    .fmd-stat.amber  .body .v { color:#ea580c; }
    .fmd-stat.green  .body .v { color:#059669; }
    .fmd-stat.indigo .body .v { color:#6366f1; }

    .fmd-gauge-wrap { position:relative; height:170px; }
    .fmd-gauge-center { position:absolute; inset:0; display:flex; flex-direction:column; align-items:center; justify-content:center; pointer-events:none; text-align:center; }
    .fmd-gauge-center .delta { font-size:.85rem; color:#16a34a; font-weight:600; }
    .fmd-gauge-center .delta.down { color:#dc2626; }
    .fmd-gauge-center .v { font-size:1.3rem; font-weight:700; color:#0f172a; margin-top:.1rem; font-variant-numeric:tabular-nums; }
    .fmd-gauge-label { text-align:center; color:#64748b; font-size:12px; margin-top:-.5rem; }

    /* Map pin reuse */
    .map-pin-div { cursor:pointer; box-shadow:0 4px 12px rgba(15,23,42,.15); white-space:nowrap; padding:.35rem .6rem; display:flex; align-items:center; gap:.4rem; background:#fff; border-radius:999px; }
    .map-pin-div .pin-icon { width:26px; height:26px; border-radius:50%; background:#dc2626; display:flex; align-items:center; justify-content:center; }
    .map-pin-div .pin-icon img { width:14px; filter:brightness(0) invert(1); }
    .map-pin-div .pin-count { font-weight:700; color:#0f172a; font-size:13px; }
@endsection

@section('content')
<div class="fmd-bg">

    {{-- Top bar --}}
    <div class="fmd-head">
        <div>
            <h2>{{ __('mc_fm.heading') }}</h2>
            <div class="crumb"><a href="{{ url('/') }}">{{ __('mc_fm.crumb_dashboard') }}</a> &rsaquo; {{ __('mc_fm.crumb_fm') }}</div>
        </div>
        <form method="GET" action="{{ url('/mc-following2') }}" class="fmd-filters">
            <div class="fmd-fi">
                <label>{{ __('mc_fm.f_from') }}</label>
                <div class="fmd-date-wrap">
                    <svg class="fi-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                    <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}">
                </div>
            </div>
            <div class="fmd-fi">
                <label>{{ __('mc_fm.f_to') }}</label>
                <div class="fmd-date-wrap">
                    <svg class="fi-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                    <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}">
                </div>
            </div>
            <div class="fmd-fi">
                <label>{{ __('mc_follow.f_location') }}</label>
                <div class="fmd-sel-wrap">
                    <svg class="fi-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                    <select name="location_id">
                        <option value="">{{ __('mc_follow.opt_all_locations') }}</option>
                        @foreach($locationOptions as $loc)
                            <option value="{{ $loc->id }}" {{ ($filters['location_id'] ?? '') == $loc->id ? 'selected' : '' }}>{{ $loc->building_name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="fmd-fi">
                <label>{{ __('mc_follow.f_user') }}</label>
                <div class="fmd-sel-wrap">
                    <svg class="fi-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                    <select name="user_id">
                        <option value="">{{ __('mc_follow.opt_all_users') }}</option>
                        @foreach($userOptions as $u)
                            <option value="{{ $u->id }}" {{ ($filters['user_id'] ?? '') == $u->id ? 'selected' : '' }}>{{ $u->display_name }} — {{ $u->type_label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="fmd-fi" style="flex-direction:row;gap:.4rem;">
                <button type="submit" class="btn btn-primary btn-sm">{{ __('mc_fm.apply') }}</button>
                <a href="{{ url('/mc-following2') }}" class="btn btn-sm btn-outline-secondary">{{ __('mc_fm.reset') }}</a>
            </div>
        </form>
    </div>

    {{-- Main 3-column grid --}}
    <div class="fmd-grid">

        {{-- LEFT --}}
        <div class="fmd-left">
            <div class="fmd-card fmd-logo-card">
                <img src="{{ asset('img/mclogo.png') }}" alt="Ministry of Culture">
            </div>

            <div class="fmd-card" style="padding:0;">
                <div class="fmd-stat red">
                    <div class="icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                    </div>
                    <div class="body"><div class="v">{{ number_format($totals['locations']) }}</div><div class="l">{{ __('mc_fm.st_locations') }}</div></div>
                </div>
            </div>

            {{-- Preventive following metrics (mirrors /mc-following totals) --}}
            <div class="fmd-card" style="padding:0;">
                <div class="fmd-stat green">
                    <div class="icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                    </div>
                    <div class="body"><div class="v">{{ number_format($closedWO) }}</div><div class="l">{{ __('mc_fm.st_closed_wo') }}</div></div>
                </div>
            </div>

            <div class="fmd-card" style="padding:0;">
                <div class="fmd-stat amber">
                    <div class="icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    </div>
                    <div class="body"><div class="v">{{ number_format($notClosedWO) }}</div><div class="l">{{ __('mc_fm.st_not_closed_wo') }}</div></div>
                </div>
            </div>

            <div class="fmd-card">
                <div class="fmd-gauge-wrap">
                    <div id="fmd_gauge"></div>
                    <div class="fmd-gauge-center">
                        <div class="delta">{{ number_format($completionPct, 1) }}%</div>
                        <div class="v">{{ number_format($closedWO) }} / {{ number_format($closedWO + $notClosedWO) }}</div>
                    </div>
                </div>
                <div class="fmd-gauge-label">{{ __('mc_fm.st_completion') }}</div>
            </div>
        </div>

        {{-- CENTER --}}
        <div class="fmd-center">
            <div class="fmd-card fmd-map-card">
                <div class="fmd-map-head">
                    <h6 style="margin:0;font-weight:600;color:#0f172a;font-size:13px;">{{ __('mc_fm.map_title') }}</h6>
                </div>
                <div id="fmd_map"></div>
                <div class="fmd-location-strip">
                    <span class="lbl">{{ __('mc_fm.building_names') }}</span>
                    <div class="fmd-loc-tags">
                        @forelse($buildingNames as $name)
                            <span class="fmd-loc-tag">{{ $name }}</span>
                        @empty
                            <span style="font-size:12px;color:#94a3b8;">{{ __('mc_fm.no_buildings') }}</span>
                        @endforelse
                    </div>
                </div>
                <div class="fmd-counters">
                    <div><div class="v">{{ number_format($totals['status_open']) }}</div><div class="l">{{ __('mc_fm.c_open') }}</div></div>
                    <div><div class="v">{{ number_format($totals['status_in_progress']) }}</div><div class="l">{{ __('mc_fm.c_in_progress') }}</div></div>
                    <div><div class="v">{{ number_format($totals['status_closed']) }}</div><div class="l">{{ __('mc_fm.c_closed') }}</div></div>
                    <div><div class="v">{{ number_format($totals['total']) }}</div><div class="l">{{ __('mc_fm.c_total') }}</div></div>
                </div>
            </div>
        </div>

        {{-- RIGHT --}}
        <div class="fmd-right">
            <div class="fmd-card fmd-bar-card">
                <h6>{{ __('mc_follow.ch_status_line') }}</h6>
                <div id="fmd_status_line"></div>
            </div>

            <div class="fmd-card">
                <h6 style="margin:0 0 .5rem;font-weight:600;color:#0f172a;font-size:13px;">{{ __('mc_follow.ch_status_pie') }}</h6>
                <div id="fmd_status_pie"></div>
            </div>
        </div>

    </div>
</div>
@endsection

@section('scripts')
<script>
/* ------- Left: User Completion Status line + Status pie ------- */
const statusColors = { 1:'#3b82f6', 2:'#f59e0b', 3:'#94a3b8', 4:'#22c55e', 5:'#ef4444', 6:'#6366f1', 7:'#ec4899', 8:'#8b5cf6' };

/* Line: preventive WOs per status over time (same data as /mc-following) */
const lineLabels = @json($months);
const lineSeries = @json($lineSeries);

new ApexCharts(document.querySelector('#fmd_status_line'), {
    chart:{ type:'line', height:240, fontFamily:'inherit', toolbar:{ show:false }, animations:{ easing:'easeinout', speed:600 }, dir: IS_RTL ? 'rtl' : 'ltr' },
    series: lineSeries.map(s => ({ name:s.name, data:s.data })),
    xaxis:{ categories: lineLabels, labels:{ style:{ fontSize:'10px', colors:'#64748b' } }, axisBorder:{ show:false }, axisTicks:{ show:false } },
    yaxis:{ labels:{ style:{ fontSize:'10px', colors:'#94a3b8' }, formatter: v => v >= 1000 ? (v/1000).toFixed(0)+'K' : v } },
    stroke:{ curve:'smooth', width:2 },
    colors: lineSeries.map(s => statusColors[s.status] || '#64748b'),
    markers:{ size:3 },
    dataLabels:{ enabled:false },
    legend:{ position:'bottom', fontSize:'11px' },
    grid:{ borderColor:'#f1f5f9', strokeDashArray:4 },
    tooltip:{ theme:'light' },
}).render();

/* Pie: Completion Preventive Scheduled by Status (same data as /mc-following) */
const pieRows = @json($perStatus);
new ApexCharts(document.querySelector('#fmd_status_pie'), {
    chart:{ type:'pie', height:260, fontFamily:'inherit', toolbar:{ show:false }, animations:{ easing:'easeinout', speed:600 }, dir: IS_RTL ? 'rtl' : 'ltr' },
    series: pieRows.map(r => r.total),
    labels: pieRows.map(r => r.label),
    colors: pieRows.map(r => statusColors[r.status] || '#94a3b8'),
    stroke:{ width:0 },
    legend:{ position:'bottom', fontSize:'11px' },
    dataLabels:{ enabled:true, style:{ fontSize:'11px', fontWeight:600, colors:['#fff'] } },
    tooltip:{ theme:'light' },
}).render();

/* ------- Right: gauge (radialBar) — Completion % ------- */
new ApexCharts(document.querySelector('#fmd_gauge'), {
    chart: { type:'radialBar', height:220, offsetY:-10, sparkline:{ enabled:true }, dir: IS_RTL ? 'rtl' : 'ltr' },
    series: [{{ $completionPct }}],
    colors: ['#10b981'],
    plotOptions:{ radialBar:{
        startAngle:-110, endAngle:110, hollow:{ size:'62%' },
        track:{ background:'#d1fae5', strokeWidth:'100%' },
        dataLabels:{ show:false }
    }},
    stroke:{ lineCap:'round' },
    fill:{ type:'gradient', gradient:{ shade:'light', type:'horizontal', gradientToColors:['#059669'], stops:[0,100] } }
}).render();
</script>

{{-- Google Map reused from /mc-workorders --}}
<script>
const PROPERTY_MAP_GROUPS = @json(array_values($propertyMapData));
const MAP_BUILDING_ICON   = "{{ asset('img/svg/map-building.svg') }}";
const MAP_TR              = @json(['na' => __('mc_fm.na')]);

let _mapInfoWindows = [];

function initialize_map() {
    const container = document.getElementById('fmd_map');
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

        const offset = 0.00002;
        const pos = new google.maps.LatLng(lat + j * offset, lng + j * offset);
        bounds.extend(pos);
        extended = true;

        new CustomDivMarker(pos, map, { title: group.length });
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
        div.className = 'map-pin-div';
        div.innerHTML = `
            <div class="pin-icon"><img src="${MAP_BUILDING_ICON}" alt=""></div>
            <div class="pin-count">${this.point.title ?? MAP_TR.na}</div>
        `;
        div.style.position = 'absolute';
        div.style.pointerEvents = 'auto';
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
</script>
<script async defer src="https://maps.google.com/maps/api/js?key=AIzaSyDWLZhSsgHS_tooyDVeSNY5HNY1ZwPMZ2o&callback=initialize_map"></script>
@endsection
