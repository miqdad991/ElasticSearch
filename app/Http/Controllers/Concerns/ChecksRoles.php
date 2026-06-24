<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Support\Facades\DB;

trait ChecksRoles
{
    /**
     * Abort with 403 unless the current SSO user holds one of $allowedRoles.
     * No-op while config('dashboards.enforce_roles') is false, so dashboards
     * stay open until role data flows through SSO / auth.user_role.
     */
    protected function requireAnyRole(array $allowedRoles): void
    {
        if (! config('dashboards.enforce_roles')) {
            return;
        }

        $userId = session('sso_user_id');
        if (! $userId) {
            abort(403);
        }

        $roles = DB::table('auth.user_role as ur')
            ->join('auth.role as r', 'r.role_id', '=', 'ur.role_id')
            ->where('ur.user_id', $userId)
            ->pluck('r.role_key')
            ->map(fn ($k) => strtolower((string) $k))
            ->all();

        $allowed = array_map('strtolower', $allowedRoles);

        if (! array_intersect($roles, $allowed)) {
            abort(403);
        }
    }
}
