@extends('layouts.app')
@section('title', 'Sync Status')

@section('styles')
    .page-bg { background: linear-gradient(180deg,#f1f5f9 0%,#e2e8f0 100%); padding:1.25rem; border-radius:12px; }
    .card-soft { background:#fff; border-radius:14px; box-shadow:0 1px 2px rgba(15,23,42,.04),0 8px 24px -12px rgba(15,23,42,.08); padding:1rem; }
    .pill { display:inline-block; padding:2px 10px; border-radius:9999px; font-size:11px; font-weight:600; }
    .gradient-title { background:linear-gradient(90deg,#10b981,#6366f1,#f59e0b); -webkit-background-clip:text; background-clip:text; color:transparent; font-weight:700; }
    .log-box { background:#0f172a; color:#cbd5e1; padding:1rem; border-radius:10px; font-family: ui-monospace, monospace; font-size:12px; white-space:pre-wrap; max-height:300px; overflow:auto; }
@endsection

@section('content')
<div class="page-bg">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;">
        <div>
            <h2 class="gradient-title" style="font-size:1.75rem;margin:0;">Sync Status</h2>
            <p style="color:#64748b;font-size:.875rem;margin:.25rem 0 0;">30-min ingestion pipeline · Osool-B2G → DWH</p>
        </div>
        <form method="post" action="{{ url('/admin/sync-status/run') }}">
            @csrf
            <button class="btn btn-primary btn-sm" onclick="return confirm('Run a full sync:cycle now?')">▶ Run cycle now</button>
        </form>
    </div>

    <div class="card-soft" style="padding:0;overflow:hidden;margin-bottom:1rem;">
        <div style="overflow-x:auto;">
            <table class="table table-sm mb-0">
                <thead style="background:#f8fafc;color:#64748b;font-size:11px;text-transform:uppercase;">
                <tr>
                    <th>Resource</th><th>Status</th><th>Freshness</th><th>Last Run</th><th>Age</th>
                    <th class="text-right">Upserted</th><th class="text-right">Deleted</th>
                    <th>Cursor</th><th>Last Error</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($rows as $r)
                    @php
                        $freshColors = ['ok'=>['#d1fae5','#047857'],'warn'=>['#fef3c7','#b45309'],'stale'=>['#fee2e2','#b91c1c'],'never'=>['#f1f5f9','#475569']];
                        $fc = $freshColors[$r->freshness];
                        $statusColors = [
                            'ok'=>['#d1fae5','#047857'],
                            'error'=>['#fee2e2','#b91c1c'],
                        ];
                        $sc = $statusColors[$r->last_status ?? 'never'] ?? ['#f1f5f9','#475569'];
                    @endphp
                    <tr>
                        <td><strong>{{ $r->table_name }}</strong></td>
                        <td><span class="pill" style="background:{{ $sc[0] }};color:{{ $sc[1] }};">{{ $r->last_status ?? '—' }}</span></td>
                        <td><span class="pill" style="background:{{ $fc[0] }};color:{{ $fc[1] }};">{{ $r->freshness }}</span></td>
                        <td>{{ $r->last_run_at ?? '—' }}</td>
                        <td>{{ $r->age_minutes === null ? '—' : $r->age_minutes . ' min' }}</td>
                        <td class="text-right">{{ number_format($r->rows_upserted ?? 0) }}</td>
                        <td class="text-right">{{ number_format($r->rows_deleted ?? 0) }}</td>
                        <td style="font-family:ui-monospace,monospace;font-size:11px;color:#64748b;">{{ $r->last_cursor ?? '—' }}</td>
                        <td style="max-width:300px;color:#b91c1c;font-size:11px;">{{ $r->last_error ? Str::limit($r->last_error, 140) : '' }}</td>
                    </tr>
                @endforeach
                @if ($rows->isEmpty())
                    <tr><td colspan="9" class="text-center text-muted py-4">No syncs recorded yet.</td></tr>
                @endif
                </tbody>
            </table>
        </div>
    </div>

    @if ($logTail)
        <div class="card-soft">
            <div style="font-weight:600;margin-bottom:.5rem;">Recent output (tail of sync-cycle.log)</div>
            <div class="log-box">@foreach ($logTail as $line){{ $line }}
@endforeach</div>
        </div>
    @endif
</div>
@endsection
