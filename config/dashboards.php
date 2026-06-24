<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Role-based dashboard access
    |--------------------------------------------------------------------------
    | When `enforce_roles` is false the gate is a no-op (open access) — this is
    | the default until SSO propagates role claims / auth.user_role is populated.
    | Flip DASHBOARD_ENFORCE_ROLES=true once role data exists to hard-gate.
    */
    'enforce_roles' => env('DASHBOARD_ENFORCE_ROLES', false),

    // Roles permitted to view the Assets Depreciation Dashboard (case-insensitive).
    'depreciation_roles' => ['Finance', 'POA', 'BMA', 'Admin'],
];
