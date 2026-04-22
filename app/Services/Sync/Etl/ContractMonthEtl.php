<?php

namespace App\Services\Sync\Etl;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ContractMonthEtl implements TableEtl
{
    public function transform(): array
    {
        $contractIds = array_flip(DB::table('marts.dim_contract')->pluck('contract_id')->all());

        $clean = fn ($v) => ($v === null || $v === '' || str_starts_with((string) $v, '0000-00-00')) ? null : $v;
        $numOrZero  = fn ($v) => is_numeric($v) ? (float) $v : 0.0;
        $nullIfZero = fn ($v) => (is_numeric($v) && (int) $v > 0) ? (int) $v : null;

        $count = 0; $skipped = 0;
        DB::table('raw.contract_months')->orderBy('id')->chunk(1000, function ($chunk) use (
            &$count, &$skipped, $contractIds, $clean, $numOrZero, $nullIfZero
        ) {
            $rows = [];
            foreach ($chunk as $r) {
                $p = json_decode($r->payload, true) ?? [];

                $cid = $nullIfZero($p['contract_id'] ?? null);
                if ($cid === null || !isset($contractIds[$cid])) { $skipped++; continue; }

                $month = $this->parseMonth((string) ($p['month'] ?? ''));
                if (!$month) { $skipped++; continue; }
                $year = (int) substr($month, 0, 4);
                if ($year < 2015 || $year > 2030) { $skipped++; continue; }

                $rows[] = [
                    'contract_month_id'    => (int) ($p['id'] ?? $r->id),
                    'contract_id'          => $cid,
                    'user_id'              => $nullIfZero($p['user_id'] ?? null),
                    'month'                => $month,
                    'amount'               => $numOrZero($p['amount'] ?? 0),
                    'is_paid'              => (bool) ($p['is_paid'] ?? false),
                    'is_extended_contract' => (bool) ($p['is_extended_contract'] ?? false),
                    'bill_id'              => $nullIfZero($p['bill_id'] ?? null),
                    'created_at'           => $clean($p['created_at'] ?? null) ?? now(),
                    'source_updated_at'    => $clean($p['updated_at'] ?? null),
                    'loaded_at'            => now(),
                ];
            }
            if ($rows) {
                DB::table('marts.fact_contract_month')->upsert(
                    $rows, ['contract_month_id', 'month'],
                    ['contract_id','user_id','amount','is_paid','is_extended_contract','bill_id','source_updated_at','loaded_at']
                );
                $count += count($rows);
            }
        });

        if ($skipped) Log::warning('ContractMonthEtl: skipped rows', ['count' => $skipped]);
        return ['upserted' => $count, 'deleted' => 0];
    }

    /** Parse the VARCHAR `month` into YYYY-MM-01. Accepts YYYY-MM-DD, YYYY-MM, and "Month YYYY". */
    private function parseMonth(string $s): ?string
    {
        $s = trim($s);
        if ($s === '') return null;
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) return substr($s, 0, 7) . '-01';
        if (preg_match('/^\d{4}-\d{2}$/',       $s)) return $s . '-01';
        try {
            $d = Carbon::parse($s);
            return $d->format('Y-m') . '-01';
        } catch (\Throwable) {
            return null;
        }
    }
}
