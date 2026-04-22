<?php

namespace App\Services\Sync\Etl;

use Illuminate\Support\Facades\DB;

class CityEtl implements TableEtl
{
    public function transform(): array
    {
        $regionIds = DB::table('marts.dim_region')->pluck('region_id')->all();
        $regionSet = array_flip($regionIds);

        $count = 0;
        DB::table('raw.cities')->orderBy('id')->chunk(1000, function ($chunk) use (&$count, $regionSet) {
            $rows = [];
            foreach ($chunk as $r) {
                $p = json_decode($r->payload, true) ?? [];
                $regionId = $p['region_id'] ?? null;
                if ($regionId !== null && !isset($regionSet[$regionId])) $regionId = null; // FK guard

                $rows[] = [
                    'city_id'     => (int) ($p['id'] ?? $r->id),
                    'name_en'     => (string) ($p['name_en'] ?? $p['name'] ?? ''),
                    'name_ar'     => $p['name_ar']     ?? null,
                    'code'        => $p['code']        ?? null,
                    'postal_code' => $p['postal_code'] ?? null,
                    'region_id'   => $regionId,
                    'country_id'  => $p['country_id']  ?? null,
                    'status'      => isset($p['status']) ? (int) $p['status'] : null,
                    'is_deleted'  => ($p['is_deleted'] ?? 'no') === 'yes',
                    'loaded_at'   => now(),
                ];
            }
            DB::table('marts.dim_city')->upsert(
                $rows, ['city_id'],
                ['name_en','name_ar','code','postal_code','region_id','country_id','status','is_deleted','loaded_at']
            );
            $count += count($rows);
        });
        return ['upserted' => $count, 'deleted' => 0];
    }
}
