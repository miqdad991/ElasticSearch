@extends('layouts.app')
@section('title', __('overview.title'))

@section('styles')
    .page-bg { background: linear-gradient(180deg,#f1f5f9 0%,#e2e8f0 100%); padding:1.25rem; border-radius:12px; }
    .card-soft { background:#fff; border-radius:14px; box-shadow:0 1px 2px rgba(15,23,42,.04),0 8px 24px -12px rgba(15,23,42,.08); padding:1rem; }
    .gradient-title { background:linear-gradient(90deg,#6366f1,#14b8a6,#f59e0b); -webkit-background-clip:text; background-clip:text; color:transparent; font-weight:700; }
    .pill { display:inline-block; padding:2px 8px; border-radius:9999px; font-size:11px; font-weight:600; }
    .avatar { width:34px; height:34px; border-radius:9999px; display:inline-flex; align-items:center; justify-content:center; font-weight:700; color:#fff; font-size:13px; }

    /* Platform KPI strip */
    .kpi-strip { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:.75rem; margin-bottom:1rem; }
    @media (max-width:900px) { .kpi-strip { grid-template-columns:repeat(2,minmax(0,1fr)); } }
    @media (max-width:480px) { .kpi-strip { grid-template-columns:1fr; } }
    .kpi-card { background:#fff; border-radius:12px; box-shadow:0 1px 2px rgba(15,23,42,.04),0 4px 12px -6px rgba(15,23,42,.08); padding:.85rem 1rem .85rem 1.15rem; position:relative; overflow:hidden; }
    .kpi-card::before { content:""; position:absolute; left:0; top:0; bottom:0; width:4px; border-radius:4px 0 0 4px; background:var(--c,#6366f1); }
    .kpi-card-label { font-size:10px; text-transform:uppercase; letter-spacing:.06em; color:#64748b; font-weight:600; }
    .kpi-card-value { font-size:1.3rem; font-weight:700; color:#0f172a; margin-top:.2rem; }

    /* Domain sections */
    .domain-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(280px,1fr)); gap:1rem; margin-bottom:1rem; }
    .domain-card { background:#fff; border-radius:14px; box-shadow:0 1px 2px rgba(15,23,42,.04),0 8px 24px -12px rgba(15,23,42,.08); overflow:hidden; }
    .domain-header { padding:.6rem 1rem; display:flex; align-items:center; gap:.5rem; }
    .domain-header-icon { width:28px; height:28px; border-radius:8px; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
    .domain-header-title { font-weight:700; font-size:.9rem; color:#fff; }
    .domain-body { padding:.85rem 1rem; }

    .mini-kpi-row { display:grid; grid-template-columns:repeat(auto-fill,minmax(90px,1fr)); gap:.5rem; margin-bottom:.75rem; }
    .mini-kpi { background:#f8fafc; border-radius:8px; padding:.4rem .6rem; }
    .mini-kpi-label { font-size:10px; text-transform:uppercase; letter-spacing:.04em; color:#64748b; font-weight:600; }
    .mini-kpi-value { font-size:1rem; font-weight:700; color:#0f172a; }

    .breakdown-row { display:flex; flex-wrap:wrap; gap:.35rem; }
    .bd-pill { font-size:11px; font-weight:600; padding:2px 8px; border-radius:999px; background:#f1f5f9; color:#475569; }

    /* Users + billing row */
    .mid-grid { display:grid; grid-template-columns:1fr 1fr; gap:1rem; margin-bottom:1rem; }
    @media (max-width:900px) { .mid-grid { grid-template-columns:1fr; } }

    /* Bottom tables */
    .tables-grid { display:grid; grid-template-columns:2fr 1fr; gap:1rem; }
    @media (max-width:1100px) { .tables-grid { grid-template-columns:1fr; } }

    .bld-table { width:100%; border-collapse:collapse; font-size:13px; }
    .bld-table th { text-align:left; padding:.45rem .6rem; color:#64748b; font-weight:600; font-size:11px; text-transform:uppercase; letter-spacing:.04em; border-bottom:1px solid #e2e8f0; background:#f8fafc; white-space:nowrap; }
    .bld-table td { padding:.45rem .6rem; border-bottom:1px solid #f1f5f9; color:#334155; white-space:nowrap; }
    .bld-table td.num { text-align:right; font-variant-numeric:tabular-nums; }

    .stat-row { display:flex; justify-content:space-between; align-items:center; padding:.35rem 0; border-bottom:1px solid #f1f5f9; font-size:13px; }
    .stat-row:last-child { border-bottom:none; }
    .stat-row-label { color:#64748b; font-weight:500; }
    .stat-row-value { font-weight:700; color:#0f172a; }

    .contracts-strip { display:grid; grid-template-columns:repeat(auto-fill,minmax(130px,1fr)); gap:.5rem; margin-bottom:.75rem; }
@endsection

@section('content')
<div class="page-bg">

    {{-- Page title --}}
    <div style="margin-bottom:1rem;">
        <h2 class="gradient-title" style="font-size:1.75rem;margin:0;">{{ __('overview.heading') }}</h2>
        <p style="color:#64748b;font-size:.875rem;margin:.25rem 0 0;">{{ __('overview.subtitle') }}</p>
    </div>

    {{-- ── Platform KPI strip ──────────────────────────────────────── --}}
    @php
        $kpiColors = ['#6366f1','#22c55e','#94a3b8','#0ea5e9','#14b8a6','#a855f7','#f59e0b','#ef4444','#ec4899'];
        $kpiIdx    = 0;
        $floatKeys = ['Sub Value', 'Projects Payment Due', 'Projects Overdue'];
        $platformLabels = [
            'Total Projects'       => __('overview.total_projects'),
            'Active Projects'      => __('overview.active_projects'),
            'Service Providers'    => __('overview.service_providers'),
            'Admins'               => __('overview.admins'),
            'Subscriptions'        => __('overview.subscriptions'),
            'Active Subs'          => __('overview.active_subs'),
            'Sub Value'            => __('overview.sub_value'),
            'Projects Payment Due' => __('overview.payment_due'),
            'Projects Overdue'     => __('overview.projects_overdue'),
        ];
    @endphp
    <div class="kpi-strip">
        @foreach ($platformCards as $label => $value)
            @php $c = $kpiColors[$kpiIdx % count($kpiColors)]; $kpiIdx++; @endphp
            <div class="kpi-card" style="--c:{{ $c }};">
                <div class="kpi-card-label">{{ $platformLabels[$label] ?? $label }}</div>
                <div class="kpi-card-value">
                    {{ is_numeric($value) ? number_format($value, in_array($label, $floatKeys) ? 2 : 0) : $value }}
                </div>
            </div>
        @endforeach
    </div>

    {{-- ── Domain cards row: WO / Properties / Assets ─────────────── --}}
    <div class="domain-grid">

        {{-- Work Orders --}}
        <div class="domain-card">
            <div class="domain-header" style="background:linear-gradient(90deg,#6366f1,#818cf8);">
                <div class="domain-header-icon" style="background:rgba(255,255,255,.18);">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/><line x1="7" y1="8" x2="17" y2="8"/><line x1="7" y1="12" x2="13" y2="12"/></svg>
                </div>
                <span class="domain-header-title">{{ __('overview.work_orders') }}</span>
                <a href="{{ url('/work-orders') }}" style="margin-left:auto;font-size:11px;color:rgba(255,255,255,.8);text-decoration:none;font-weight:600;">{{ __('overview.view') }}</a>
            </div>
            <div class="domain-body">
                <div class="mini-kpi-row">
                    <div class="mini-kpi"><div class="mini-kpi-label">{{ __('overview.total') }}</div><div class="mini-kpi-value">{{ number_format($workOrderStats['total']) }}</div></div>
                    <div class="mini-kpi"><div class="mini-kpi-label">{{ __('overview.open') }}</div><div class="mini-kpi-value" style="color:#f59e0b;">{{ number_format($workOrderStats['open']) }}</div></div>
                    <div class="mini-kpi"><div class="mini-kpi-label">{{ __('overview.in_progress') }}</div><div class="mini-kpi-value" style="color:#0ea5e9;">{{ number_format($workOrderStats['in_progress']) }}</div></div>
                    <div class="mini-kpi"><div class="mini-kpi-label">{{ __('overview.closed') }}</div><div class="mini-kpi-value" style="color:#22c55e;">{{ number_format($workOrderStats['closed']) }}</div></div>
                </div>
                <div class="mini-kpi-row" style="margin-bottom:.65rem;">
                    <div class="mini-kpi"><div class="mini-kpi-label">{{ __('overview.preventive') }}</div><div class="mini-kpi-value">{{ number_format($workOrderStats['preventive']) }}</div></div>
                    <div class="mini-kpi"><div class="mini-kpi-label">{{ __('overview.reactive') }}</div><div class="mini-kpi-value">{{ number_format($workOrderStats['reactive']) }}</div></div>
                    <div class="mini-kpi"><div class="mini-kpi-label">{{ __('overview.hard_svc') }}</div><div class="mini-kpi-value">{{ number_format($workOrderStats['hard_service']) }}</div></div>
                    <div class="mini-kpi"><div class="mini-kpi-label">{{ __('overview.soft_svc') }}</div><div class="mini-kpi-value">{{ number_format($workOrderStats['soft_service']) }}</div></div>
                </div>
                <div style="font-size:11px;color:#64748b;font-weight:600;margin-bottom:.35rem;text-transform:uppercase;letter-spacing:.04em;">{{ __('overview.by_status') }}</div>
                @if($woByStatus->isNotEmpty())
                    <div id="ch_wo_status" style="margin:0 -4px;"></div>
                @else
                    <div class="breakdown-row">
                        @foreach($woByType as $b)<span class="bd-pill">{{ $b['label'] }}: {{ number_format($b['count']) }}</span>@endforeach
                    </div>
                @endif
                <div style="margin-top:.5rem;font-size:11px;color:#64748b;">{{ __('overview.total_cost') }}: <strong style="color:#0f172a;">{{ number_format($workOrderStats['total_cost'], 2) }}</strong></div>
            </div>
        </div>

        {{-- Properties --}}
        <div class="domain-card">
            <div class="domain-header" style="background:linear-gradient(90deg,#0ea5e9,#38bdf8);">
                <div class="domain-header-icon" style="background:rgba(255,255,255,.18);">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                </div>
                <span class="domain-header-title">{{ __('overview.properties') }}</span>
                <a href="{{ url('/properties') }}" style="margin-left:auto;font-size:11px;color:rgba(255,255,255,.8);text-decoration:none;font-weight:600;">{{ __('overview.view') }}</a>
            </div>
            <div class="domain-body">
                <div class="mini-kpi-row">
                    <div class="mini-kpi"><div class="mini-kpi-label">{{ __('overview.total') }}</div><div class="mini-kpi-value">{{ number_format($propertyStats['total']) }}</div></div>
                    <div class="mini-kpi"><div class="mini-kpi-label">{{ __('overview.active') }}</div><div class="mini-kpi-value" style="color:#22c55e;">{{ number_format($propertyStats['active']) }}</div></div>
                    <div class="mini-kpi"><div class="mini-kpi-label">{{ __('overview.buildings') }}</div><div class="mini-kpi-value">{{ number_format($propertyStats['buildings_only']) }}</div></div>
                    <div class="mini-kpi"><div class="mini-kpi-label">{{ __('overview.complexes') }}</div><div class="mini-kpi-value">{{ number_format($propertyStats['complexes']) }}</div></div>
                </div>
                <div class="mini-kpi-row" style="margin-bottom:.65rem;">
                    <div class="mini-kpi" style="grid-column:span 2;">
                        <div class="mini-kpi-label">{{ __('overview.total_buildings') }}</div>
                        <div class="mini-kpi-value">{{ number_format($propertyStats['total_buildings']) }}</div>
                    </div>
                </div>
                <div style="font-size:11px;color:#64748b;font-weight:600;margin-bottom:.35rem;text-transform:uppercase;letter-spacing:.04em;">{{ __('overview.by_region') }}</div>
                <div class="breakdown-row">
                    @forelse($propByRegion as $r)
                        <span class="bd-pill" style="background:#e0f2fe;color:#0369a1;">{{ $r['label'] }}: {{ number_format($r['count']) }}</span>
                    @empty
                        <span style="font-size:12px;color:#94a3b8;">{{ __('overview.no_region_data') }}</span>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Assets --}}
        <div class="domain-card">
            <div class="domain-header" style="background:linear-gradient(90deg,#14b8a6,#2dd4bf);">
                <div class="domain-header-icon" style="background:rgba(255,255,255,.18);">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/><line x1="12" y1="12" x2="12" y2="16"/><line x1="10" y1="14" x2="14" y2="14"/></svg>
                </div>
                <span class="domain-header-title">{{ __('overview.assets') }}</span>
                <a href="{{ url('/assets') }}" style="margin-left:auto;font-size:11px;color:rgba(255,255,255,.8);text-decoration:none;font-weight:600;">{{ __('overview.view') }}</a>
            </div>
            <div class="domain-body">
                <div class="mini-kpi-row">
                    <div class="mini-kpi"><div class="mini-kpi-label">{{ __('overview.total') }}</div><div class="mini-kpi-value">{{ number_format($assetStats['total']) }}</div></div>
                    <div class="mini-kpi"><div class="mini-kpi-label">{{ __('overview.categories') }}</div><div class="mini-kpi-value">{{ number_format($assetStats['categories']) }}</div></div>
                    <div class="mini-kpi"><div class="mini-kpi-label">{{ __('overview.warranty') }}</div><div class="mini-kpi-value" style="color:#14b8a6;">{{ number_format($assetStats['under_warranty']) }}</div></div>
                    <div class="mini-kpi"><div class="mini-kpi-label">{{ __('overview.has_status') }}</div><div class="mini-kpi-value">{{ number_format($assetStats['with_status']) }}</div></div>
                </div>
                <div class="mini-kpi-row" style="margin-bottom:.65rem;">
                    <div class="mini-kpi" style="grid-column:span 2;">
                        <div class="mini-kpi-label">{{ __('overview.total_asset_value') }}</div>
                        <div class="mini-kpi-value" style="font-size:.9rem;">{{ number_format($assetStats['total_value'], 2) }}</div>
                    </div>
                </div>
                <div style="font-size:11px;color:#64748b;font-weight:600;margin-bottom:.35rem;text-transform:uppercase;letter-spacing:.04em;">{{ __('overview.top_categories') }}</div>
                <div class="breakdown-row">
                    @forelse($assetByCategory as $c)
                        <span class="bd-pill" style="background:#ccfbf1;color:#0f766e;">{{ $c['label'] }}: {{ number_format($c['count']) }}</span>
                    @empty
                        <span style="font-size:12px;color:#94a3b8;">{{ __('overview.no_cat_data') }}</span>
                    @endforelse
                </div>
            </div>
        </div>

    </div>

    {{-- ── Users + Billing row ──────────────────────────────────────── --}}
    <div class="mid-grid">

        {{-- Users --}}
        <div class="domain-card">
            <div class="domain-header" style="background:linear-gradient(90deg,#a855f7,#c084fc);">
                <div class="domain-header-icon" style="background:rgba(255,255,255,.18);">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                </div>
                <span class="domain-header-title">{{ __('overview.users') }}</span>
                <a href="{{ url('/users') }}" style="margin-left:auto;font-size:11px;color:rgba(255,255,255,.8);text-decoration:none;font-weight:600;">{{ __('overview.view') }}</a>
            </div>
            <div class="domain-body">
                <div class="mini-kpi-row" style="margin-bottom:.75rem;">
                    <div class="mini-kpi"><div class="mini-kpi-label">{{ __('overview.total') }}</div><div class="mini-kpi-value">{{ number_format($userStats['total']) }}</div></div>
                    <div class="mini-kpi"><div class="mini-kpi-label">{{ __('overview.active') }}</div><div class="mini-kpi-value" style="color:#22c55e;">{{ number_format($userStats['active']) }}</div></div>
                    <div class="mini-kpi"><div class="mini-kpi-label">{{ __('overview.inactive') }}</div><div class="mini-kpi-value" style="color:#94a3b8;">{{ number_format($userStats['inactive']) }}</div></div>
                </div>
                <div style="font-size:11px;color:#64748b;font-weight:600;margin-bottom:.35rem;text-transform:uppercase;letter-spacing:.04em;">{{ __('overview.by_user_type') }}</div>
                @if($userByType->isNotEmpty())
                    <div id="ch_user_type"></div>
                @else
                    <div style="font-size:12px;color:#94a3b8;">{{ __('overview.no_user_type') }}</div>
                @endif
            </div>
        </div>

        {{-- Billing --}}
        <div class="domain-card">
            <div class="domain-header" style="background:linear-gradient(90deg,#10b981,#34d399);">
                <div class="domain-header-icon" style="background:rgba(255,255,255,.18);">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                </div>
                <span class="domain-header-title">{{ __('overview.billing') }}</span>
                <a href="{{ url('/billing') }}" style="margin-left:auto;font-size:11px;color:rgba(255,255,255,.8);text-decoration:none;font-weight:600;">{{ __('overview.view') }}</a>
            </div>
            <div class="domain-body">
                <div class="mini-kpi-row" style="margin-bottom:.75rem;">
                    <div class="mini-kpi"><div class="mini-kpi-label">{{ __('overview.contracts_count') }}</div><div class="mini-kpi-value">{{ number_format($billingStats['total_cc']) }}</div></div>
                    <div class="mini-kpi"><div class="mini-kpi-label">{{ __('overview.active') }}</div><div class="mini-kpi-value" style="color:#22c55e;">{{ number_format($billingStats['active_cc']) }}</div></div>
                    <div class="mini-kpi"><div class="mini-kpi-label">{{ __('overview.rent') }}</div><div class="mini-kpi-value">{{ number_format($billingStats['rent']) }}</div></div>
                    <div class="mini-kpi"><div class="mini-kpi-label">{{ __('overview.lease') }}</div><div class="mini-kpi-value">{{ number_format($billingStats['lease']) }}</div></div>
                </div>
                <div class="stat-row"><span class="stat-row-label">{{ __('overview.total_amount') }}</span><span class="stat-row-value">{{ number_format($billingStats['total_amount'], 2) }}</span></div>
                <div class="stat-row"><span class="stat-row-label">{{ __('overview.collected') }}</span><span class="stat-row-value" style="color:#10b981;">{{ number_format($billingStats['collected'], 2) }}</span></div>
                <div class="stat-row"><span class="stat-row-label">{{ __('overview.outstanding') }}</span><span class="stat-row-value" style="color:#f59e0b;">{{ number_format($billingStats['outstanding'], 2) }}</span></div>
                <div class="stat-row"><span class="stat-row-label">{{ __('overview.overdue') }}</span><span class="stat-row-value" style="color:#ef4444;">{{ number_format($billingStats['overdue'], 2) }}</span></div>
            </div>
        </div>

    </div>

    {{-- ── Execution Contracts section ─────────────────────────────── --}}
    <div class="card-soft" style="margin-bottom:1rem;">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:.75rem;">
            <div style="font-weight:700;font-size:.9rem;color:#1e293b;display:flex;align-items:center;gap:.5rem;">
                <span style="display:inline-flex;width:24px;height:24px;background:#22c55e;border-radius:6px;align-items:center;justify-content:center;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                </span>
                {{ __('overview.exec_contracts') }}
            </div>
            <a href="{{ url('/contracts') }}" style="font-size:12px;color:#22c55e;text-decoration:none;font-weight:600;">{{ __('overview.view') }}</a>
        </div>
        <div class="contracts-strip">
            <div class="mini-kpi"><div class="mini-kpi-label">{{ __('overview.total') }}</div><div class="mini-kpi-value">{{ number_format($contractStats['total']) }}</div></div>
            <div class="mini-kpi"><div class="mini-kpi-label">{{ __('overview.active') }}</div><div class="mini-kpi-value" style="color:#22c55e;">{{ number_format($contractStats['active']) }}</div></div>
            <div class="mini-kpi"><div class="mini-kpi-label">{{ __('overview.total_value') }}</div><div class="mini-kpi-value" style="font-size:.85rem;">{{ number_format($contractStats['total_value'], 2) }}</div></div>
            <div class="mini-kpi"><div class="mini-kpi-label">{{ __('overview.paid') }}</div><div class="mini-kpi-value" style="font-size:.85rem;color:#10b981;">{{ number_format($contractStats['paid'], 2) }}</div></div>
            <div class="mini-kpi"><div class="mini-kpi-label">{{ __('overview.overdue') }}</div><div class="mini-kpi-value" style="font-size:.85rem;color:#ef4444;">{{ number_format($contractStats['overdue'], 2) }}</div></div>
        </div>
        @if($conByType->isNotEmpty())
            <div style="font-size:11px;color:#64748b;font-weight:600;margin-bottom:.35rem;text-transform:uppercase;letter-spacing:.04em;">{{ __('overview.by_contract_type') }}</div>
            <div class="breakdown-row">
                @foreach($conByType as $c)
                    <span class="bd-pill" style="background:#dcfce7;color:#15803d;">{{ $c['label'] }}: {{ number_format($c['count']) }}</span>
                @endforeach
            </div>
        @endif
    </div>

    {{-- ── Projects + Subscriptions tables ────────────────────────── --}}
    <div class="tables-grid">

        {{-- Projects --}}
        <div class="card-soft" style="padding:0;overflow:hidden;">
            <div style="padding:.75rem 1rem;border-bottom:1px solid #e2e8f0;font-weight:600;font-size:14px;color:#1e293b;">{{ __('overview.projects_rollup') }}</div>
            <div style="overflow-x:auto;">
                <table class="bld-table">
                    <thead>
                        <tr>
                            <th>{{ __('overview.project') }}</th>
                            <th>{{ __('overview.industry') }}</th>
                            <th>{{ __('overview.owner') }}</th>
                            <th>{{ __('overview.properties') }}</th>
                            <th>{{ __('overview.sps') }}</th>
                            <th>{{ __('overview.contract_value') }}</th>
                            <th>{{ __('overview.payment_due') }}</th>
                            <th>{{ __('overview.overdue') }}</th>
                            <th>{{ __('overview.status') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php $palette = ['#6366f1','#22c55e','#f59e0b','#ec4899','#14b8a6','#a855f7','#0ea5e9','#ef4444']; @endphp
                        @foreach ($projects as $ix => $p)
                            @php $color = $palette[$ix % count($palette)]; $initial = strtoupper(mb_substr($p['project_name'] ?? '?', 0, 1)); @endphp
                            <tr>
                                <td>
                                    <div style="display:flex;align-items:center;gap:.5rem;">
                                        <span class="avatar" style="background:{{ $color }};">{{ $initial }}</span>
                                        <div>
                                            <div style="font-weight:600;">{{ $p['project_name'] ?? '' }}</div>
                                            <div style="font-size:11px;color:#94a3b8;">#{{ $p['project_id'] ?? '' }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td>{{ $p['industry_type'] ?? '—' }}</td>
                                <td>{{ $p['owner_name'] ?? '—' }}</td>
                                <td class="num">{{ number_format($p['property_count'] ?? 0) }}</td>
                                <td class="num">{{ number_format($p['sp_count'] ?? 0) }}</td>
                                <td class="num">{{ number_format($p['contract_value'] ?? 0, 2) }}</td>
                                <td class="num">{{ number_format($p['payment_due'] ?? 0, 2) }}</td>
                                <td class="num">
                                    @if (!empty($p['payment_overdue']) && $p['payment_overdue'] > 0)
                                        <span class="pill" style="background:#fee2e2;color:#b91c1c;">{{ number_format($p['payment_overdue'], 0) }}</span>
                                    @else
                                        <span style="color:#94a3b8;">—</span>
                                    @endif
                                </td>
                                <td>
                                    @if (empty($p['is_deleted']))
                                        <span class="pill" style="background:#d1fae5;color:#047857;">{{ __('overview.status_active') }}</span>
                                    @else
                                        <span class="pill" style="background:#f1f5f9;color:#475569;">{{ __('overview.status_inactive') }}</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        @if ($projects->isEmpty())
                            <tr><td colspan="9" style="text-align:center;color:#94a3b8;padding:2rem;">{{ __('overview.no_projects') }}</td></tr>
                        @endif
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Subscriptions --}}
        <div class="card-soft" style="padding:0;overflow:hidden;">
            <div style="padding:.75rem 1rem;border-bottom:1px solid #e2e8f0;font-weight:600;font-size:14px;color:#1e293b;">{{ __('overview.sub_packages') }}</div>
            <div style="overflow-x:auto;">
                <table class="bld-table">
                    <thead>
                        <tr>
                            <th>{{ __('overview.package') }}</th>
                            <th>{{ __('overview.pricing') }}</th>
                            <th>{{ __('overview.price') }}</th>
                            <th>{{ __('overview.discount') }}</th>
                            <th>{{ __('overview.effective') }}</th>
                            <th>{{ __('overview.status') }}</th>
                            <th>{{ __('overview.popular') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($subscriptions as $s)
                            <tr>
                                <td><strong>{{ $s->name }}</strong></td>
                                <td>{{ $s->pricing_model ?? '—' }}</td>
                                <td class="num">{{ number_format($s->price, 2) }}</td>
                                <td class="num">{{ $s->discount > 0 ? number_format($s->discount, 2).'%' : '—' }}</td>
                                <td class="num" style="font-weight:600;color:#047857;">{{ number_format($s->effective_price, 2) }}</td>
                                <td>
                                    <span class="pill" style="background:{{ $s->is_active ? '#d1fae5;color:#047857' : '#f1f5f9;color:#475569' }}">{{ $s->status ?? '—' }}</span>
                                </td>
                                <td>
                                    @if ($s->most_popular)
                                        <span class="pill" style="background:#fef3c7;color:#b45309;">{{ __('overview.popular') }}</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        @if ($subscriptions->isEmpty())
                            <tr><td colspan="7" style="text-align:center;color:#94a3b8;padding:2rem;">{{ __('overview.no_packages') }}</td></tr>
                        @endif
                    </tbody>
                </table>
            </div>
        </div>

    </div>

</div>
@endsection

@section('scripts')
<script>
const PALETTE = ['#6366f1','#22c55e','#f59e0b','#0ea5e9','#a855f7','#ec4899','#14b8a6','#f97316','#10b981','#3b82f6'];
const baseOpts = {
    chart:{ fontFamily:'inherit', toolbar:{ show:false }, animations:{ easing:'easeinout', speed:500 }, dir: IS_RTL ? 'rtl' : 'ltr' },
    grid:{ borderColor:'#e2e8f0', strokeDashArray:4 },
    dataLabels:{ enabled:false },
    tooltip:{ theme:'light' }
};

@if($woByStatus->isNotEmpty())
(function(){
    const el = document.querySelector('#ch_wo_status');
    if (!el) return;
    const data   = @json($woByStatus);
    const labels = data.map(r => r.label);
    const values = data.map(r => r.count);
    new ApexCharts(el, {
        ...baseOpts,
        chart:{ ...baseOpts.chart, type:'donut', height:200 },
        series: values, labels: labels,
        colors: PALETTE.slice(0, labels.length),
        stroke:{ width:0 },
        legend:{ position:'bottom', fontSize:'11px' },
        plotOptions:{ pie:{ donut:{ size:'60%', labels:{ show:true, total:{ show:true, label:'{{ __('overview.total') }}', formatter: w => w.globals.seriesTotals.reduce((a,b) => a+b, 0).toLocaleString() }}}}},
        dataLabels:{ enabled:false },
    }).render();
})();
@endif

@if($userByType->isNotEmpty())
(function(){
    const el = document.querySelector('#ch_user_type');
    if (!el) return;
    const data   = @json($userByType);
    const labels = data.map(r => r.label);
    const values = data.map(r => r.count);
    new ApexCharts(el, {
        ...baseOpts,
        chart:{ ...baseOpts.chart, type:'bar', height: Math.max(180, labels.length * 32) },
        series:[{ name:'{{ __('overview.users') }}', data: values }],
        xaxis:{ categories: labels, labels:{ style:{ fontSize:'11px' }}},
        plotOptions:{ bar:{ horizontal:true, borderRadius:4, barHeight:'60%', distributed:true }},
        colors: PALETTE.slice(0, labels.length),
        legend:{ show:false },
        tooltip:{ y:{ formatter: v => v.toLocaleString() }},
    }).render();
})();
@endif
</script>
@endsection
