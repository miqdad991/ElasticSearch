<?php

namespace App\Services\Sync\Etl;

use Illuminate\Support\Facades\DB;

class PackageEtl implements TableEtl
{
    public function transform(): array
    {
        $clean = fn ($v) => ($v === null || $v === '' || str_starts_with((string) $v, '0000-00-00')) ? null : $v;
        $num   = fn ($v) => is_numeric($v) ? (float) $v : 0.0;

        $count = 0;
        DB::table('raw.packages')->orderBy('id')->chunk(500, function ($chunk) use (&$count, $clean, $num) {
            $rows = [];
            foreach ($chunk as $r) {
                $p = json_decode($r->payload, true) ?? [];
                $rows[] = [
                    'package_id'        => (int) ($p['id'] ?? $r->id),
                    'name'              => (string) ($p['name'] ?? 'Unnamed'),
                    'pricing_model'     => $p['pricing_model'] ?? null,
                    'price'             => $num($p['price']    ?? 0),
                    'discount'          => $num($p['discount'] ?? 0),
                    'status'            => strtolower((string) ($p['status'] ?? '')),
                    'most_popular'      => (bool) ($p['most_popular'] ?? false),
                    'created_at'        => $clean($p['created_at'] ?? null) ?? now(),
                    'source_updated_at' => $clean($p['updated_at'] ?? null),
                    'loaded_at'         => now(),
                ];
            }
            DB::table('marts.dim_subscription_package')->upsert(
                $rows, ['package_id'],
                ['name','pricing_model','price','discount','status','most_popular','source_updated_at','loaded_at']
            );
            $count += count($rows);
        });
        return ['upserted' => $count, 'deleted' => 0];
    }
}
