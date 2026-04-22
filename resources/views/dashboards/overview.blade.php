@extends('layouts.app')
@section('title', 'Overview Dashboard')

@section('styles')
    .page-bg { background: linear-gradient(180deg,#f1f5f9 0%,#e2e8f0 100%); padding:1.25rem; border-radius:12px; }
    .card-soft { background:#fff; border-radius:14px; box-shadow:0 1px 2px rgba(15,23,42,.04),0 8px 24px -12px rgba(15,23,42,.08); padding:1rem; }
    .kpi { position:relative; overflow:hidden; padding-left:1.25rem; }
    .kpi::before { content:""; position:absolute; left:0; top:0; bottom:0; width:4px; }
    .k1::before { background:#6366f1; } .k2::before { background:#22c55e; } .k3::before { background:#94a3b8; }
    .k4::before { background:#0ea5e9; } .k5::before { background:#14b8a6; } .k6::before { background:#a855f7; }
    .k7::before { background:#f59e0b; } .k8::before { background:#10b981; } .k9::before { background:#ec4899; } .k10::before { background:#ef4444; }
    .kpi-label { font-size:11px; text-transform:uppercase; color:#64748b; font-weight:600; }
    .kpi-value { font-size:1.25rem; font-weight:700; color:#0f172a; margin-top:.25rem; }
    .pill { display:inline-block; padding:2px 8px; border-radius:9999px; font-size:11px; font-weight:600; }
    .grid-cards { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:.75rem; }
    @media (min-width:768px) { .grid-cards { grid-template-columns:repeat(5,minmax(0,1fr)); } }
    .grid-two { display:grid; grid-template-columns:1fr; gap:1rem; }
    @media (min-width:1024px) { .grid-two { grid-template-columns:2fr 1fr; } }
    .gradient-title { background:linear-gradient(90deg,#6366f1,#14b8a6,#f59e0b); -webkit-background-clip:text; background-clip:text; color:transparent; font-weight:700; }
    .avatar { width:34px; height:34px; border-radius:9999px; display:inline-flex; align-items:center; justify-content:center; font-weight:700; color:#fff; font-size:13px; }
@endsection

@section('content')
<div class="page-bg">
    <div style="margin-bottom:1rem;">
        <h2 class="gradient-title" style="font-size:1.75rem;margin:0;">Platform Overview</h2>
        <p style="color:#64748b;font-size:.875rem;margin:.25rem 0 0;">Executive summary across every project & subscription</p>
    </div>

    <div class="grid-cards mb-3">
        @php $i=0; @endphp
        @foreach ($cards as $label => $value)
            @php $i++; @endphp
            <div class="card-soft kpi k{{ $i }}">
                <div class="kpi-label">{{ $label }}</div>
                <div class="kpi-value">{{ is_numeric($value) ? number_format($value, str_contains($label,'Value')||str_contains($label,'Due')?2:0) : $value }}</div>
            </div>
        @endforeach
    </div>

    <div class="grid-two mb-3">
        {{-- Projects --}}
        <div class="card-soft" style="padding:0;overflow:hidden;">
            <div style="padding:.75rem 1rem;border-bottom:1px solid #e2e8f0;font-weight:600;">Projects rollup</div>
            <div style="overflow-x:auto;">
                <table class="table table-sm mb-0">
                    <thead style="background:#f8fafc;color:#64748b;font-size:11px;text-transform:uppercase;">
                    <tr>@foreach (['Project','Industry','Owner','Properties','SPs','Contract Value','Payment Due','Overdue','Status'] as $h)<th>{{ $h }}</th>@endforeach</tr>
                    </thead>
                    <tbody>
                    @php
                        $palette = ['#6366f1','#22c55e','#f59e0b','#ec4899','#14b8a6','#a855f7','#0ea5e9','#ef4444'];
                    @endphp
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
                            <td class="text-right">{{ number_format($p['property_count'] ?? 0) }}</td>
                            <td class="text-right">{{ number_format($p['sp_count'] ?? 0) }}</td>
                            <td class="text-right">{{ number_format($p['contract_value'] ?? 0, 2) }}</td>
                            <td class="text-right">{{ number_format($p['payment_due'] ?? 0, 2) }}</td>
                            <td class="text-right">
                                @if (!empty($p['payment_overdue']) && $p['payment_overdue'] > 0)
                                    <span class="pill" style="background:#fee2e2;color:#b91c1c;">{{ number_format($p['payment_overdue'], 0) }}</span>
                                @else
                                    <span style="color:#94a3b8;">—</span>
                                @endif
                            </td>
                            <td>
                                @if (empty($p['is_deleted']))
                                    <span class="pill" style="background:#d1fae5;color:#047857;">Active</span>
                                @else
                                    <span class="pill" style="background:#f1f5f9;color:#475569;">Inactive</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    @if ($projects->isEmpty()) <tr><td colspan="9" class="text-center text-muted py-4">No projects.</td></tr> @endif
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Subscriptions --}}
        <div class="card-soft" style="padding:0;overflow:hidden;">
            <div style="padding:.75rem 1rem;border-bottom:1px solid #e2e8f0;font-weight:600;">Subscription packages</div>
            <div style="overflow-x:auto;">
                <table class="table table-sm mb-0">
                    <thead style="background:#f8fafc;color:#64748b;font-size:11px;text-transform:uppercase;">
                    <tr>@foreach (['Package','Pricing','Price','Discount','Effective','Status','Popular'] as $h)<th>{{ $h }}</th>@endforeach</tr>
                    </thead>
                    <tbody>
                    @foreach ($subscriptions as $s)
                        <tr>
                            <td><strong>{{ $s->name }}</strong></td>
                            <td>{{ $s->pricing_model ?? '—' }}</td>
                            <td class="text-right">{{ number_format($s->price, 2) }}</td>
                            <td class="text-right">{{ $s->discount > 0 ? number_format($s->discount, 2).'%' : '—' }}</td>
                            <td class="text-right" style="font-weight:600;color:#047857;">{{ number_format($s->effective_price, 2) }}</td>
                            <td>
                                <span class="pill" style="background:{{ $s->is_active?'#d1fae5;color:#047857':'#f1f5f9;color:#475569' }}">{{ $s->status ?? '—' }}</span>
                            </td>
                            <td>
                                @if ($s->most_popular)
                                    <span class="pill" style="background:#fef3c7;color:#b45309;">★ Popular</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    @if ($subscriptions->isEmpty()) <tr><td colspan="7" class="text-center text-muted py-4">No packages.</td></tr> @endif
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
