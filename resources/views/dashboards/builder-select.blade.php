@extends('layouts.app')
@section('title', __('builder.title'))

@section('styles')
    .page-bg { background: linear-gradient(180deg,#f1f5f9 0%,#e2e8f0 100%); padding: 1.25rem; border-radius: 12px; }
    .gradient-title { background:linear-gradient(90deg,#6366f1,#d946ef,#f43f5e); -webkit-background-clip:text; background-clip:text; color:transparent; font-weight:700; }
    .sel-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(240px,1fr)); gap:1.5rem; max-width:1100px; margin:2.5rem auto 0; }
    @media (max-width:560px)  { .sel-grid { grid-template-columns:1fr; } }
    .sel-card { background:#fff; border-radius:18px; box-shadow:0 1px 2px rgba(15,23,42,.04),0 8px 24px -12px rgba(15,23,42,.10); padding:2rem 1.75rem 2.5rem; cursor:pointer; text-decoration:none; display:block; border:2px solid transparent; transition:all .2s; position:relative; overflow:hidden; }
    .sel-card:hover { border-color:var(--c); box-shadow:0 0 0 4px color-mix(in srgb,var(--c) 10%,transparent),0 8px 30px -8px rgba(15,23,42,.14); transform:translateY(-3px); }
    .sel-card-icon { width:56px; height:56px; border-radius:14px; display:flex; align-items:center; justify-content:center; margin-bottom:1.25rem; }
    .sel-card-title { font-size:1.2rem; font-weight:700; color:#0f172a; margin-bottom:.45rem; }
    .sel-card-desc { font-size:13px; color:#64748b; line-height:1.6; }
    .sel-card-features { margin-top:1rem; display:flex; flex-wrap:wrap; gap:.4rem; }
    .sel-feat { font-size:11px; font-weight:600; padding:2px 9px; border-radius:999px; background:#f1f5f9; color:#475569; }
    .sel-card-arrow { position:absolute; bottom:1.2rem; right:1.3rem; font-size:1.1rem; color:var(--c); opacity:0; transition:opacity .2s,transform .2s; font-weight:700; }
    [dir="rtl"] .sel-card-arrow { right:auto; left:1.3rem; }
    .sel-card:hover .sel-card-arrow { opacity:1; transform:translateX(4px); }
    [dir="rtl"] .sel-card:hover .sel-card-arrow { transform:translateX(-4px); }
@endsection

@section('content')
<div class="page-bg">

    <div style="text-align:center;padding-top:1.5rem;">
        <h2 class="gradient-title" style="font-size:2rem;margin:0;">{{ __('builder.select_heading') }}</h2>
        <p style="color:#64748b;font-size:.9rem;margin:.4rem 0 0;">{{ __('builder.select_sub') }}</p>
    </div>

    <div class="sel-grid">

        {{-- Work Orders --}}
        <a href="{{ route('dashboard.builder', ['type' => 'work-orders']) }}"
           class="sel-card"
           style="--c:#6366f1;">
            <div class="sel-card-icon" style="background:#eef2ff;">
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#6366f1" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="2" y="3" width="20" height="14" rx="2" ry="2"/>
                    <line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/>
                    <line x1="7" y1="8" x2="17" y2="8"/><line x1="7" y1="12" x2="13" y2="12"/>
                </svg>
            </div>
            <div class="sel-card-title">{{ __('builder.wo_title') }}</div>
            <div class="sel-card-desc">{{ __('builder.wo_desc') }}</div>
            <div class="sel-card-features">
                <span class="sel-feat">{{ __('builder.wo_feat_kpi') }}</span>
                <span class="sel-feat">{{ __('builder.wo_feat_charts') }}</span>
                <span class="sel-feat">{{ __('builder.wo_feat_filters') }}</span>
                <span class="sel-feat">{{ __('builder.wo_feat_tables') }}</span>
            </div>
            <div class="sel-card-arrow">&rarr;</div>
        </a>

        {{-- Properties --}}
        <a href="{{ route('dashboard.builder', ['type' => 'properties']) }}"
           class="sel-card"
           style="--c:#0ea5e9;">
            <div class="sel-card-icon" style="background:#e0f2fe;">
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#0ea5e9" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                    <polyline points="9 22 9 12 15 12 15 22"/>
                </svg>
            </div>
            <div class="sel-card-title">{{ __('builder.props_title') }}</div>
            <div class="sel-card-desc">{{ __('builder.props_desc') }}</div>
            <div class="sel-card-features">
                <span class="sel-feat">{{ __('builder.props_feat_kpi') }}</span>
                <span class="sel-feat">{{ __('builder.props_feat_charts') }}</span>
                <span class="sel-feat">{{ __('builder.props_feat_filters') }}</span>
                <span class="sel-feat">{{ __('builder.props_feat_tables') }}</span>
            </div>
            <div class="sel-card-arrow">&rarr;</div>
        </a>

        {{-- Billing --}}
        <a href="{{ route('dashboard.builder', ['type' => 'billing']) }}"
           class="sel-card"
           style="--c:#10b981;">
            <div class="sel-card-icon" style="background:#d1fae5;">
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                </svg>
            </div>
            <div class="sel-card-title">{{ __('builder.billing_title') }}</div>
            <div class="sel-card-desc">{{ __('builder.billing_desc') }}</div>
            <div class="sel-card-features">
                <span class="sel-feat">{{ __('builder.billing_feat_kpi') }}</span>
                <span class="sel-feat">{{ __('builder.billing_feat_charts') }}</span>
                <span class="sel-feat">{{ __('builder.billing_feat_overdue') }}</span>
                <span class="sel-feat">{{ __('builder.billing_feat_upcoming') }}</span>
            </div>
            <div class="sel-card-arrow">&rarr;</div>
        </a>

        {{-- Users --}}
        <a href="{{ route('dashboard.builder', ['type' => 'users']) }}"
           class="sel-card"
           style="--c:#6366f1;">
            <div class="sel-card-icon" style="background:#eef2ff;">
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#6366f1" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                    <circle cx="9" cy="7" r="4"/>
                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                </svg>
            </div>
            <div class="sel-card-title">{{ __('builder.users_title') }}</div>
            <div class="sel-card-desc">{{ __('builder.users_desc') }}</div>
            <div class="sel-card-features">
                <span class="sel-feat">{{ __('builder.users_feat_kpi') }}</span>
                <span class="sel-feat">{{ __('builder.users_feat_charts') }}</span>
                <span class="sel-feat">{{ __('builder.users_feat_filters') }}</span>
                <span class="sel-feat">{{ __('builder.users_feat_tables') }}</span>
            </div>
            <div class="sel-card-arrow">&rarr;</div>
        </a>

        {{-- Assets --}}
        <a href="{{ route('dashboard.builder', ['type' => 'assets']) }}"
           class="sel-card"
           style="--c:#14b8a6;">
            <div class="sel-card-icon" style="background:#ccfbf1;">
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#14b8a6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="2" y="7" width="20" height="14" rx="2" ry="2"/>
                    <path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/>
                    <line x1="12" y1="12" x2="12" y2="16"/><line x1="10" y1="14" x2="14" y2="14"/>
                </svg>
            </div>
            <div class="sel-card-title">{{ __('builder.assets_title') }}</div>
            <div class="sel-card-desc">{{ __('builder.assets_desc') }}</div>
            <div class="sel-card-features">
                <span class="sel-feat">{{ __('builder.assets_feat_kpi') }}</span>
                <span class="sel-feat">{{ __('builder.assets_feat_charts') }}</span>
                <span class="sel-feat">{{ __('builder.assets_feat_filters') }}</span>
                <span class="sel-feat">{{ __('builder.assets_feat_tables') }}</span>
            </div>
            <div class="sel-card-arrow">&rarr;</div>
        </a>

        {{-- Contracts --}}
        <a href="{{ route('dashboard.builder', ['type' => 'contracts']) }}"
           class="sel-card"
           style="--c:#22c55e;">
            <div class="sel-card-icon" style="background:#dcfce7;">
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#22c55e" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                    <polyline points="14 2 14 8 20 8"/>
                    <line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/>
                    <polyline points="10 9 9 9 8 9"/>
                </svg>
            </div>
            <div class="sel-card-title">{{ __('builder.contracts_title') }}</div>
            <div class="sel-card-desc">{{ __('builder.contracts_desc') }}</div>
            <div class="sel-card-features">
                <span class="sel-feat">{{ __('builder.contracts_feat_kpi') }}</span>
                <span class="sel-feat">{{ __('builder.contracts_feat_charts') }}</span>
                <span class="sel-feat">{{ __('builder.contracts_feat_filters') }}</span>
                <span class="sel-feat">{{ __('builder.contracts_feat_tables') }}</span>
            </div>
            <div class="sel-card-arrow">&rarr;</div>
        </a>

        {{-- Overview --}}
        <a href="{{ route('dashboard.builder', ['type' => 'overview']) }}"
           class="sel-card"
           style="--c:#f59e0b;">
            <div class="sel-card-icon" style="background:#fef3c7;">
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/>
                    <rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>
                </svg>
            </div>
            <div class="sel-card-title">{{ __('builder.overview_title') }}</div>
            <div class="sel-card-desc">{{ __('builder.overview_desc') }}</div>
            <div class="sel-card-features">
                <span class="sel-feat">{{ __('builder.overview_feat_kpi') }}</span>
                <span class="sel-feat">{{ __('builder.overview_feat_charts') }}</span>
                <span class="sel-feat">{{ __('builder.overview_feat_domains') }}</span>
                <span class="sel-feat">{{ __('builder.overview_feat_filter') }}</span>
            </div>
            <div class="sel-card-arrow">&rarr;</div>
        </a>

    </div>

</div>
@endsection
