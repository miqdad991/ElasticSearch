<?php

return [
    // Shared HMAC secret between Osool and this dashboard.
    // Must match SSO_HMAC_SECRET in the Osool .env exactly.
    'secret' => env('SSO_HMAC_SECRET', ''),

    // Public URL of the Osool app — used for browser redirects on
    // missing/expired SSO session.
    'osool_url' => rtrim((string) env('OSOOL_URL', ''), '/'),

    // Server-to-server URL for back-channel calls FROM this container.
    // Falls back to osool_url. In local Docker setups set this to
    // http://host.docker.internal:PORT to reach the host-published port.
    'osool_internal_url' => rtrim((string) env('OSOOL_INTERNAL_URL', env('OSOOL_URL', '')), '/'),

    // Lifetime in seconds for the launch token. Keep short — the token
    // only needs to survive one redirect hop.
    'token_ttl' => 60,

    // How long to remember a jti to prevent replay (seconds).
    'jti_ttl' => 300,

    // Allowed clock skew (seconds) on the back-channel logout timestamp.
    'logout_skew' => 30,
];
