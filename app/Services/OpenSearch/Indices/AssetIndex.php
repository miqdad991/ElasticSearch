<?php

namespace App\Services\OpenSearch\Indices;

use App\Services\OpenSearch\IndexManager;
use Illuminate\Support\Facades\DB;

class AssetIndex
{
    public const ENTITY = 'assets';

    public function __construct(private IndexManager $im) {}

    public function mapping(): array
    {
        return [
            'properties' => [
                'asset_id'            => ['type' => 'long'],
                'asset_tag'           => ['type' => 'keyword'],
                'owner_user_id'       => ['type' => 'long'],
                'project_ids'         => ['type' => 'integer'],
                'owner_name'          => ['type' => 'keyword'],
                'property_id'         => ['type' => 'long'],
                'property_name'       => ['type' => 'keyword'],
                'building_id'         => ['type' => 'long'],
                'building_name'       => ['type' => 'keyword'],
                'asset_category_id'   => ['type' => 'long'],
                'asset_category'      => ['type' => 'keyword'],
                'asset_name_id'       => ['type' => 'long'],
                'asset_name'          => ['type' => 'keyword'],
                'asset_status_id'     => ['type' => 'integer'],
                'asset_status_name'   => ['type' => 'keyword'],
                'has_status'          => ['type' => 'boolean'],
                'manufacturer_name'   => ['type' => 'keyword'],
                'model_number'        => ['type' => 'keyword'],
                'purchase_date'       => ['type' => 'date', 'format' => 'yyyy-MM-dd'],
                'purchase_amount'     => ['type' => 'scaled_float', 'scaling_factor' => 100],
                'warranty_end_date'   => ['type' => 'date', 'format' => 'yyyy-MM-dd'],
                'under_warranty'      => ['type' => 'boolean'],
                'linked_wo'           => ['type' => 'boolean'],
                'created_at'          => ['type' => 'date'],
                'created_year_month'  => ['type' => 'keyword'],
                'search_text'         => ['type' => 'text'],
            ],
        ];
    }

    public function reindex(?string $since = null, int $chunk = 1000): array
    {
        $newIndex = $since
            ? $this->im->aliasName(self::ENTITY)
            : $this->im->createVersionedIndex(self::ENTITY, $this->mapping());

        $sql = <<<SQL
            SELECT
                a.asset_id, a.asset_tag,
                a.owner_user_id, u.full_name AS owner_name,
                a.property_id, p.property_name,
                a.building_id, b.building_name,
                a.asset_category_id, ac.asset_category,
                a.asset_name_id,     an.asset_name,
                a.asset_status_id,   ast.name AS asset_status_name,
                a.has_status,
                a.manufacturer_name, a.model_number,
                a.purchase_date, a.purchase_amount,
                a.warranty_end_date,
                (a.warranty_end_date IS NOT NULL AND a.warranty_end_date >= CURRENT_DATE) AS under_warranty,
                a.linked_wo,
                a.created_at,
                to_char(a.created_at, 'YYYY-MM') AS created_year_month,
                COALESCE(
                    (SELECT array_agg(bup.project_id) FROM marts.bridge_user_project bup WHERE bup.user_id = a.owner_user_id),
                    '{}'::int[]
                ) AS project_ids
            FROM marts.fact_asset a
            LEFT JOIN marts.dim_user             u   ON u.user_id           = a.owner_user_id
            LEFT JOIN marts.dim_property         p   ON p.property_id       = a.property_id
            LEFT JOIN marts.dim_property_building b  ON b.building_id       = a.building_id
            LEFT JOIN marts.dim_asset_category   ac  ON ac.asset_category_id = a.asset_category_id
            LEFT JOIN marts.dim_asset_name       an  ON an.asset_name_id     = a.asset_name_id
            LEFT JOIN marts.dim_asset_status     ast ON ast.asset_status_id  = a.asset_status_id
        SQL;

        $bindings = [];
        if ($since) {
            $sql .= ' WHERE a.source_updated_at > ?';
            $bindings[] = $since;
        }
        $sql .= ' ORDER BY a.asset_id';

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
                $doc['asset_tag'] ?? null, $doc['asset_name'] ?? null,
                $doc['asset_category'] ?? null, $doc['manufacturer_name'] ?? null,
            ])));

            $buffer[] = ['index' => ['_index' => $newIndex, '_id' => $doc['asset_id']]];
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
