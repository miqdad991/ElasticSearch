<?php

namespace App\Services\Sync\Etl;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AssetEtl implements TableEtl
{
    private const THRESHOLD_UNITS = ['days', 'hours'];

    public function transform(): array
    {
        $userIds     = array_flip(DB::table('marts.dim_user')->pluck('user_id')->all());
        $propertyIds = array_flip(DB::table('marts.dim_property')->pluck('property_id')->all());
        $buildingIds = array_flip(DB::table('marts.dim_property_building')->pluck('building_id')->all());
        $categoryIds = array_flip(DB::table('marts.dim_asset_category')->pluck('asset_category_id')->all());
        $nameIds     = array_flip(DB::table('marts.dim_asset_name')->pluck('asset_name_id')->all());
        $statusIds   = array_flip(DB::table('marts.dim_asset_status')->pluck('asset_status_id')->all());

        $clean      = fn ($v) => ($v === null || $v === '' || str_starts_with((string) $v, '0000-00-00')) ? null : $v;
        $intOrNull  = fn ($v) => is_numeric($v) ? (int) $v : null;
        $numOrNull  = fn ($v) => is_numeric($v) ? (float) $v : null;
        $nullIfZero = fn ($v) => (is_numeric($v) && (int) $v > 0) ? (int) $v : null;
        $enum       = fn ($v, array $allowed) => in_array($v, $allowed, true) ? $v : null;
        $guard      = fn (?int $v, array $set) => ($v !== null && isset($set[$v])) ? $v : null;

        $count = 0;
        $skipped = 0;

        DB::table('raw.assets')->orderBy('id')->chunk(1000, function ($chunk) use (
            &$count, &$skipped, $userIds, $propertyIds, $buildingIds, $categoryIds, $nameIds, $statusIds,
            $clean, $intOrNull, $numOrNull, $nullIfZero, $enum, $guard
        ) {
            $rows = [];
            foreach ($chunk as $r) {
                $p = json_decode($r->payload, true) ?? [];

                $createdAt = $clean($p['created_at'] ?? null);
                if (!$createdAt) { $skipped++; continue; }
                $year = (int) substr($createdAt, 0, 4);
                if ($year < 2010 || $year > 2035) { $skipped++; continue; } // dim_date bounds

                // asset_status: sometimes numeric FK id, sometimes free text
                $rawStatus = (string) ($p['asset_status'] ?? '');
                $statusId  = preg_match('/^\d+$/', $rawStatus) ? (int) $rawStatus : null;
                if ($statusId !== null && !isset($statusIds[$statusId])) $statusId = null;

                $rows[] = [
                    'asset_id'                 => (int) ($p['id'] ?? $r->id),
                    'asset_tag'                => (string) ($p['asset_tag'] ?? ('AST-' . ($p['id'] ?? $r->id))),
                    'asset_symbol'             => $p['asset_symbol'] ?? null,
                    'asset_number'             => $p['asset_number'] ?? null,
                    'barcode_value'            => $p['barcode_value'] ?? null,
                    'owner_user_id'            => $guard($nullIfZero($p['user_id']       ?? null), $userIds),
                    'property_id'              => $guard($nullIfZero($p['property_id']   ?? null), $propertyIds),
                    'building_id'              => $guard($nullIfZero($p['building_id']   ?? null), $buildingIds),
                    'unit_id'                  => $intOrNull($p['unit_id'] ?? null),
                    'floor'                    => $p['floor'] ?? null,
                    'room'                     => $p['room']  ?? null,
                    'asset_category_id'        => $guard($nullIfZero($p['asset_category_id'] ?? null), $categoryIds),
                    'asset_name_id'            => $guard($nullIfZero($p['asset_name_id']     ?? null), $nameIds),
                    'asset_status_id'          => $statusId,
                    'asset_status_raw'         => $rawStatus !== '' ? $rawStatus : null,
                    'model_number'             => $p['model_number']      ?? null,
                    'manufacturer_name'        => $p['manufacturer_name'] ?? null,
                    'purchase_date'            => $clean($p['purchase_date'] ?? null),
                    'purchase_amount'          => $numOrNull($p['purchase_amount'] ?? null),
                    'warranty_duration_months' => $intOrNull($p['warranty_duration_months'] ?? null),
                    'warranty_end_date'        => $clean($p['warranty_end_date'] ?? null),
                    'asset_damage_date'        => $clean($p['asset_damage_date'] ?? null),
                    'usage_threshold'          => $intOrNull($p['usage_threshold'] ?? null),
                    'threshold_unit_value'     => $enum($p['threshold_unit_value'] ?? null, self::THRESHOLD_UNITS),
                    'hours_per_day'            => $intOrNull($p['hours_per_day'] ?? null),
                    'days_per_week'            => $intOrNull($p['days_per_week'] ?? null),
                    'usage_start_at'           => $clean($p['usage_start_at']    ?? null),
                    'last_usage_reset_at'      => $clean($p['last_usage_reset']  ?? null),
                    'linked_wo'                => (bool) ($p['linked_wo'] ?? false),
                    'warehouse_id'             => $intOrNull($p['warehouse_id']  ?? null),
                    'inventory_id'             => $intOrNull($p['inventory_id']  ?? null),
                    'converted_assets'         => $intOrNull($p['converted_assets'] ?? 0) ?? 0,
                    'related_to'               => $intOrNull($p['related_to']    ?? null),
                    'created_at'               => $createdAt,
                    'source_updated_at'        => $clean($p['modified_at']       ?? null),
                    'loaded_at'                => now(),
                ];
            }
            if ($rows) {
                DB::table('marts.fact_asset')->upsert(
                    $rows, ['asset_id'],
                    ['asset_tag','asset_symbol','asset_number','barcode_value',
                     'owner_user_id','property_id','building_id','unit_id','floor','room',
                     'asset_category_id','asset_name_id','asset_status_id','asset_status_raw',
                     'model_number','manufacturer_name','purchase_date','purchase_amount',
                     'warranty_duration_months','warranty_end_date','asset_damage_date',
                     'usage_threshold','threshold_unit_value','hours_per_day','days_per_week',
                     'usage_start_at','last_usage_reset_at','linked_wo','warehouse_id','inventory_id',
                     'converted_assets','related_to','source_updated_at','loaded_at']
                );
                $count += count($rows);
            }
        });

        if ($skipped) Log::warning('AssetEtl: skipped rows', ['count' => $skipped]);
        return ['upserted' => $count, 'deleted' => 0];
    }
}
