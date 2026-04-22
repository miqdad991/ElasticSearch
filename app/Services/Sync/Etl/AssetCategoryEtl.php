<?php

namespace App\Services\Sync\Etl;

use Illuminate\Support\Facades\DB;

class AssetCategoryEtl implements TableEtl
{
    public function transform(): array
    {
        $userIds = array_flip(DB::table('marts.dim_user')->pluck('user_id')->all());
        $clean = fn ($v) => ($v === null || $v === '' || str_starts_with((string) $v, '0000-00-00')) ? null : $v;
        $nullIfZero = fn ($v) => (is_numeric($v) && (int) $v > 0) ? (int) $v : null;

        $count = 0;
        DB::table('raw.asset_categories')->orderBy('id')->chunk(1000, function ($chunk) use (&$count, $userIds, $clean, $nullIfZero) {
            $rows = [];
            foreach ($chunk as $r) {
                $p = json_decode($r->payload, true) ?? [];
                $owner = $nullIfZero($p['user_id'] ?? null);
                if ($owner !== null && !isset($userIds[$owner])) $owner = null;

                $rows[] = [
                    'asset_category_id' => (int) ($p['id'] ?? $r->id),
                    'asset_category'    => (string) ($p['asset_category'] ?? 'Unnamed'),
                    'service_type'      => $p['service_type'] ?? null,
                    'status'            => isset($p['status']) ? (int) $p['status'] : null,
                    'source_updated_at' => $clean($p['modified_at'] ?? null),
                    'loaded_at'         => now(),
                    'owner_user_id'     => $owner,
                    'is_deleted'        => ($p['is_deleted'] ?? 'no') === 'yes',
                    'deleted_at'        => $clean($p['deleted_at'] ?? null),
                    'created_at'        => $clean($p['created_at'] ?? null),
                    'modified_at'       => $clean($p['modified_at'] ?? null),
                ];
            }
            DB::table('marts.dim_asset_category')->upsert(
                $rows, ['asset_category_id'],
                ['asset_category','service_type','status','source_updated_at','loaded_at',
                 'owner_user_id','is_deleted','deleted_at','created_at','modified_at']
            );
            $count += count($rows);
        });
        return ['upserted' => $count, 'deleted' => 0];
    }
}
