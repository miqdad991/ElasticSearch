<?php

namespace App\Services\OpenSearch\Indices;

use App\Services\OpenSearch\IndexManager;
use Illuminate\Support\Facades\DB;

class CommercialContractIndex
{
    public const ENTITY = 'commercial_contracts';

    public function __construct(private IndexManager $im) {}

    public function mapping(): array
    {
        return [
            'properties' => [
                'commercial_contract_id'  => ['type' => 'long'],
                'reference_number'        => ['type' => 'keyword'],
                'contract_name'           => ['type' => 'text', 'fields' => ['raw' => ['type' => 'keyword']]],
                'contract_type'           => ['type' => 'keyword'],
                'tenant_id'               => ['type' => 'long'],
                'tenant_name'             => ['type' => 'keyword'],
                'property_id'             => ['type' => 'long'],
                'property_name'           => ['type' => 'keyword'],
                'building_id'             => ['type' => 'long'],
                'project_id'              => ['type' => 'integer'],
                'ejar_sync_status'        => ['type' => 'keyword'],
                'start_date'              => ['type' => 'date', 'format' => 'yyyy-MM-dd'],
                'end_date'                => ['type' => 'date', 'format' => 'yyyy-MM-dd'],
                'amount'                  => ['type' => 'scaled_float', 'scaling_factor' => 100],
                'security_deposit_amount' => ['type' => 'scaled_float', 'scaling_factor' => 100],
                'late_fees_charge'        => ['type' => 'scaled_float', 'scaling_factor' => 100],
                'brokerage_fee'           => ['type' => 'scaled_float', 'scaling_factor' => 100],
                'retainer_fee'            => ['type' => 'scaled_float', 'scaling_factor' => 100],
                'payment_due'             => ['type' => 'scaled_float', 'scaling_factor' => 100],
                'payment_overdue'         => ['type' => 'scaled_float', 'scaling_factor' => 100],
                'currency'                => ['type' => 'keyword'],
                'is_active'               => ['type' => 'boolean'],
                'auto_renewal'            => ['type' => 'boolean'],
                'is_deleted'              => ['type' => 'boolean'],
                'created_at'              => ['type' => 'date'],
                'created_year_month'      => ['type' => 'keyword'],
            ],
        ];
    }

    public function reindex(?string $since = null, int $chunk = 1000): array
    {
        $newIndex = $since ? $this->im->aliasName(self::ENTITY)
                           : $this->im->createVersionedIndex(self::ENTITY, $this->mapping());

        $sql = <<<SQL
            SELECT
                c.commercial_contract_id, c.reference_number, c.contract_name,
                c.contract_type::text AS contract_type,
                c.tenant_id, u.full_name AS tenant_name,
                c.property_id, p.property_name,
                c.building_id, c.project_id,
                c.ejar_sync_status::text AS ejar_sync_status,
                c.start_date, c.end_date,
                c.amount, c.security_deposit_amount, c.late_fees_charge,
                c.brokerage_fee, c.retainer_fee, c.payment_due, c.payment_overdue,
                c.currency, c.is_active, c.auto_renewal, c.is_deleted,
                c.created_at,
                to_char(c.created_at, 'YYYY-MM') AS created_year_month
            FROM marts.fact_commercial_contract c
            LEFT JOIN marts.dim_user     u ON u.user_id     = c.tenant_id
            LEFT JOIN marts.dim_property p ON p.property_id = c.property_id
        SQL;
        $bindings = [];
        if ($since) { $sql .= ' WHERE c.source_updated_at > ?'; $bindings[] = $since; }

        $total = 0; $buffer = [];
        foreach (DB::cursor($sql, $bindings) as $row) {
            $doc = (array) $row;
            foreach (['created_at'] as $f) {
                if (!empty($doc[$f])) $doc[$f] = \Carbon\Carbon::parse($doc[$f])->utc()->format('Y-m-d\TH:i:s\Z');
            }
            $buffer[] = ['index' => ['_index' => $newIndex, '_id' => $doc['commercial_contract_id']]];
            $buffer[] = $doc;
            $total++;
            if (count($buffer) >= $chunk * 2) { $this->im->bulk($buffer); $buffer = []; }
        }
        $this->im->bulk($buffer);
        if (!$since) $this->im->swapAlias(self::ENTITY, $newIndex);
        return ['index' => $newIndex, 'docs' => $total];
    }
}
