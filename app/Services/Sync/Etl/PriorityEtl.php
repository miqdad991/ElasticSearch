<?php

namespace App\Services\Sync\Etl;

use Illuminate\Support\Facades\DB;

class PriorityEtl implements TableEtl
{
    public function transform(): array
    {
        $userIds = array_flip(DB::table('marts.dim_user')->pluck('user_id')->all());
        $clean = fn ($v) => ($v === null || $v === '' || str_starts_with((string) $v, '0000-00-00')) ? null : $v;
        $nullIfZero = fn ($v) => (is_numeric($v) && (int) $v > 0) ? (int) $v : null;
        $intOrNull = fn ($v) => is_numeric($v) ? (int) $v : null;
        $numOrNull = fn ($v) => is_numeric($v) ? (float) $v : null;

        $count = 0;
        DB::table('raw.priorities')->orderBy('id')->chunk(1000, function ($chunk) use (&$count, $userIds, $clean, $nullIfZero, $intOrNull, $numOrNull) {
            $rows = [];
            foreach ($chunk as $r) {
                $p = json_decode($r->payload, true) ?? [];
                $owner = $nullIfZero($p['user_id'] ?? null);
                if ($owner !== null && !isset($userIds[$owner])) $owner = null;

                $rows[] = [
                    'priority_id'         => (int) ($p['id'] ?? $r->id),
                    'priority_level'      => (string) ($p['priority_level'] ?? 'Unnamed'),
                    'service_window'      => $intOrNull($p['service_window']  ?? null),
                    'service_window_type' => $p['service_window_type'] ?? null,
                    'response_time'       => $numOrNull($p['response_time']   ?? null),
                    'response_time_type'  => $p['response_time_type'] ?? null,
                    'loaded_at'           => now(),
                    'owner_user_id'       => $owner,
                    'is_deleted'          => ($p['is_deleted'] ?? 'no') === 'yes',
                    'deleted_at'          => $clean($p['deleted_at'] ?? null),
                    'created_at'          => $clean($p['created_at'] ?? null),
                    'modified_at'         => $clean($p['modified_at'] ?? null),
                ];
            }
            DB::table('marts.dim_priority')->upsert(
                $rows, ['priority_id'],
                ['priority_level','service_window','service_window_type','response_time','response_time_type',
                 'loaded_at','owner_user_id','is_deleted','deleted_at','created_at','modified_at']
            );
            $count += count($rows);
        });
        return ['upserted' => $count, 'deleted' => 0];
    }
}
