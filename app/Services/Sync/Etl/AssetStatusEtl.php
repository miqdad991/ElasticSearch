<?php

namespace App\Services\Sync\Etl;

use Illuminate\Support\Facades\DB;

class AssetStatusEtl implements TableEtl
{
    public function transform(): array
    {
        $userIds = array_flip(DB::table('marts.dim_user')->pluck('user_id')->all());
        $nullIfZero = fn ($v) => (is_numeric($v) && (int) $v > 0) ? (int) $v : null;

        $count = 0;
        DB::table('raw.asset_statuses')->orderBy('id')->chunk(500, function ($chunk) use (&$count, $userIds, $nullIfZero) {
            $rows = [];
            foreach ($chunk as $r) {
                $p = json_decode($r->payload, true) ?? [];
                $owner = $nullIfZero($p['user_id'] ?? null);
                if ($owner !== null && !isset($userIds[$owner])) $owner = null;

                $rows[] = [
                    'asset_status_id' => (int) ($p['id'] ?? $r->id),
                    'name'            => (string) ($p['name'] ?? 'Unnamed'),
                    'color'           => $p['color'] ?? null,
                    'owner_user_id'   => $owner,
                    'is_deleted'      => ($p['is_deleted'] ?? 'no') === 'yes',
                    'loaded_at'       => now(),
                ];
            }
            DB::table('marts.dim_asset_status')->upsert(
                $rows, ['asset_status_id'],
                ['name','color','owner_user_id','is_deleted','loaded_at']
            );
            $count += count($rows);
        });
        return ['upserted' => $count, 'deleted' => 0];
    }
}
