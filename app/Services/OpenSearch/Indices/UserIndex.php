<?php

namespace App\Services\OpenSearch\Indices;

use App\Services\OpenSearch\IndexManager;
use Illuminate\Support\Facades\DB;

class UserIndex
{
    public const ENTITY = 'users';

    public function __construct(private IndexManager $im) {}

    public function mapping(): array
    {
        return [
            'properties' => [
                'user_id'            => ['type' => 'long'],
                'email'              => ['type' => 'keyword'],
                'full_name'          => ['type' => 'text', 'fields' => ['raw' => ['type' => 'keyword']]],
                'phone'              => ['type' => 'keyword'],
                'user_type'          => ['type' => 'keyword'],
                'user_type_label'    => ['type' => 'keyword'],
                'status'             => ['type' => 'short'],
                'is_active'          => ['type' => 'boolean'],
                'is_deleted'         => ['type' => 'boolean'],
                'city_id'            => ['type' => 'long'],
                'city_name'          => ['type' => 'keyword'],
                'project_ids'        => ['type' => 'long'],
                'last_login_at'      => ['type' => 'date'],
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

        $sql = <<<SQL
            SELECT
                u.user_id, u.email, u.full_name, u.phone,
                u.user_type::text AS user_type, u.user_type_label,
                u.status, u.is_active, u.is_deleted,
                u.city_id, c.name_en AS city_name,
                u.last_login_at, u.created_at,
                to_char(u.created_at, 'YYYY-MM') AS created_year_month,
                COALESCE(
                    (SELECT array_agg(bup.project_id) FROM marts.bridge_user_project bup WHERE bup.user_id = u.user_id),
                    '{}'::int[]
                ) AS project_ids
            FROM marts.dim_user u
            LEFT JOIN marts.dim_city c ON c.city_id = u.city_id
        SQL;
        $bindings = [];
        if ($since) { $sql .= ' WHERE u.source_updated_at > ?'; $bindings[] = $since; }
        $sql .= ' ORDER BY u.user_id';

        $tsFields = ['created_at','last_login_at'];
        $total = 0; $buffer = [];
        foreach (DB::cursor($sql, $bindings) as $row) {
            $doc = (array) $row;
            foreach ($tsFields as $f) {
                if (!empty($doc[$f])) {
                    $doc[$f] = \Carbon\Carbon::parse($doc[$f])->utc()->format('Y-m-d\TH:i:s\Z');
                }
            }
            $raw = $doc['project_ids'] ?? '{}';
            $doc['project_ids'] = is_array($raw) ? $raw
                : (($raw === '{}' || !$raw) ? [] : array_map('intval', explode(',', trim($raw,'{}'))));
            $doc['search_text'] = trim(implode(' ', array_filter([
                $doc['full_name'] ?? null, $doc['email'] ?? null, $doc['phone'] ?? null,
            ])));

            $buffer[] = ['index' => ['_index' => $newIndex, '_id' => $doc['user_id']]];
            $buffer[] = $doc;
            $total++;
            if (count($buffer) >= $chunk * 2) { $this->im->bulk($buffer); $buffer = []; }
        }
        $this->im->bulk($buffer);

        if (!$since) $this->im->swapAlias(self::ENTITY, $newIndex);
        return ['index' => $newIndex, 'docs' => $total];
    }
}
