<?php

namespace App\Services\Sync\Etl;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WorkOrderEtl implements TableEtl
{
    private const STATUS_LABELS = [
        1 => 'Open', 2 => 'In Progress', 3 => 'On Hold', 4 => 'Closed',
        5 => 'Deleted', 6 => 'Re-open', 7 => 'Warranty', 8 => 'Scheduled',
    ];
    private const WO_TYPE         = ['reactive','preventive'];
    private const SERVICE_TYPE    = ['soft','hard'];
    private const CONTRACT_TYPE   = ['regular','warranty'];
    private const JOURNEY         = ['submitted','job_execution','job_evaluation','job_approval','finished'];
    private const PASS_FAIL       = ['pass','fail','pending'];
    private const TIME_UNITS      = ['days','hours','minutes'];

    public function transform(): array
    {
        $userIds       = array_flip(DB::table('marts.dim_user')->pluck('user_id')->all());
        $spIds         = array_flip(DB::table('marts.dim_service_provider')->pluck('sp_id')->all());
        $buildingIds   = array_flip(DB::table('marts.dim_property_building')->pluck('building_id')->all());
        $categoryIds   = array_flip(DB::table('marts.dim_asset_category')->pluck('asset_category_id')->all());
        $nameIds       = array_flip(DB::table('marts.dim_asset_name')->pluck('asset_name_id')->all());
        $priorityIds   = array_flip(DB::table('marts.dim_priority')->pluck('priority_id')->all());

        $clean = fn ($v) => ($v === null || $v === '' || str_starts_with((string) $v, '0000-00-00')) ? null : $v;
        $intOrNull  = fn ($v) => is_numeric($v) ? (int) $v : null;
        $numOrNull  = fn ($v) => is_numeric($v) ? (float) $v : null;
        $nullIfZero = fn ($v) => (is_numeric($v) && (int) $v > 0) ? (int) $v : null;
        $enum       = fn ($v, array $allowed) => in_array($v, $allowed, true) ? $v : null;

        $guard = function (?int $v, array $set): ?int {
            return ($v !== null && isset($set[$v])) ? $v : null;
        };

        $count = 0;
        $skipped = 0;

        DB::table('raw.work_orders')->orderBy('id')->chunk(1000, function ($chunk) use (
            &$count, &$skipped, $userIds, $spIds, $buildingIds, $categoryIds, $nameIds, $priorityIds,
            $clean, $intOrNull, $numOrNull, $nullIfZero, $enum, $guard
        ) {
            $rows = [];
            foreach ($chunk as $r) {
                $p = json_decode($r->payload, true) ?? [];

                $createdAt = $clean($p['created_at'] ?? null);
                if (!$createdAt) { $skipped++; continue; }
                // partition bounds: 2024-01-01 .. 2030-12-31
                $year = (int) substr($createdAt, 0, 4);
                if ($year < 2015 || $year > 2030) { $skipped++; continue; }

                $statusCode = $intOrNull($p['status'] ?? null);
                $rows[] = [
                    'wo_id'                  => (int) ($p['id'] ?? $r->id),
                    'wo_number'              => (string) ($p['work_order_id'] ?? ('WO-' . ($p['id'] ?? $r->id))),
                    'project_user_id'        => $guard($nullIfZero($p['project_user_id']     ?? null), $userIds),
                    'service_provider_id'    => $guard($nullIfZero($p['service_provider_id'] ?? null), $spIds),
                    'supervisor_id'          => $guard($nullIfZero($p['supervisor_id'] ?? null), $userIds),
                    'property_id'            => $guard($nullIfZero($p['property_id']         ?? null), $buildingIds),
                    'unit_id'                => $intOrNull($p['unit_id']              ?? null),
                    'asset_category_id'      => $guard($nullIfZero($p['asset_category_id']   ?? null), $categoryIds),
                    'asset_name_id'          => $guard($nullIfZero($p['asset_name_id']       ?? null), $nameIds),
                    'priority_id'            => $guard($nullIfZero($p['priority_id']         ?? null), $priorityIds),
                    'contract_id'            => $nullIfZero($p['contract_id']         ?? null),
                    'contract_type'          => $enum($p['contract_type']     ?? null, self::CONTRACT_TYPE),
                    'maintenance_request_id' => $intOrNull($p['maintanance_request_id'] ?? null), // source typo
                    'work_order_type'        => $enum($p['work_order_type']   ?? null, self::WO_TYPE),
                    'service_type'           => $enum($p['service_type']      ?? null, self::SERVICE_TYPE),
                    'workorder_journey'      => $enum($p['workorder_journey'] ?? null, self::JOURNEY),
                    'status_code'            => $statusCode,
                    'status_label'           => $statusCode !== null ? (self::STATUS_LABELS[$statusCode] ?? ('Status ' . $statusCode)) : null,
                    'cost'                   => $numOrNull($p['cost'] ?? 0) ?? 0,
                    'score'                  => $numOrNull($p['score'] ?? 0) ?? 0,
                    'pass_fail'              => $enum($p['pass_fail']          ?? null, self::PASS_FAIL),
                    'sla_response_time'      => $numOrNull($p['sla_response_time']  ?? null),
                    'response_time_type'     => $enum($p['response_time_type'] ?? null, self::TIME_UNITS),
                    'sla_service_window'     => $intOrNull($p['sla_service_window'] ?? null),
                    'service_window_type'    => $enum($p['service_window_type']?? null, self::TIME_UNITS),
                    'start_date'             => $clean($p['start_date'] ?? null),
                    'end_date'               => $clean($p['end_date']   ?? null),
                    'target_at'              => $clean($p['target_date'] ?? null),
                    'job_started_at'         => $clean($p['job_started_at'] ?? null),
                    'job_submitted_at'       => $clean($p['job_submitted_at'] ?? null),
                    'job_completion_at'      => $clean($p['job_completion_date'] ?? null),
                    'created_at'             => $createdAt,
                    'source_updated_at'      => $clean($p['modified_at'] ?? null) ?? $createdAt,
                    'loaded_at'              => now(),
                ];
            }
            if ($rows) {
                DB::table('marts.fact_work_order')->upsert(
                    $rows, ['wo_id', 'created_at'],
                    ['wo_number','project_user_id','service_provider_id','supervisor_id','property_id','unit_id',
                     'asset_category_id','asset_name_id','priority_id','contract_id','contract_type',
                     'maintenance_request_id','work_order_type','service_type','workorder_journey',
                     'status_code','status_label','cost','score','pass_fail',
                     'sla_response_time','response_time_type','sla_service_window','service_window_type',
                     'start_date','end_date','target_at','job_started_at','job_submitted_at','job_completion_at',
                     'source_updated_at','loaded_at']
                );
                $count += count($rows);
            }
        });

        if ($skipped) Log::warning('WorkOrderEtl: skipped rows', ['count' => $skipped, 'reason' => 'created_at null or out of partition range']);
        return ['upserted' => $count, 'deleted' => 0];
    }
}
