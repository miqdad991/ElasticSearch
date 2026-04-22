<?php

namespace App\Services\Sync\Etl;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Simple Type-1 upsert into marts.dim_contract.
 * Keeps exactly one row per contract_id with is_current=TRUE (no history for now).
 */
class ContractEtl implements TableEtl
{
    public function transform(): array
    {
        $userIds = array_flip(DB::table('marts.dim_user')->pluck('user_id')->all());
        $spIds   = array_flip(DB::table('marts.dim_service_provider')->pluck('sp_id')->all());
        $typeIds = array_flip(DB::table('marts.dim_contract_type')->pluck('contract_type_id')->all());
        $existing = array_flip(
            DB::table('marts.dim_contract')->where('is_current', true)->pluck('contract_id')->all()
        );

        $clean = fn ($v) => ($v === null || $v === '' || str_starts_with((string) $v, '0000-00-00')) ? null : $v;
        $intOrZero = fn ($v) => is_numeric($v) ? (int) $v : 0;
        $numOrZero = fn ($v) => is_numeric($v) ? (float) $v : 0.0;
        $nullIfZero = fn ($v) => (is_numeric($v) && (int) $v > 0) ? (int) $v : null;
        $guard = fn (?int $v, array $set) => ($v !== null && isset($set[$v])) ? $v : null;

        $updated = 0; $inserted = 0; $skipped = 0;
        DB::table('raw.contracts')->orderBy('id')->chunk(500, function ($chunk) use (
            &$updated, &$inserted, &$skipped, $userIds, $spIds, $typeIds, $existing,
            $clean, $intOrZero, $numOrZero, $nullIfZero, $guard
        ) {
            foreach ($chunk as $r) {
                $p = json_decode($r->payload, true) ?? [];
                $id = (int) ($p['id'] ?? $r->id);

                // Skip advance contracts (types 6,7) per doc #6
                $type = $nullIfZero($p['contract_type_id'] ?? null);
                if (in_array($type, [6, 7], true)) { $skipped++; continue; }

                $row = [
                    'contract_number'     => $p['contract_number']   ?? null,
                    'parent_contract_id'  => $nullIfZero($p['parent_contract_id'] ?? null),
                    'owner_user_id'       => $guard($nullIfZero($p['user_id'] ?? null), $userIds),
                    'service_provider_id' => $guard($nullIfZero($p['service_provider_id'] ?? null), $spIds),
                    'contract_type_id'    => $guard($type, $typeIds),
                    'start_date'          => $clean($p['start_date'] ?? null),
                    'end_date'            => $clean($p['end_date']   ?? null),
                    'contract_value'      => $numOrZero($p['contract_value']    ?? 0),
                    'retention_percent'   => $numOrZero($p['retention_percent'] ?? 0),
                    'discount_percent'    => $numOrZero($p['discount_percent']  ?? 0),
                    'spare_parts_included'=> ($p['spare_parts_included'] ?? 'no') === 'yes',
                    'allow_subcontract'   => (bool) ($p['allow_subcontract'] ?? false),
                    'workers_count'       => $intOrZero($p['workers_count']       ?? 0),
                    'supervisor_count'    => $intOrZero($p['supervisor_count']    ?? 0),
                    'administrator_count' => $intOrZero($p['administrator_count'] ?? 0),
                    'engineer_count'      => $intOrZero($p['engineer_count']      ?? 0),
                    'comment'             => $p['comment']   ?? null,
                    'file_path'           => $p['file_path'] ?? null,
                    'status'              => $intOrZero($p['status'] ?? 0),
                    'is_deleted'          => ($p['is_deleted'] ?? 'no') === 'yes',
                    'source_updated_at'   => $clean($p['modified_at'] ?? null) ?? $clean($p['created_at'] ?? null) ?? now(),
                    'loaded_at'           => now(),
                ];

                if (isset($existing[$id])) {
                    DB::table('marts.dim_contract')
                        ->where('contract_id', $id)->where('is_current', true)
                        ->update($row);
                    $updated++;
                } else {
                    DB::table('marts.dim_contract')->insert(array_merge($row, [
                        'contract_id' => $id,
                        'valid_from'  => now(),
                        'valid_to'    => 'infinity',
                        'is_current'  => true,
                    ]));
                    $existing[$id] = true;
                    $inserted++;
                }
            }
        });

        if ($skipped) Log::info('ContractEtl skipped advance contracts', ['count' => $skipped]);
        return ['upserted' => $updated + $inserted, 'deleted' => 0];
    }
}
