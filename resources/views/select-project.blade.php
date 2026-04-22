@extends('layouts.app')
@section('title', 'Select Project')

@section('styles')
    .page-bg { background: linear-gradient(180deg,#f1f5f9 0%,#e2e8f0 100%); padding:1.25rem; border-radius:12px; }
    .card-soft { background:#fff; border-radius:14px; box-shadow:0 1px 2px rgba(15,23,42,.04),0 8px 24px -12px rgba(15,23,42,.08); padding:1.25rem; }
    .gradient-title { background:linear-gradient(90deg,#6366f1,#14b8a6,#f59e0b); -webkit-background-clip:text; background-clip:text; color:transparent; font-weight:700; }
    .pill { display:inline-block; padding:2px 8px; border-radius:9999px; font-size:11px; font-weight:600; }
    .grid-projects { display:grid; grid-template-columns:repeat(1,minmax(0,1fr)); gap:1rem; }
    @media (min-width:640px)  { .grid-projects { grid-template-columns:repeat(2,minmax(0,1fr)); } }
    @media (min-width:1024px) { .grid-projects { grid-template-columns:repeat(3,minmax(0,1fr)); } }
    @media (min-width:1280px) { .grid-projects { grid-template-columns:repeat(4,minmax(0,1fr)); } }
    .proj-card { position:relative; display:flex; flex-direction:column; gap:.75rem; padding:1.1rem 1.2rem; background:#fff; border-radius:16px; box-shadow:0 1px 2px rgba(15,23,42,.04),0 10px 28px -16px rgba(15,23,42,.15); transition:transform .15s, box-shadow .15s; border:1px solid #f1f5f9; }
    .proj-card:hover { transform:translateY(-3px); box-shadow:0 1px 2px rgba(15,23,42,.06),0 18px 36px -18px rgba(15,23,42,.25); }
    .proj-card::before { content:""; position:absolute; left:0; top:0; bottom:0; width:5px; border-radius:16px 0 0 16px; }
    .proj-header { display:flex; align-items:center; gap:.75rem; }
    .avatar { width:42px; height:42px; border-radius:12px; display:inline-flex; align-items:center; justify-content:center; font-weight:700; color:#fff; font-size:16px; flex-shrink:0; }
    .proj-name { font-weight:700; font-size:15px; color:#0f172a; line-height:1.2; }
    .proj-sub  { font-size:11px; color:#94a3b8; }
    .stats-row { display:grid; grid-template-columns:repeat(2,1fr); gap:.4rem; font-size:12px; margin-top:.3rem; }
    .stat-label { color:#64748b; font-size:10px; text-transform:uppercase; letter-spacing:.05em; font-weight:600; }
    .stat-value { color:#0f172a; font-weight:700; }
    .enter-btn  { display:inline-flex; align-items:center; justify-content:center; gap:.4rem; padding:.55rem .9rem; border-radius:8px; color:#fff; font-size:13px; font-weight:600; text-align:center; background:linear-gradient(90deg,#6366f1,#8b5cf6); border:none; margin-top:.5rem; text-decoration:none; transition:opacity .15s; }
    .enter-btn:hover { opacity:.9; color:#fff; }
    .search-bar { display:flex; gap:.5rem; margin-bottom:1rem; }
    .search-bar input { flex:1; padding:.55rem .8rem; border:1px solid #e2e8f0; border-radius:10px; font-size:14px; }
    .page-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:1.25rem; flex-wrap:wrap; gap:1rem; }
    .pagination-wrap { margin-top:1.5rem; }
@endsection

@section('content')
<div class="page-bg">
    <div class="page-header">
        <div>
            <h2 class="gradient-title" style="font-size:1.75rem;margin:0;">Select a Project</h2>
            <p style="color:#64748b;font-size:.875rem;margin:.25rem 0 0;">Pick a project to open its scoped dashboards</p>
        </div>
        <div style="font-size:13px;color:#64748b;">
            <strong style="color:#0f172a;">{{ $projects->total() }}</strong> projects
        </div>
    </div>

    <form method="get" class="search-bar">
        <input type="text" name="search" value="{{ $search }}" placeholder="Search by project name…">
        <button class="btn btn-primary">Search</button>
        @if ($search)
            <a href="{{ url('/select-project') }}" class="btn btn-outline-secondary">Clear</a>
        @endif
    </form>

    @php $palette = ['#6366f1','#14b8a6','#f59e0b','#ec4899','#22c55e','#a855f7','#0ea5e9','#ef4444','#f97316','#10b981','#8b5cf6','#06b6d4']; @endphp

    @if ($projects->isEmpty())
        <div class="card-soft text-center text-muted" style="padding:3rem;">
            <div style="font-size:40px;">🗂️</div>
            <div style="font-weight:600;color:#0f172a;margin-top:.5rem;">No projects found</div>
            <div style="font-size:13px;">Try a different search or clear filters.</div>
        </div>
    @else
        <div class="grid-projects">
            @foreach ($projects as $ix => $p)
                @php
                    $color   = $palette[($ix + ($projects->currentPage() - 1) * $projects->perPage()) % count($palette)];
                    $initial = strtoupper(mb_substr($p['project_name'] ?? '?', 0, 1));
                    $modules = array_filter([
                        ($p['use_erp_module']        ?? false) ? 'ERP'        : null,
                        ($p['use_crm_module']        ?? false) ? 'CRM'        : null,
                        ($p['use_tenant_module']     ?? false) ? 'Tenant'     : null,
                        ($p['use_beneficiary_module']?? false) ? 'Beneficiary': null,
                    ]);
                @endphp
                <div class="proj-card" style="--accent:{{ $color }}; ">
                    <style>.proj-card[style*="{{ $color }}"]::before { background: {{ $color }}; }</style>
                    <div class="proj-header">
                        <span class="avatar" style="background:{{ $color }};">{{ $initial }}</span>
                        <div style="min-width:0;">
                            <div class="proj-name" style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ $p['project_name'] ?? 'Unnamed' }}</div>
                            <div class="proj-sub">#{{ $p['project_id'] }} · {{ $p['industry_type'] ?? '—' }}</div>
                        </div>
                    </div>

                    @if ($modules)
                        <div style="display:flex;flex-wrap:wrap;gap:.3rem;">
                            @foreach ($modules as $m)
                                <span class="pill" style="background:#eef2ff;color:#4338ca;">{{ $m }}</span>
                            @endforeach
                        </div>
                    @endif

                    <div class="stats-row">
                        <div><div class="stat-label">Properties</div><div class="stat-value">{{ number_format($p['property_count'] ?? 0) }}</div></div>
                        <div><div class="stat-label">Providers</div> <div class="stat-value">{{ number_format($p['sp_count']       ?? 0) }}</div></div>
                        <div><div class="stat-label">Contract Value</div><div class="stat-value">{{ number_format($p['contract_value'] ?? 0, 0) }}</div></div>
                        <div>
                            <div class="stat-label">Overdue</div>
                            <div class="stat-value" style="color:{{ ($p['payment_overdue'] ?? 0) > 0 ? '#b91c1c' : '#0f172a' }};">
                                {{ number_format($p['payment_overdue'] ?? 0, 0) }}
                            </div>
                        </div>
                    </div>

                    <form method="post" action="{{ url('/select-project/' . $p['project_id']) }}">
                        @csrf
                        <button class="enter-btn" style="width:100%;">Enter project →</button>
                    </form>
                </div>
            @endforeach
        </div>

        <div class="pagination-wrap d-flex justify-content-center">
            {{ $projects->links('pagination::bootstrap-5') }}
        </div>
    @endif
</div>
@endsection
