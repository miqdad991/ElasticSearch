<?php

namespace App\Services\Sync\Etl;

use Illuminate\Support\Facades\DB;

class AssetNameEtl implements TableEtl
{
    public function transform(): array
    {
        $count = 0;
        DB::table('raw.asset_names')->orderBy('id')->chunk(1000, function ($chunk) use (&$count) {
            $rows = [];
            foreach ($chunk as $r) {
                $p = json_decode($r->payload, true) ?? [];
                $rows[] = [
                    'asset_name_id' => (int) ($p['id'] ?? $r->id),
                    'asset_name'    => (string) ($p['asset_name'] ?? 'Unnamed'),
                    'loaded_at'     => now(),
                ];
            }
            DB::table('marts.dim_asset_name')->upsert($rows, ['asset_name_id'], ['asset_name','loaded_at']);
            $count += count($rows);
        });
        return ['upserted' => $count, 'deleted' => 0];
    }
}
