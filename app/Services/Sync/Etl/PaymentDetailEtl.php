<?php

namespace App\Services\Sync\Etl;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentDetailEtl implements TableEtl
{
    public function transform(): array
    {
        $contractIds = array_flip(DB::table('marts.fact_commercial_contract')->pluck('commercial_contract_id')->all());
        $tenantIds   = array_flip(DB::table('marts.dim_tenant')->pluck('tenant_id')->all());
        $userIds     = array_flip(DB::table('marts.dim_user')->pluck('user_id')->all());

        $clean = fn ($v) => ($v === null || $v === '' || str_starts_with((string) $v, '0000-00-00')) ? null : $v;
        $intOrNull  = fn ($v) => is_numeric($v) ? (int) $v : null;
        $numOrZero  = fn ($v) => is_numeric($v) ? (float) $v : 0.0;
        $nullIfZero = fn ($v) => (is_numeric($v) && (int) $v > 0) ? (int) $v : null;
        $guard      = fn (?int $v, array $set) => ($v !== null && isset($set[$v])) ? $v : null;

        $count = 0; $skipped = 0;
        DB::table('raw.payment_details')->orderBy('id')->chunk(1000, function ($chunk) use (
            &$count, &$skipped, $contractIds, $tenantIds, $userIds,
            $clean, $intOrNull, $numOrZero, $nullIfZero, $guard
        ) {
            $rows = [];
            foreach ($chunk as $r) {
                $p = json_decode($r->payload, true) ?? [];

                $due = $clean($p['payment_due_date'] ?? null);
                if (!$due) { $skipped++; continue; }
                $year = (int) substr($due, 0, 4);
                if ($year < 2015 || $year > 2030) { $skipped++; continue; }

                $ccId = $nullIfZero($p['contract_id'] ?? null);
                if ($ccId === null || !isset($contractIds[$ccId])) { $skipped++; continue; }

                $rows[] = [
                    'installment_id'         => (int) ($p['id'] ?? $r->id),
                    'commercial_contract_id' => $ccId,
                    'payment_ref'            => $p['payment_ref']        ?? null,
                    'transaction_id'         => $p['transaction_id']     ?? null,
                    'transaction_date'       => $clean($p['transaction_date'] ?? null),
                    'lessor_id'              => $nullIfZero($p['lessor_id']  ?? null),
                    'lessor_name_snapshot'   => $p['lessor_name']        ?? null,
                    'tenant_id'              => $guard($nullIfZero($p['tenant_id'] ?? null), $tenantIds),
                    'tenant_name_snapshot'   => $p['tenant_name']        ?? null,
                    'period_start'           => $clean($p['start_date']           ?? null),
                    'period_end'             => $clean($p['installment_end_date'] ?? null),
                    'date_before_due'        => $clean($p['date_before_due_date'] ?? null),
                    'payment_due_date'       => $due,
                    'original_payment_date'  => $clean($p['original_payment_date'] ?? null),
                    'payment_date'           => $clean($p['payment_date']  ?? null),
                    'amount'                 => $numOrZero($p['amount']             ?? 0),
                    'amount_prepayment'      => $numOrZero($p['amount_prepayment']  ?? 0),
                    'is_paid'                => (bool) ($p['is_paid']       ?? false),
                    'is_prepayment'          => (bool) ($p['is_prepayment'] ?? false),
                    'payment_type'           => $p['payment_type']            ?? null,
                    'payment_interval'       => $p['payment_interval']        ?? null,
                    'payment_type_prepayment'=> $p['payment_type_prepayment'] ?? null,
                    'notes'                  => $p['notes']                   ?? null,
                    'notes_prepayment'       => $p['notes_prepayment']        ?? null,
                    'receipt_ref'            => $p['receipt_ref']             ?? null,
                    'receipt_date'           => $clean($p['receipt_date']     ?? null),
                    'updated_by'             => $guard($nullIfZero($p['updated_by'] ?? null), $userIds),
                    'created_at'             => $clean($p['created_at'] ?? null) ?? now(),
                    'source_updated_at'      => $clean($p['updated_at'] ?? null),
                    'loaded_at'              => now(),
                ];
            }
            if ($rows) {
                DB::table('marts.fact_installment')->upsert(
                    $rows, ['installment_id', 'payment_due_date'],
                    ['commercial_contract_id','payment_ref','transaction_id','transaction_date',
                     'lessor_id','lessor_name_snapshot','tenant_id','tenant_name_snapshot',
                     'period_start','period_end','date_before_due','original_payment_date','payment_date',
                     'amount','amount_prepayment','is_paid','is_prepayment',
                     'payment_type','payment_interval','payment_type_prepayment',
                     'notes','notes_prepayment','receipt_ref','receipt_date','updated_by',
                     'source_updated_at','loaded_at']
                );
                $count += count($rows);
            }
        });

        if ($skipped) Log::warning('PaymentDetailEtl: skipped rows', ['count' => $skipped]);
        return ['upserted' => $count, 'deleted' => 0];
    }
}
