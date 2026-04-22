<?php

namespace App\Services\Sync\Etl;

use Illuminate\Support\Facades\DB;

class ContractTypeEtl implements TableEtl
{
    public function transform(): array
    {
        $count = 0;
        DB::table('raw.contract_types')->orderBy('id')->chunk(500, function ($chunk) use (&$count) {
            $rows = [];
            foreach ($chunk as $r) {
                $p = json_decode($r->payload, true) ?? [];
                $rows[] = [
                    'contract_type_id' => (int) ($p['id'] ?? $r->id),
                    'name'             => (string) ($p['name'] ?? 'Unnamed'),
                    'slug'             => $p['slug'] ?? null,
                    'loaded_at'        => now(),
                ];
            }
            DB::table('marts.dim_contract_type')->upsert($rows, ['contract_type_id'], ['name', 'slug', 'loaded_at']);
            $count += count($rows);
        });
        return ['upserted' => $count, 'deleted' => 0];
    }
}
