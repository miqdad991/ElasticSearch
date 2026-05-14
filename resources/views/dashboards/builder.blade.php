@extends('layouts.app')
@section('title', __('builder.title'))

@section('styles')
    .page-bg { background: linear-gradient(180deg,#f1f5f9 0%,#e2e8f0 100%); padding: 1.25rem; border-radius: 12px; }
    .card-soft { background:#fff; border-radius:14px; box-shadow: 0 1px 2px rgba(15,23,42,.04), 0 8px 24px -12px rgba(15,23,42,.08); padding:1rem; }
    .gradient-title { background:linear-gradient(90deg,#6366f1,#d946ef,#f43f5e); -webkit-background-clip:text; background-clip:text; color:transparent; font-weight:700; }

    /* Layout */
    .bld-grid { display:grid; grid-template-columns:60% 40%; gap:1rem; margin-bottom:1rem; }
    .bld-bottom { display:grid; grid-template-columns:1fr 1fr; gap:1rem; margin-bottom:1rem; }
    @media (max-width:900px) {
        .bld-grid { grid-template-columns:1fr; }
        .bld-bottom { grid-template-columns:1fr; }
    }

    /* Section headers */
    .bld-section-title { font-size:13px; font-weight:700; text-transform:uppercase; letter-spacing:.06em; color:#475569; margin-bottom:.75rem; display:flex; align-items:center; gap:.5rem; }
    .bld-badge { background:#6366f1; color:#fff; border-radius:999px; font-size:11px; padding:1px 8px; font-weight:700; min-width:22px; text-align:center; }

    /* KPI toggle cards */
    .bld-kpi-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:.5rem; }
    .bld-kpi-card { position:relative; border:1.5px solid #e2e8f0; border-radius:10px; padding:.55rem .65rem .55rem 1rem; cursor:pointer; transition:all .15s; background:#f8fafc; border-left-width:4px; user-select:none; }
    [dir="rtl"] .bld-kpi-card { padding:.55rem 1rem .55rem .65rem; border-left-width:1.5px; border-right-width:4px; }
    .bld-kpi-card:hover { background:#f1f5f9; }
    .bld-kpi-card input[type=checkbox] { position:absolute; opacity:0; width:0; height:0; }
    .bld-kpi-card .kpi-name { font-size:12px; font-weight:600; color:#475569; line-height:1.3; }
    .bld-kpi-card .kpi-check { position:absolute; top:6px; right:8px; width:16px; height:16px; border-radius:50%; background:#e2e8f0; display:flex; align-items:center; justify-content:center; font-size:9px; color:#fff; transition:background .15s; }
    [dir="rtl"] .bld-kpi-card .kpi-check { right:auto; left:8px; }
    .bld-kpi-card.checked { background:#fafafe; }
    .bld-kpi-card.checked .kpi-check { background:#6366f1; }
    .bld-kpi-card.checked .kpi-check::after { content:'✓'; }

    /* Chart rows */
    .bld-chart-list { display:flex; flex-direction:column; gap:.4rem; }
    .bld-chart-row { display:flex; align-items:center; gap:.75rem; padding:.55rem .75rem; border:1.5px solid #e2e8f0; border-radius:10px; background:#f8fafc; transition:background .15s; }
    .bld-chart-row.enabled { background:#fafafe; border-color:#c7d2fe; }
    .bld-chart-row .chart-name { font-size:13px; font-weight:600; color:#334155; flex:1; }
    .type-pills { display:flex; gap:.3rem; }
    .type-pill { position:relative; }
    .type-pill input[type=radio] { position:absolute; opacity:0; width:0; height:0; }
    .type-pill label { font-size:11px; font-weight:600; padding:.2rem .55rem; border-radius:999px; border:1.5px solid #e2e8f0; cursor:pointer; color:#64748b; background:#fff; transition:all .15s; white-space:nowrap; }
    .type-pill input[type=radio]:checked + label { background:#6366f1; border-color:#6366f1; color:#fff; }
    .bld-chart-row:not(.enabled) .type-pills { opacity:.4; pointer-events:none; }

    /* Toggle switch */
    .bld-toggle-wrap { display:flex; align-items:center; gap:.6rem; cursor:pointer; }
    .bld-toggle { position:relative; width:36px; height:20px; flex-shrink:0; }
    .bld-toggle input { opacity:0; width:0; height:0; position:absolute; }
    .bld-toggle-slider { position:absolute; inset:0; background:#cbd5e1; border-radius:999px; transition:background .2s; }
    .bld-toggle-slider::before { content:''; position:absolute; width:14px; height:14px; left:3px; top:3px; background:#fff; border-radius:50%; transition:transform .2s; box-shadow:0 1px 3px rgba(0,0,0,.15); }
    .bld-toggle input:checked ~ .bld-toggle-slider { background:#6366f1; }
    .bld-toggle input:checked ~ .bld-toggle-slider::before { transform:translateX(16px); }
    .bld-toggle-label { font-size:13px; color:#334155; font-weight:500; }

    /* Col pills */
    .col-pills { display:flex; gap:.4rem; }
    .col-pill { position:relative; }
    .col-pill input[type=radio] { position:absolute; opacity:0; width:0; height:0; }
    .col-pill label { font-size:13px; font-weight:600; padding:.35rem .8rem; border-radius:999px; border:1.5px solid #e2e8f0; cursor:pointer; color:#64748b; background:#fff; transition:all .15s; }
    .col-pill input[type=radio]:checked + label { background:#6366f1; border-color:#6366f1; color:#fff; }

    /* Name input */
    .bld-name-input { width:100%; padding:.55rem .85rem; border:1.5px solid #e2e8f0; border-radius:10px; font-size:15px; font-weight:600; color:#1e293b; outline:none; transition:border-color .15s; background:#f8fafc; }
    .bld-name-input:focus { border-color:#6366f1; box-shadow:0 0 0 3px rgba(99,102,241,.1); background:#fff; }

    /* Extras checkboxes */
    .extras-list { display:flex; flex-direction:column; gap:.6rem; }

    /* Submit bar */
    .bld-submit-bar { display:flex; align-items:center; justify-content:space-between; padding:.75rem 1rem; background:#fff; border-radius:12px; box-shadow:0 1px 2px rgba(15,23,42,.04), 0 8px 24px -12px rgba(15,23,42,.08); margin-bottom:1rem; }
    .bld-counter { font-size:13px; color:#64748b; }
    .bld-counter strong { color:#1e293b; }
@endsection

@section('content')
<div class="page-bg">

    {{-- Header --}}
    @php $typeSlug = str_replace('-', '_', $type); @endphp
    <div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:1rem;">
        <div>
            <a href="{{ route('dashboard.builder.select') }}" style="font-size:12px;color:#6366f1;text-decoration:none;font-weight:600;">{{ __('builder.back_to_types') }}</a>
            <h2 class="gradient-title" style="font-size:1.75rem;margin:.25rem 0 0;">
                @if(app()->isLocale('ar'))
                    {{ __('builder.dashboard_builder_suffix') }}: {{ __("builder.type_{$typeSlug}") }}
                @else
                    {{ __("builder.type_{$typeSlug}") }} {{ __('builder.dashboard_builder_suffix') }}
                @endif
            </h2>
            <p style="color:#64748b;font-size:.875rem;margin:.25rem 0 0;">{{ __('builder.builder_sub') }}</p>
        </div>
        <button form="bld-form" type="submit" class="btn btn-primary btn-lg" style="white-space:nowrap;">
            {{ __('builder.preview_btn') }}
        </button>
    </div>

    <form id="bld-form" method="POST" action="{{ route('dashboard.builder.save', $type) }}">
        @csrf

        {{-- Dashboard name --}}
        <div class="card-soft" style="margin-bottom:1rem;">
            <label style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#64748b;display:block;margin-bottom:.4rem;">{{ __('builder.name_label') }}</label>
            <input class="bld-name-input" type="text" name="name" value="{{ $config['name'] }}" placeholder="{{ __('builder.name_placeholder') }}">
        </div>

        {{-- Two-column: KPIs + Charts --}}
        <div class="bld-grid">

            {{-- Left: KPIs --}}
            <div class="card-soft">
                <div class="bld-section-title">
                    <span>{{ __('builder.kpi_section') }}</span>
                    <span class="bld-badge" id="kpi-count">0</span>
                </div>
                <div class="bld-kpi-grid">
                    @foreach($kpiOptions as $key => $opt)
                        @php $isChecked = in_array($key, $config['kpis'] ?? []); @endphp
                        <label class="bld-kpi-card {{ $isChecked ? 'checked' : '' }}"
                               style="border-left-color:{{ $opt['color'] }};"
                               data-kpi-card>
                            <input type="checkbox" name="kpis[]" value="{{ $key }}" {{ $isChecked ? 'checked' : '' }}>
                            <div class="kpi-name">{{ $opt['label'] }}</div>
                            <div class="kpi-check"></div>
                        </label>
                    @endforeach
                </div>
            </div>

            {{-- Right: Charts --}}
            <div class="card-soft">
                <div class="bld-section-title">
                    <span>{{ __('builder.charts_section') }}</span>
                    <span class="bld-badge" id="chart-count">0</span>
                </div>
                <div class="bld-chart-list">
                    @foreach($chartOptions as $key => $opt)
                        @php
                            $cfg     = $config['charts'][$key] ?? ['enabled' => false, 'type' => $opt['types'][0]];
                            $enabled = $cfg['enabled'];
                            $selType = $cfg['type'];
                        @endphp
                        <div class="bld-chart-row {{ $enabled ? 'enabled' : '' }}" data-chart-row>
                            <label class="bld-toggle" title="{{ __('builder.charts_section') }}">
                                <input type="checkbox" name="charts_{{ $key }}_enabled"
                                       value="1" {{ $enabled ? 'checked' : '' }}
                                       data-chart-toggle>
                                <span class="bld-toggle-slider"></span>
                            </label>
                            <span class="chart-name">{{ $opt['label'] }}</span>
                            @if(count($opt['types']) > 1)
                                <div class="type-pills">
                                    @foreach($opt['types'] as $type)
                                        <div class="type-pill">
                                            <input type="radio"
                                                   name="charts_{{ $key }}_type"
                                                   id="type_{{ $key }}_{{ $type }}"
                                                   value="{{ $type }}"
                                                   {{ $selType === $type ? 'checked' : '' }}>
                                            <label for="type_{{ $key }}_{{ $type }}">{{ $type }}</label>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <input type="hidden" name="charts_{{ $key }}_type" value="{{ $opt['types'][0] }}">
                                <span style="font-size:11px;color:#94a3b8;padding:.2rem .55rem;border:1.5px solid #f1f5f9;border-radius:999px;">{{ $opt['types'][0] }}</span>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>

        </div>{{-- /bld-grid --}}

        {{-- Bottom options row --}}
        <div class="bld-bottom">

            {{-- KPI Layout --}}
            <div class="card-soft">
                <div class="bld-section-title">{{ __('builder.layout_section') }}</div>
                <div class="col-pills">
                    @foreach([2, 3, 4, 5] as $n)
                        <div class="col-pill">
                            <input type="radio" name="kpi_cols" id="kpi_cols_{{ $n }}"
                                   value="{{ $n }}" {{ (int)$config['kpi_cols'] === $n ? 'checked' : '' }}>
                            <label for="kpi_cols_{{ $n }}">{{ $n }}</label>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Extras --}}
            <div class="card-soft">
                <div class="bld-section-title">{{ __('builder.extras_section') }}</div>
                <div class="extras-list">
                    @foreach([
                        ['show_filters', __('builder.filter_bar_label')],
                        ['show_map',     __('builder.map_label')],
                        ['show_table',   __('builder.table_label')],
                    ] as [$name, $label])
                        <label class="bld-toggle-wrap">
                            <span class="bld-toggle">
                                <input type="checkbox" name="{{ $name }}" value="1"
                                       {{ !empty($config[$name]) ? 'checked' : '' }}>
                                <span class="bld-toggle-slider"></span>
                            </span>
                            <span class="bld-toggle-label">{{ $label }}</span>
                        </label>
                    @endforeach
                </div>
            </div>

        </div>{{-- /bld-bottom --}}

        {{-- Submit at bottom too --}}
        <div style="text-align:right;margin-top:.5rem;">
            <button type="submit" class="btn btn-primary btn-lg">
                {{ __('builder.preview_btn') }}
            </button>
        </div>

    </form>

</div>
@endsection

@section('scripts')
<script>
(function () {
    function updateCounts() {
        const kpiCount   = document.querySelectorAll('[data-kpi-card] input[type=checkbox]:checked').length;
        const chartCount = document.querySelectorAll('[data-chart-toggle]:checked').length;
        document.getElementById('kpi-count').textContent   = kpiCount;
        document.getElementById('chart-count').textContent = chartCount;
    }

    // KPI card toggle
    document.querySelectorAll('[data-kpi-card]').forEach(card => {
        const cb = card.querySelector('input[type=checkbox]');
        cb.addEventListener('change', () => {
            card.classList.toggle('checked', cb.checked);
            updateCounts();
        });
    });

    // Chart row toggle
    document.querySelectorAll('[data-chart-toggle]').forEach(cb => {
        cb.addEventListener('change', () => {
            cb.closest('[data-chart-row]').classList.toggle('enabled', cb.checked);
            updateCounts();
        });
    });

    updateCounts();
})();
</script>
@endsection
