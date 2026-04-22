<?php

namespace App\Services\Sync\Etl;

use Illuminate\Support\Facades\DB;

class ProjectEtl implements TableEtl
{
    public function transform(): array
    {
        $upserted = 0;
        // Defensive FK guard for owner_user_id
        $userIds = array_flip(DB::table('marts.dim_user')->pluck('user_id')->all());

        $clean = function ($v) {
            if ($v === null || $v === '' || $v === '0000-00-00' || $v === '0000-00-00 00:00:00') return null;
            return $v;
        };
        $nullIfZero = fn ($v) => (is_numeric($v) && (int) $v > 0) ? (int) $v : null;

        DB::table('raw.projects_details')->orderBy('id')->chunk(1000, function ($chunk) use (&$upserted, $clean, $nullIfZero, $userIds) {
            $rows = [];
            foreach ($chunk as $r) {
                $p = json_decode($r->payload, true) ?? [];

                $isDeleted = ((int) ($p['is_deleted'] ?? 0)) === 1;
                $owner     = $nullIfZero($p['user_id'] ?? null);
                if ($owner !== null && !isset($userIds[$owner])) $owner = null;

                $rows[] = [
                    'project_id'             => (int) ($p['id'] ?? $r->id),
                    'owner_user_id'          => $owner,
                    'project_name'           => (string) ($p['project_name'] ?? 'Project #' . $r->id),
                    'industry_type'          => $p['industry_type']   ?? null,
                    'contract_status'        => $p['contract_status'] ?? null,
                    'contract_start_date'    => $clean($p['contract_start_date'] ?? null),
                    'contract_end_date'      => $clean($p['contract_end_date']   ?? null),
                    'use_erp_module'         => (bool) ($p['use_erp_module']         ?? false),
                    'use_crm_module'         => (bool) ($p['use_crm_module']         ?? false),
                    'use_tenant_module'      => (bool) ($p['use_tenant_module']      ?? false),
                    'use_beneficiary_module' => (bool) ($p['use_beneficiary_module'] ?? false),
                    'enable_crm_projects'    => (bool) ($p['enable_crm_projects']    ?? false),
                    'enable_crm_sales'       => (bool) ($p['enable_crm_sales']       ?? false),
                    'enable_crm_finance'     => (bool) ($p['enable_crm_finance']     ?? false),
                    'enable_crm_rfx'         => (bool) ($p['enable_crm_rfx']         ?? false),
                    'enable_crm_documents'   => (bool) ($p['enable_crm_documents']   ?? false),
                    'is_active'              => !$isDeleted,
                    'is_deleted'             => $isDeleted,
                    'created_at'             => $clean($p['created_at'] ?? null) ?? now(),
                    'source_updated_at'      => $clean($p['updated_at'] ?? null),
                    'loaded_at'              => now(),
                ];
            }
            if ($rows) {
                DB::table('marts.dim_project')->upsert(
                    $rows, ['project_id'],
                    ['owner_user_id','project_name','industry_type','contract_status',
                     'contract_start_date','contract_end_date',
                     'use_erp_module','use_crm_module','use_tenant_module','use_beneficiary_module',
                     'enable_crm_projects','enable_crm_sales','enable_crm_finance','enable_crm_rfx','enable_crm_documents',
                     'is_active','is_deleted','source_updated_at','loaded_at']
                );
                $upserted += count($rows);
            }
        });

        return ['upserted' => $upserted, 'deleted' => 0];
    }
}
