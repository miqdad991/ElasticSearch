<?php

namespace App\Services\Sync\Etl;

use Illuminate\Support\Facades\DB;

class PropertyBuildingEtl implements TableEtl
{
    public function transform(): array
    {
        $propertyIds = array_flip(DB::table('marts.dim_property')->pluck('property_id')->all());

        $clean = function ($v) {
            if ($v === null || $v === '' || str_starts_with((string) $v, '0000-00-00')) return null;
            return $v;
        };
        $coord = function ($v, float $min, float $max) {
            if ($v === null || $v === '' || !is_numeric(trim((string) $v))) return null;
            $n = (float) trim((string) $v);
            return ($n >= $min && $n <= $max) ? $n : null;
        };
        $lat = fn ($v) => $coord($v, -90.0, 90.0);
        $lng = fn ($v) => $coord($v, -180.0, 180.0);
        $intOrNull = fn ($v) => (is_numeric($v)) ? (int) $v : null;
        $nullIfZero = fn ($v) => (is_numeric($v) && (int) $v > 0) ? (int) $v : null;

        $count = 0;
        DB::table('raw.property_buildings')->orderBy('id')->chunk(1000, function ($chunk) use (&$count, $propertyIds, $clean, $lat, $lng, $intOrNull, $nullIfZero) {
            $rows = [];
            foreach ($chunk as $r) {
                $p = json_decode($r->payload, true) ?? [];

                $propId = $nullIfZero($p['property_id'] ?? null);
                if ($propId !== null && !isset($propertyIds[$propId])) $propId = null;

                $rows[] = [
                    'building_id'              => (int) ($p['id'] ?? $r->id),
                    'property_id'              => $propId,
                    'building_name'            => (string) ($p['building_name'] ?? ('Building #' . $r->id)),
                    'building_tag'             => $p['building_tag'] ?? null,
                    'rooms_count'              => $intOrNull($p['rooms_count'] ?? 0) ?? 0,
                    'use_building'             => $intOrNull($p['use_building'] ?? null),
                    'district_name'            => $p['district_name'] ?? null,
                    'street_name'              => $p['street_name']   ?? null,
                    'latitude'                 => $lat($p['latitude']  ?? null),
                    'longitude'                => $lng($p['longitude'] ?? null),
                    'location_label'           => $p['location'] ?? null,
                    'barcode_value'            => $p['barcode_value'] ?? null,
                    'ownership_document_type'  => $p['ownership_document_type']   ?? null,
                    'ownership_document_number'=> $p['ownership_document_number'] ?? null,
                    'ownership_issue_date'     => $clean($p['ownership_issue_date'] ?? null),
                    'is_deleted'               => ($p['is_deleted'] ?? 'no') === 'yes',
                    'created_at'               => $clean($p['created_at'] ?? null) ?? now(),
                    'source_updated_at'        => $clean($p['modified_at'] ?? null),
                    'loaded_at'                => now(),
                ];
            }
            DB::table('marts.dim_property_building')->upsert(
                $rows, ['building_id'],
                ['property_id','building_name','building_tag','rooms_count','use_building',
                 'district_name','street_name','latitude','longitude','location_label',
                 'barcode_value','ownership_document_type','ownership_document_number','ownership_issue_date',
                 'is_deleted','source_updated_at','loaded_at']
            );
            $count += count($rows);
        });
        return ['upserted' => $count, 'deleted' => 0];
    }
}
