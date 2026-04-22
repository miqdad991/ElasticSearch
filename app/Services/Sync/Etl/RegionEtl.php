<?php

namespace App\Services\Sync\Etl;

use Illuminate\Support\Facades\DB;

class RegionEtl implements TableEtl
{
    public function transform(): array
    {
        $count = 0;
        DB::table('raw.regions')->orderBy('id')->chunk(1000, function ($chunk) use (&$count) {
            $rows = [];
            foreach ($chunk as $r) {
                $p = json_decode($r->payload, true) ?? [];
                $rows[] = [
                    'region_id'  => (int) ($p['id'] ?? $r->id),
                    'name'       => (string) ($p['name'] ?? ($p['name_en'] ?? '')),
                    'name_ar'    => $p['name_ar'] ?? null,
                    'code'       => $p['code']    ?? null,
                    'country_id' => $p['country_id'] ?? null,
                    'status'     => isset($p['status']) ? (int) $p['status'] : null,
                    'is_deleted' => ($p['is_deleted'] ?? 'no') === 'yes',
                    'loaded_at'  => now(),
                ];
            }
            DB::table('marts.dim_region')->upsert(
                $rows, ['region_id'],
                ['name','name_ar','code','country_id','status','is_deleted','loaded_at']
            );
            $count += count($rows);
        });
        return ['upserted' => $count, 'deleted' => 0];
    }
}
