<?php

return [
    // Base URL of the source Osool-B2G instance.
    'base_url' => rtrim((string) env('OSOOL_BASE_URL', 'http://localhost:8001'), '/'),

    // Shared HMAC secret. MUST match DWH_HMAC_SECRET on the source side.
    'hmac_secret' => (string) env('OSOOL_HMAC_SECRET', env('DWH_HMAC_SECRET', '')),

    // Per-sync knobs
    'page_size'     => (int) env('OSOOL_SYNC_PAGE_SIZE', 500),
    'timeout'       => (int) env('OSOOL_SYNC_TIMEOUT', 30),
    'max_retries'   => (int) env('OSOOL_SYNC_RETRIES', 3),
    'cursor_overlap_seconds' => (int) env('OSOOL_CURSOR_OVERLAP', 600), // 10 min
];
