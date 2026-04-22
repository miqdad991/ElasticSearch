<?php

namespace App\Services\OpenSearch\Indices;

use App\Services\OpenSearch\IndexManager;
use Illuminate\Support\Facades\DB;

class PropertyIndex
{
    public const ENTITY = 'properties';

    public function __construct(private IndexManager $im) {}

    public function mapping(): array
    {
        return [
            'properties' => [
                'property_id'        => ['type' => 'long'],
                'owner_user_id'      => ['type' => 'long'],
                'project_ids'        => ['type' => 'integer'],
                'owner_name'         => ['type' => 'keyword'],
                'property_name'      => ['type' => 'text', 'fields' => ['raw' => ['type' => 'keyword']]],
                'property_tag'       => ['type' => 'keyword'],
                'property_type'      => ['type' => 'keyword'],
                'location_type'      => ['type' => 'keyword'],
                'property_usage'     => ['type' => 'keyword'],
                'region_id'          => ['type' => 'integer'],
                'region_name'        => ['type' => 'keyword'],
                'city_id'            => ['type' => 'long'],
                'city_name'          => ['type' => 'keyword'],
                'district_name'      => ['type' => 'keyword'],
                'street_name'        => ['type' => 'keyword'],
                'buildings_count'    => ['type' => 'integer'],
                'total_floors'       => ['type' => 'integer'],
                'total_units'        => ['type' => 'integer'],
                'status'             => ['type' => 'short'],
                'is_active'          => ['type' => 'boolean'],
                'is_deleted'         => ['type' => 'boolean'],
                'contract_count'     => ['type' => 'integer'],
                'rent_count'         => ['type' => 'integer'],
                'lease_count'        => ['type' => 'integer'],
                'active_contracts'   => ['type' => 'integer'],
                'auto_renewal_count' => ['type' => 'integer'],
                'total_budget'       => ['type' => 'scaled_float', 'scaling_factor' => 100],
                'created_at'         => ['type' => 'date'],
                'created_year_month' => ['type' => 'keyword'],
                'search_text'        => ['type' => 'text'],
            ],
        ];
    }

    public function reindex(?string $since = null, int $chunk = 1000): array
    {
        $newIndex = $since
            ? $this->im->aliasName(self::ENTITY)
            : $this->im->createVersionedIndex(self::ENTITY, $this->mapping());

        // Refresh the contract rollup MV so top properties / contract counts are current.
        DB::statement('REFRESH MATERIALIZED VIEW reports.mv_property_contract_rollup');

        $sql = <<<SQL
            SELECT
                p.property_id, p.owner_user_id, u.full_name AS owner_name,
                p.property_name, p.property_tag,
                p.property_type::text AS property_type,
                p.location_type::text AS location_type,
                p.property_usage,
                p.region_id, r.name AS region_name,
                p.city_id,   c.name_en AS city_name,
                p.district_name, p.street_name,
                p.buildings_count, p.total_floors, p.total_units,
                p.status, p.is_active, p.is_deleted,
                COALESCE(rl.contract_count, 0)     AS contract_count,
                COALESCE(rl.rent_count, 0)         AS rent_count,
                COALESCE(rl.lease_count, 0)        AS lease_count,
                COALESCE(rl.active_contracts, 0)   AS active_contracts,
                COALESCE(rl.auto_renewal_count, 0) AS auto_renewal_count,
                COALESCE(rl.total_budget, 0)       AS total_budget,
                p.created_at,
                to_char(p.created_at, 'YYYY-MM') AS created_year_month,
                COALESCE(
                    (SELECT array_agg(bup.project_id) FROM marts.bridge_user_project bup WHERE bup.user_id = p.owner_user_id),
                    '{}'::int[]
                ) AS project_ids
            FROM marts.dim_property p
            LEFT JOIN marts.dim_user   u ON u.user_id   = p.owner_user_id
            LEFT JOIN marts.dim_region r ON r.region_id = p.region_id
            LEFT JOIN marts.dim_city   c ON c.city_id   = p.city_id
            LEFT JOIN reports.mv_property_contract_rollup rl ON rl.property_id = p.property_id
        SQL;

        $sql .= ' WHERE NOT p.is_deleted'; // exclude soft-deleted
        $bindings = [];
        if ($since) {
            $sql .= ' AND p.source_updated_at > ?';
            $bindings[] = $since;
        }
        $sql .= ' ORDER BY p.property_id';

        $tsFields = ['created_at'];
        $total = 0; $buffer = [];
        foreach (DB::cursor($sql, $bindings) as $row) {
            $doc = (array) $row;
            $raw = $doc['project_ids'] ?? '{}';
            $doc['project_ids'] = is_array($raw) ? $raw
                : (($raw === '{}' || !$raw) ? [] : array_map('intval', explode(',', trim($raw,'{}'))));
            foreach ($tsFields as $f) {
                if (!empty($doc[$f])) {
                    $doc[$f] = \Carbon\Carbon::parse($doc[$f])->utc()->format('Y-m-d\TH:i:s\Z');
                }
            }
            $doc['search_text'] = trim(implode(' ', array_filter([
                $doc['property_name'] ?? null, $doc['property_tag'] ?? null,
                $doc['region_name']   ?? null, $doc['city_name']    ?? null,
            ])));

            $buffer[] = ['index' => ['_index' => $newIndex, '_id' => $doc['property_id']]];
            $buffer[] = $doc;
            $total++;
            if (count($buffer) >= $chunk * 2) { $this->im->bulk($buffer); $buffer = []; }
        }
        $this->im->bulk($buffer);

        if (!$since) {
            $this->im->swapAlias(self::ENTITY, $newIndex);
        }
        return ['index' => $newIndex, 'docs' => $total];
    }
}
