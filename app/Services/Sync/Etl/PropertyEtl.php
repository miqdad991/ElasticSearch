<?php

namespace App\Services\Sync\Etl;

use Illuminate\Support\Facades\DB;

class PropertyEtl implements TableEtl
{
    public function transform(): array
    {
        $userIds   = array_flip(DB::table('marts.dim_user')->pluck('user_id')->all());
        $regionIds = array_flip(DB::table('marts.dim_region')->pluck('region_id')->all());
        $cityIds   = array_flip(DB::table('marts.dim_city')->pluck('city_id')->all());

        $clean = function ($v) {
            if ($v === null || $v === '' || str_starts_with((string) $v, '0000-00-00')) return null;
            return $v;
        };
        $nullIfZero = fn ($v) => (is_numeric($v) && (int) $v > 0) ? (int) $v : null;
        $coord = function ($v, float $min, float $max) {
            if ($v === null || $v === '' || !is_numeric(trim((string) $v))) return null;
            $n = (float) trim((string) $v);
            return ($n >= $min && $n <= $max) ? $n : null;
        };
        $lat = fn ($v) => $coord($v, -90.0, 90.0);
        $lng = fn ($v) => $coord($v, -180.0, 180.0);
        $intOrNull = fn ($v) => (is_numeric($v)) ? (int) $v : null;

        $count = 0;
        DB::table('raw.properties')->orderBy('id')->chunk(1000, function ($chunk) use (&$count, $userIds, $regionIds, $cityIds, $clean, $nullIfZero, $lat, $lng, $intOrNull) {
            $rows = [];
            foreach ($chunk as $r) {
                $p = json_decode($r->payload, true) ?? [];

                $owner    = $nullIfZero($p['user_id']   ?? null);
                if ($owner    !== null && !isset($userIds[$owner]))     $owner    = null;
                $regionId = $nullIfZero($p['region_id'] ?? null);
                if ($regionId !== null && !isset($regionIds[$regionId])) $regionId = null;
                $cityId   = $nullIfZero($p['city_id']   ?? null);
                if ($cityId   !== null && !isset($cityIds[$cityId]))     $cityId   = null;

                $type     = $p['property_type'] ?? null;
                if ($type !== null && !in_array($type, ['building','complex'], true)) $type = null;
                $loc      = $p['location_type'] ?? null;
                if ($loc !== null && !in_array($loc, ['single_location','multiple_location'], true)) $loc = null;

                $rows[] = [
                    'property_id'            => (int) ($p['id'] ?? $r->id),
                    'owner_user_id'          => $owner,
                    'property_name'          => (string) ($p['project'] ?? ('Property #' . $r->id)),
                    'property_tag'           => $p['property_tag']    ?? null,
                    'property_number'        => $p['property_number'] ?? null,
                    'compound_name'          => $p['compound_name']   ?? null,
                    'property_type'          => $type,
                    'location_type'          => $loc,
                    'property_usage'         => $p['property_usage']  ?? null,
                    'region_id'              => $regionId,
                    'city_id'                => $cityId,
                    'district_name'          => $p['district_name']  ?? null,
                    'street_name'            => $p['street_name']    ?? null,
                    'postal_code'            => $p['postal_code']    ?? null,
                    'building_number'        => $p['building_number']?? null,
                    'latitude'               => $lat($p['latitude']  ?? null),
                    'longitude'              => $lng($p['longitude'] ?? null),
                    'location_label'         => $p['location'] ?? null,
                    'buildings_count'        => $intOrNull($p['buildings_count'] ?? 0) ?? 0,
                    'actual_buildings_added' => $intOrNull($p['actual_buildings_added'] ?? null),
                    'total_floors'           => $intOrNull($p['total_floors']    ?? null),
                    'units_per_floor'        => $intOrNull($p['units_per_floor'] ?? null),
                    'total_units'            => $intOrNull($p['total_units']     ?? null),
                    'established_date'       => $clean($p['established_date'] ?? null),
                    'awqaf_contains'         => ($p['awqaf_contains'] ?? 'no') === 'yes',
                    'worker_housing'         => (bool) ($p['worker_housing'] ?? false),
                    'agreement_status'       => $p['agreement_status'] ?? null,
                    'contract_type'          => $p['contract_type']    ?? null,
                    'status'                 => isset($p['status']) ? (int) $p['status'] : null,
                    'is_deleted'             => ($p['is_deleted'] ?? 'no') === 'yes',
                    'created_at'             => $clean($p['created_at'] ?? null) ?? now(),
                    'source_updated_at'      => $clean($p['modified_at'] ?? null),
                    'loaded_at'              => now(),
                ];
            }
            DB::table('marts.dim_property')->upsert(
                $rows, ['property_id'],
                ['owner_user_id','property_name','property_tag','property_number','compound_name',
                 'property_type','location_type','property_usage','region_id','city_id',
                 'district_name','street_name','postal_code','building_number',
                 'latitude','longitude','location_label',
                 'buildings_count','actual_buildings_added','total_floors','units_per_floor','total_units',
                 'established_date','awqaf_contains','worker_housing','agreement_status','contract_type',
                 'status','is_deleted','source_updated_at','loaded_at']
            );
            $count += count($rows);
        });
        return ['upserted' => $count, 'deleted' => 0];
    }
}
