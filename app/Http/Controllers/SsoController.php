<?php

namespace App\Http\Controllers;

use App\Services\Sso\SsoHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SsoController extends Controller
{
    /**
     * Inbound from Osool. Validates the signed handoff token and
     * establishes the dashboard session. Idempotent jti prevents replay.
     */
    public function consume(Request $request): RedirectResponse
    {
        $token = (string) $request->query('token', '');
        if ($token === '') {
            abort(400, 'Missing SSO token.');
        }

        try {
            $claims = SsoHelper::fromConfig()->verify($token);
        } catch (\Throwable $e) {
            abort(401, $e->getMessage());
        }

        $jti = (string) ($claims['jti'] ?? '');
        if ($jti === '') {
            abort(401, 'SSO token missing jti.');
        }

        $cacheKey = 'sso:jti:' . $jti;
        if (Cache::has($cacheKey)) {
            abort(401, 'SSO token already consumed.');
        }
        Cache::put($cacheKey, 1, (int) config('sso.jti_ttl', 300));

        // Establish identity for this browser session. Other controllers
        // read session('sso_user_id') and session('selected_project_id').
        $userId    = (int) ($claims['sub'] ?? 0);
        $projectId = isset($claims['pid']) ? (int) $claims['pid'] : null;
        $locale    = (string) ($claims['lang'] ?? config('app.locale', 'en'));

        $request->session()->regenerate();
        $request->session()->put('sso_user_id', $userId);
        $request->session()->put('locale', in_array($locale, ['en', 'ar'], true) ? $locale : 'en');

        if ($projectId) {
            // The selected project comes only from Osool's SSO claims.
            // Dashboards read both keys (id + name).
            $name = DB::table('marts.dim_project')
                ->where('project_id', $projectId)
                ->value('project_name');
            $request->session()->put('selected_project_id', $projectId);
            $request->session()->put('selected_project_name', $name ?? "Project #{$projectId}");
        }

        return redirect('/');
    }

    /**
     * Back-channel inbound from Osool when the user logs out there.
     * Body: JSON { user_id, ts }. Header: X-Sso-Signature = hmac of raw body.
     * Deletes every session row belonging to the user.
     */
    public function logout(Request $request): JsonResponse
    {
        $raw = (string) $request->getContent();
        $sig = (string) $request->header('X-Sso-Signature', '');

        $helper = SsoHelper::fromConfig();
        if ($sig === '' || !$helper->verifyBody($raw, $sig)) {
            abort(401, 'Invalid logout signature.');
        }

        $body = json_decode($raw, true);
        if (!is_array($body) || !isset($body['user_id'], $body['ts'])) {
            abort(400, 'Malformed logout body.');
        }

        $skew = (int) config('sso.logout_skew', 30);
        if (abs(time() - (int) $body['ts']) > $skew) {
            abort(401, 'Logout timestamp out of window.');
        }

        $userId = (int) $body['user_id'];
        $table  = config('session.table', 'sessions');

        // We do not use Laravel Auth, so the sessions.user_id column is
        // always null on our rows. Identity lives inside the payload as
        // session('sso_user_id'). Laravel's DatabaseSessionHandler writes
        // payload as base64(serialize($data)) — decode to match.
        $ids = [];
        foreach (DB::table($table)->select('id', 'payload')->cursor() as $row) {
            $raw = base64_decode($row->payload, true);
            if ($raw === false) continue;
            $data = @unserialize($raw);
            if (!is_array($data)) continue;
            if (isset($data['sso_user_id']) && (int) $data['sso_user_id'] === $userId) {
                $ids[] = $row->id;
            }
        }
        foreach (array_chunk($ids, 1000) as $batch) {
            DB::table($table)->whereIn('id', $batch)->delete();
        }

        return response()->json(['ok' => true, 'deleted' => count($ids)]);
    }

    /**
     * Local dashboard logout. Clears the session and notifies Osool
     * via back-channel POST so the other system drops the user too.
     */
    public function selfLogout(Request $request): RedirectResponse
    {
        $userId = (int) $request->session()->get('sso_user_id', 0);

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($userId > 0) {
            $this->notifyOsoolLogout($userId);
        }

        $osool = (string) config('sso.osool_url', '');
        return redirect($osool !== '' ? $osool : '/');
    }

    private function notifyOsoolLogout(int $userId): void
    {
        $osool = (string) config('sso.osool_internal_url', config('sso.osool_url', ''));
        if ($osool === '') return;

        $body = json_encode(['user_id' => $userId, 'ts' => time()], JSON_UNESCAPED_UNICODE);
        $sig  = SsoHelper::fromConfig()->signBody($body);

        try {
            \Illuminate\Support\Facades\Http::timeout(3)
                ->withHeaders([
                    'Content-Type'    => 'application/json',
                    'X-Sso-Signature' => $sig,
                ])
                ->withBody($body, 'application/json')
                ->post($osool . '/sso/logout');
        } catch (\Throwable) {
            // Fire-and-forget; if Osool is unreachable the user is still
            // logged out locally and Osool's TTL will eventually expire.
        }
    }
}
