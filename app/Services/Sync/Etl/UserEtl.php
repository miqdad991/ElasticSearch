<?php

namespace App\Services\Sync\Etl;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UserEtl implements TableEtl
{
    private const VALID_USER_TYPES = [
        'super_admin','osool_admin','admin','admin_employee',
        'building_manager','building_manager_employee',
        'sp_admin','supervisor','sp_worker','tenant',
        'procurement_admin','manual_custodian','team_leader',
    ];

    public function transform(): array
    {
        // Dedupe by email at load time. dim_user.email has UNIQUE — source has dupes.
        // Rule: highest user_id wins (treat as the "current" row). Losers logged.
        $byEmail   = [];
        $skipped   = ['no_email' => 0, 'duplicate_email' => 0];
        $loserIds  = [];

        // Pre-load valid city ids so we can NULL out FK violations defensively.
        $cityIds = array_flip(DB::table('marts.dim_city')->pluck('city_id')->all());

        DB::table('raw.users')->orderBy('id')->chunk(2000, function ($chunk) use (&$byEmail, &$skipped, &$loserIds, $cityIds) {
            foreach ($chunk as $r) {
                $p = json_decode($r->payload, true) ?? [];

                $email = mb_strtolower(trim((string) ($p['email'] ?? '')));
                if ($email === '') { $skipped['no_email']++; continue; }

                $row = $this->mapRow($p, (int) $r->id);
                if ($row['city_id'] !== null && !isset($cityIds[$row['city_id']])) {
                    $row['city_id'] = null;
                }

                if (!isset($byEmail[$email])) {
                    $byEmail[$email] = $row;
                } elseif ($row['user_id'] > $byEmail[$email]['user_id']) {
                    $loserIds[] = $byEmail[$email]['user_id'];
                    $byEmail[$email] = $row;
                    $skipped['duplicate_email']++;
                } else {
                    $loserIds[] = $row['user_id'];
                    $skipped['duplicate_email']++;
                }
            }
        });

        if ($loserIds) {
            Log::warning('UserEtl: dropped duplicate-email user_ids', ['count' => count($loserIds), 'sample' => array_slice($loserIds, 0, 10)]);
        }

        // Handle email collisions with existing dim_user rows from prior syncs.
        // If an incoming row has the same email as an existing row but a different user_id,
        // delete the stale row so the upsert can proceed without violating the UNIQUE(email) constraint.
        $existingByEmail = DB::table('marts.dim_user')->pluck('user_id', 'email')->all();
        $deleteIds = [];
        foreach ($byEmail as $email => $row) {
            if (isset($existingByEmail[$email]) && (int) $existingByEmail[$email] !== $row['user_id']) {
                $deleteIds[] = (int) $existingByEmail[$email];
            }
        }
        if ($deleteIds) {
            DB::table('marts.dim_user')->whereIn('user_id', $deleteIds)->delete();
            Log::warning('UserEtl: deleted stale email-collision rows', ['count' => count($deleteIds), 'sample' => array_slice($deleteIds, 0, 10)]);
        }

        // Resolve self-FKs: NULL out targets that won't exist in the final upsert set
        // (either dropped above, or simply absent from the source).
        $known = [];
        foreach ($byEmail as $row) $known[$row['user_id']] = true;
        // Also count users already in dim_user from prior runs (minus deleted ones).
        foreach (DB::table('marts.dim_user')->pluck('user_id') as $existingId) $known[$existingId] = true;

        foreach ($byEmail as &$row) {
            if ($row['project_user_id'] !== null && !isset($known[$row['project_user_id']])) $row['project_user_id'] = null;
            if ($row['created_by']      !== null && !isset($known[$row['created_by']]))      $row['created_by']      = null;
        }
        unset($row);

        // Single transaction so all deferred self-FKs resolve together at commit.
        $upserted = 0;
        DB::transaction(function () use ($byEmail, &$upserted) {
            foreach (array_chunk(array_values($byEmail), 1000) as $batch) {
                DB::table('marts.dim_user')->upsert(
                    $batch,
                    ['user_id'],
                    ['email','full_name','first_name','last_name','phone','profile_image_url',
                     'emp_id','user_type','project_user_id','sp_admin_id','service_provider',
                     'country_id','city_id','status','is_deleted','deleted_at','source_updated_at',
                     'last_login_at','allow_akaunting','created_by','loaded_at']
                );
                $upserted += count($batch);
            }
        });

        Log::info('UserEtl summary', ['upserted' => $upserted, 'skipped' => $skipped]);
        return ['upserted' => $upserted, 'deleted' => 0];
    }

    private function mapRow(array $p, int $rawId): array
    {
        $type = $p['user_type'] ?? null;
        if ($type !== null && !in_array($type, self::VALID_USER_TYPES, true)) $type = null;

        // Source uses 0 as "no user" sentinel for self-FKs; coerce to null.
        $nullIfZero = fn ($v) => (is_numeric($v) && (int) $v > 0) ? (int) $v : null;

        return [
            'user_id'           => (int) ($p['id'] ?? $rawId),
            'email'             => mb_strtolower(trim((string) ($p['email'] ?? ''))),
            'full_name'         => $p['name']        ?? null,
            'first_name'        => $p['first_name']  ?? null,
            'last_name'         => $p['last_name']   ?? null,
            'phone'             => $p['phone']       ?? null,
            'profile_image_url' => $p['profile_img'] ?? null,
            'emp_id'            => $p['emp_id']      ?? null,
            'user_type'         => $type,
            'project_user_id'   => $nullIfZero($p['project_user_id'] ?? null),
            'sp_admin_id'       => $nullIfZero($p['sp_admin_id']     ?? null),
            'service_provider'  => $p['service_provider']?? null,
            'country_id'        => $p['country_id']      ?? null,
            'city_id'           => $p['city_id']         ?? null,
            'status'            => isset($p['status']) ? (int) $p['status'] : 1,
            'is_deleted'        => ($p['is_deleted'] ?? 'no') === 'yes',
            'deleted_at'        => $p['deleted_at']  ?? null,
            'created_at'        => $p['created_at']  ?? now(),
            'source_updated_at' => $p['modified_at'] ?? null,
            'last_login_at'     => $p['last_login_datetime'] ?? null,
            'allow_akaunting'   => (bool) ($p['allow_akaunting'] ?? false),
            'created_by'        => $nullIfZero($p['created_by'] ?? null),
            'loaded_at'         => now(),
        ];
    }
}
