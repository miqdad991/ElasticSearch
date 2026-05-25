<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates dashboard routes behind an SSO session established by
 * SsoController::consume. Anonymous requests bounce to Osool's login.
 */
class RequireSso
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->session()->has('sso_user_id')) {
            return $next($request);
        }

        $osool = (string) config('sso.osool_url', '');
        if ($osool === '') {
            abort(401, 'SSO session required and OSOOL_URL is not configured.');
        }

        return redirect($osool . '/login');
    }
}
