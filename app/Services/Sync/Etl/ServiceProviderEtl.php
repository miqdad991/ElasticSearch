<?php

namespace App\Services\Sync\Etl;

use Illuminate\Support\Facades\DB;

class ServiceProviderEtl implements TableEtl
{
    public function transform(): array
    {
        $upserted = 0;

        // Walk raw.service_providers in chunks — same payload shape as MySQL source.
        DB::table('raw.service_providers')
            ->orderBy('id')
            ->chunk(1000, function ($chunk) use (&$upserted) {
                $rows = [];
                foreach ($chunk as $r) {
                    $p = json_decode($r->payload, true) ?? [];
                    $rows[] = [
                        'sp_id'             => (int) ($p['id'] ?? $r->id),
                        'name'              => (string) ($p['service_provider_name'] ?? $p['name'] ?? ''),
                        'status'            => isset($p['status']) ? (int) $p['status'] : null,
                        'is_deleted'        => ($p['is_deleted'] ?? 'no') === 'yes',
                        'source_updated_at' => $p['modified_at'] ?? null,
                        'loaded_at'         => now(),
                    ];
                }
                DB::table('marts.dim_service_provider')->upsert(
                    $rows,
                    ['sp_id'],
                    ['name', 'status', 'is_deleted', 'source_updated_at', 'loaded_at']
                );
                $upserted += count($rows);
            });

        return ['upserted' => $upserted, 'deleted' => 0];
    }
}
