@extends('layouts.app')
@section('title', 'Project Dashboard')

@section('styles')
    .page-bg { background: linear-gradient(180deg,#f1f5f9 0%,#e2e8f0 100%); padding:1.25rem; border-radius:12px; }
    .card-soft { background:#fff; border-radius:14px; box-shadow:0 1px 2px rgba(15,23,42,.04),0 8px 24px -12px rgba(15,23,42,.08); padding:1rem; }
    .header-card { background:linear-gradient(135deg,#6366f1 0%,#8b5cf6 50%,#ec4899 100%); color:#fff; padding:1.5rem; border-radius:16px; }
    .header-avatar { width:64px; height:64px; border-radius:16px; background:rgba(255,255,255,.22); display:inline-flex; align-items:center; justify-content:center; font-weight:700; font-size:28px; color:#fff; }
    .pill { display:inline-block; padding:2px 10px; border-radius:9999px; font-size:11px; font-weight:600; }
    .kpi { position:relative; overflow:hidden; padding:1rem 1rem 1rem 1.25rem; }
    .kpi::before { content:""; position:absolute; left:0; top:0; bottom:0; width:4px; }
    .kpi-label { font-size:11px; text-transform:uppercase; color:#64748b; font-weight:600; }
    .kpi-value { font-size:1.5rem; font-weight:700; color:#0f172a; margin-top:.25rem; }
    .o1::before{background:#6366f1}.o2::before{background:#f59e0b}.o3::before{background:#22c55e}.o4::before{background:#ec4899}
    .f1::before{background:#22c55e}.f2::before{background:#0ea5e9}.f3::before{background:#f59e0b}.f4::before{background:#ef4444}
    .f5::before{background:#a855f7}.f6::before{background:#14b8a6}.f7::before{background:#f97316}
    .grid-cards { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:.75rem; }
    @media (min-width:768px) { .grid-cards-4 { grid-template-columns:repeat(4,minmax(0,1fr)); } .grid-cards-7 { grid-template-columns:repeat(7,minmax(0,1fr)); } }
    .dash-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:1rem; }
    @media (min-width:768px) { .dash-grid { grid-template-columns:repeat(3,minmax(0,1fr)); } }
    .dash-btn { display:flex; align-items:center; gap:.8rem; padding:1.1rem 1.2rem; border-radius:14px; text-decoration:none; color:#0f172a; background:#fff; border:1px solid #f1f5f9; transition:transform .15s, box-shadow .15s; box-shadow:0 1px 2px rgba(15,23,42,.04),0 8px 24px -14px rgba(15,23,42,.12); }
    .dash-btn:hover { transform:translateY(-2px); box-shadow:0 1px 2px rgba(15,23,42,.06),0 16px 30px -18px rgba(15,23,42,.22); color:#0f172a; text-decoration:none; }
    .dash-btn .icon { width:44px; height:44px; border-radius:12px; display:inline-flex; align-items:center; justify-content:center; color:#fff; flex-shrink:0; }
    .dash-title { font-weight:700; font-size:15px; }
    .dash-sub   { font-size:11.5px; color:#64748b; }
@endsection

@section('content')
<div class="page-bg">
    @php
        $p = $project ?? [];
        $modules = array_filter([
            ($p['use_erp_module']         ?? false) ? 'ERP'         : null,
            ($p['use_crm_module']         ?? false) ? 'CRM'         : null,
            ($p['use_tenant_module']      ?? false) ? 'Tenant'      : null,
            ($p['use_beneficiary_module'] ?? false) ? 'Beneficiary' : null,
        ]);
    @endphp

    <div class="header-card mb-3">
        <div style="display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap;">
            <div style="display:flex;align-items:center;gap:1rem;">
                <span class="header-avatar">{{ strtoupper(mb_substr($p['project_name'] ?? '?', 0, 1)) }}</span>
                <div>
                    <div style="opacity:.85;font-size:12px;text-transform:uppercase;letter-spacing:.08em;">Project #{{ $p['project_id'] ?? '' }}</div>
                    <h2 style="margin:0;font-weight:700;font-size:1.75rem;">{{ $p['project_name'] ?? 'Unnamed' }}</h2>
                    <div style="margin-top:.35rem;opacity:.9;font-size:13px;">
                        {{ $p['industry_type'] ?? '—' }}
                        @if (!empty($p['owner_name'])) · Owned by <strong>{{ $p['owner_name'] }}</strong> @endif
                        @if (!empty($p['contract_start_date'])) · {{ $p['contract_start_date'] }} → {{ $p['contract_end_date'] ?? '—' }} @endif
                    </div>
                    @if ($modules)
                        <div style="margin-top:.6rem;display:flex;flex-wrap:wrap;gap:.3rem;">
                            @foreach ($modules as $m)<span class="pill" style="background:rgba(255,255,255,.25);color:#fff;">{{ $m }}</span>@endforeach
                        </div>
                    @endif
                </div>
            </div>
            <form method="post" action="{{ url('/exit-project') }}">
                @csrf
                <button class="btn btn-light btn-sm" style="font-weight:600;">✕ Exit Project</button>
            </form>
        </div>
    </div>

    {{-- Overview cards --}}
    <div class="grid-cards grid-cards-4 mb-3">
        @php $i=0; @endphp
        @foreach ($cards['overview'] as $label => $value)
            @php $i++; @endphp
            <div class="card-soft kpi o{{ $i }}">
                <div class="kpi-label">{{ $label }}</div>
                <div class="kpi-value">{{ number_format($value) }}</div>
            </div>
        @endforeach
    </div>

    {{-- Financial cards --}}
    <div class="grid-cards grid-cards-7 mb-3">
        @php $i=0; @endphp
        @foreach ($cards['financial'] as $label => $value)
            @php $i++; @endphp
            <div class="card-soft kpi f{{ $i }}">
                <div class="kpi-label">{{ $label }}</div>
                <div class="kpi-value">{{ number_format($value, 2) }}</div>
            </div>
        @endforeach
    </div>

    {{-- Enter dashboards --}}
    <div class="card-soft mb-3">
        <h5 style="margin:0 0 1rem 0;font-weight:700;color:#0f172a;">Project Dashboards</h5>
        <div class="dash-grid">
            @php
                $dashes = [
                    ['/work-orders','clipboard','#6366f1','Work Orders','Preventive, reactive, SLA'],
                    ['/properties', 'home',     '#0ea5e9','Properties','Portfolio & contract coverage'],
                    ['/assets',     'package',  '#14b8a6','Assets',    'Inventory, warranty, cost'],
                    ['/users',      'users',    '#22c55e','Users',     'Team members in this project'],
                    ['/billing',    'dollar-sign','#f59e0b','Billing',  'Receivables, aging & collections'],
                    ['/contracts',  'file-text','#ec4899','Contracts', 'Service providers & payments'],
                ];
            @endphp
            @foreach ($dashes as [$url, $icon, $color, $title, $sub])
                <a href="{{ url($url) }}" class="dash-btn">
                    <span class="icon" style="background:{{ $color }};"><span data-feather="{{ $icon }}"></span></span>
                    <div>
                        <div class="dash-title">{{ $title }}</div>
                        <div class="dash-sub">{{ $sub }}</div>
                    </div>
                </a>
            @endforeach
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    if (typeof feather !== 'undefined') feather.replace();
</script>
@endsection
