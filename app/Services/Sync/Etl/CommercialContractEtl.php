<?php

namespace App\Services\Sync\Etl;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CommercialContractEtl implements TableEtl
{
    private const EJAR    = ['synced_successfully','pending_sync','failed_sync','not_synced'];
    private const LEASE   = ['rent','lease'];
    private const CAL     = ['gregorian','hijri'];

    public function transform(): array
    {
        // Pre-load sets for FK guards
        $userIds     = array_flip(DB::table('marts.dim_user')->pluck('user_id')->all());
        $propertyIds = array_flip(DB::table('marts.dim_property')->pluck('property_id')->all());
        $buildingIds = array_flip(DB::table('marts.dim_property_building')->pluck('building_id')->all());
        $projectIds  = array_flip(DB::table('marts.dim_project')->pluck('project_id')->all());

        // Ensure dim_tenant has a row for every tenant_id referenced (pulled from dim_user)
        // Simplest: insert (tenant_id) for every dim_user user that isn't already there.
        DB::statement(<<<'SQL'
            INSERT INTO marts.dim_tenant (tenant_id, tenant_email)
            SELECT u.user_id, u.email
            FROM marts.dim_user u
            LEFT JOIN marts.dim_tenant t ON t.tenant_id = u.user_id
            WHERE t.tenant_id IS NULL
        SQL);
        $tenantIds = array_flip(DB::table('marts.dim_tenant')->pluck('tenant_id')->all());

        // Pre-load lease contract details for enrichment (1:1 with commercial_contracts)
        $leaseByContract = [];
        DB::table('raw.lease_contract_details')->orderBy('id')->chunk(1000, function ($chunk) use (&$leaseByContract) {
            foreach ($chunk as $r) {
                $p   = json_decode($r->payload, true) ?? [];
                $ccId = $p['commercial_contract_id'] ?? null;
                if ($ccId) $leaseByContract[(int) $ccId] = $p;
            }
        });

        $clean = fn ($v) => ($v === null || $v === '' || str_starts_with((string) $v, '0000-00-00')) ? null : $v;
        $intOrNull  = fn ($v) => is_numeric($v) ? (int) $v : null;
        $nullIfZero = fn ($v) => (is_numeric($v) && (int) $v > 0) ? (int) $v : null;
        $numOrZero  = fn ($v) => is_numeric($v) ? (float) $v : 0.0;
        $enum       = fn ($v, array $allowed) => in_array($v, $allowed, true) ? $v : null;
        $guard      = fn (?int $v, array $set) => ($v !== null && isset($set[$v])) ? $v : null;

        $count = 0;
        DB::table('raw.commercial_contracts')->orderBy('id')->chunk(500, function ($chunk) use (
            &$count, $userIds, $propertyIds, $buildingIds, $projectIds, $tenantIds, $leaseByContract,
            $clean, $intOrNull, $nullIfZero, $numOrZero, $enum, $guard
        ) {
            $rows = [];
            foreach ($chunk as $r) {
                $p = json_decode($r->payload, true) ?? [];
                $id = (int) ($p['id'] ?? $r->id);

                $currency = strtoupper((string) ($p['currency'] ?? ''));
                if (!preg_match('/^[A-Z]{3}$/', $currency)) $currency = null;

                $lease = $leaseByContract[$id] ?? null;
                $termsJson = null; $attsJson = null;
                if ($lease) {
                    $terms = $lease['contract_terms'] ?? null;
                    if ($terms) {
                        $decoded = json_decode($terms, true);
                        $termsJson = json_last_error() === JSON_ERROR_NONE ? json_encode($decoded) : json_encode(['raw' => $terms]);
                    }
                    $atts = $lease['contract_attachments'] ?? null;
                    if ($atts) {
                        $decoded = json_decode($atts, true);
                        $attsJson = json_last_error() === JSON_ERROR_NONE ? json_encode($decoded) : json_encode(['raw' => $atts]);
                    }
                }

                $rows[] = [
                    'commercial_contract_id'  => $id,
                    'reference_number'        => $p['reference_number'] ?? null,
                    'contract_name'           => $p['contract_name']    ?? null,
                    'contract_type'           => $enum($p['contract_type'] ?? null, self::LEASE),
                    'tenant_id'               => $guard($nullIfZero($p['tenant_id']    ?? null), $tenantIds),
                    'property_id'             => $guard($nullIfZero($p['property_id']  ?? null), $propertyIds),
                    'building_id'             => $guard($nullIfZero($p['building_id']  ?? null), $buildingIds),
                    'unit_id'                 => $intOrNull($p['unit_id'] ?? null),
                    'project_id'              => $guard($nullIfZero($p['project_id']   ?? null), $projectIds),
                    'created_by'              => $guard($nullIfZero($p['created_by']   ?? null), $userIds),
                    'ejar_contract_id'        => $p['ejar_contract_id'] ?? null,
                    'ejar_sync_status'        => $enum($p['ejar_sync_status'] ?? null, self::EJAR) ?? 'not_synced',
                    'calendar_type'           => $enum($p['calender_type']   ?? null, self::CAL)  ?? 'gregorian', // source typo
                    'start_date'              => $clean($p['start_date']   ?? null),
                    'end_date'                => $clean($p['end_date']     ?? null),
                    'signing_date'            => $clean($p['signing_date'] ?? null),
                    'payment_date'            => $clean($p['payment_date'] ?? null),
                    'payment_interval'        => $p['payment_interval']    ?? null,
                    'amount'                  => $numOrZero($p['amount'] ?? 0),
                    'security_deposit_amount' => $numOrZero($p['security_deposit_amount'] ?? 0),
                    'late_fees_charge'        => $numOrZero($p['late_fees_charge']        ?? 0),
                    'brokerage_fee'           => $numOrZero($p['brokerage_fee']           ?? 0),
                    'retainer_fee'            => $numOrZero($p['retainer_fee']            ?? 0),
                    'payment_due'             => $numOrZero($p['payment_due']             ?? 0),
                    'payment_overdue'         => $numOrZero($p['payment_overdue']         ?? 0),
                    'currency'                => $currency,
                    'issuing_office'          => $p['issuing_office'] ?? null,
                    'status'                  => $intOrNull($p['status'] ?? 0) ?? 0,
                    'auto_renewal'            => (bool) ($p['auto_renewal'] ?? false),
                    'is_unit_applies'         => (bool) ($p['is_unit_applies'] ?? false),
                    'is_dynamic_rent_applies' => (bool) ($p['is_dynamic_rent_applies'] ?? false),
                    'is_deleted'              => ($p['is_deleted'] ?? 'no') === 'yes',
                    'contract_terms_json'     => $termsJson,
                    'contract_attachments_json'=> $attsJson,
                    'created_at'              => $clean($p['created_at'] ?? null) ?? now(),
                    'source_updated_at'       => $clean($p['updated_at'] ?? null),
                    'loaded_at'               => now(),
                ];
            }
            if ($rows) {
                DB::table('marts.fact_commercial_contract')->upsert(
                    $rows, ['commercial_contract_id'],
                    ['reference_number','contract_name','contract_type','tenant_id','property_id','building_id','unit_id',
                     'project_id','created_by','ejar_contract_id','ejar_sync_status','calendar_type',
                     'start_date','end_date','signing_date','payment_date','payment_interval',
                     'amount','security_deposit_amount','late_fees_charge','brokerage_fee','retainer_fee',
                     'payment_due','payment_overdue','currency','issuing_office','status',
                     'auto_renewal','is_unit_applies','is_dynamic_rent_applies','is_deleted',
                     'contract_terms_json','contract_attachments_json','source_updated_at','loaded_at']
                );
                $count += count($rows);
            }
        });
        return ['upserted' => $count, 'deleted' => 0];
    }
}
