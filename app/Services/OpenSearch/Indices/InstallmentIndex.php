<?php

namespace App\Services\OpenSearch\Indices;

use App\Services\OpenSearch\IndexManager;
use Illuminate\Support\Facades\DB;

class InstallmentIndex
{
    public const ENTITY = 'installments';

    public function __construct(private IndexManager $im) {}

    public function mapping(): array
    {
        return [
            'properties' => [
                'installment_id'          => ['type' => 'long'],
                'commercial_contract_id'  => ['type' => 'long'],
                'contract_reference'      => ['type' => 'keyword'],
                'contract_type'           => ['type' => 'keyword'],
                'project_id'              => ['type' => 'integer'],
                'tenant_id'               => ['type' => 'long'],
                'tenant_name'             => ['type' => 'keyword'],
                'payment_ref'             => ['type' => 'keyword'],
                'payment_type'            => ['type' => 'keyword'],
                'payment_due_date'        => ['type' => 'date', 'format' => 'yyyy-MM-dd'],
                'payment_date'            => ['type' => 'date', 'format' => 'yyyy-MM-dd'],
                'due_year_month'          => ['type' => 'keyword'],
                'amount'                  => ['type' => 'scaled_float', 'scaling_factor' => 100],
                'is_paid'                 => ['type' => 'boolean'],
                'is_prepayment'           => ['type' => 'boolean'],
                'is_overdue'              => ['type' => 'boolean'],
                'days_overdue'            => ['type' => 'integer'],
                'aging_bucket'            => ['type' => 'keyword'],
            ],
        ];
    }

    public function reindex(?string $since = null, int $chunk = 1000): array
    {
        $newIndex = $since ? $this->im->aliasName(self::ENTITY)
                           : $this->im->createVersionedIndex(self::ENTITY, $this->mapping());

        $sql = <<<SQL
            SELECT
                i.installment_id, i.commercial_contract_id,
                c.reference_number AS contract_reference,
                c.contract_type::text AS contract_type,
                c.project_id,
                i.tenant_id, COALESCE(u.full_name, i.tenant_name_snapshot) AS tenant_name,
                i.payment_ref, i.payment_type,
                i.payment_due_date, i.payment_date,
                to_char(i.payment_due_date, 'YYYY-MM') AS due_year_month,
                i.amount, i.is_paid, i.is_prepayment,
                (NOT i.is_paid AND i.payment_due_date < CURRENT_DATE) AS is_overdue,
                GREATEST(0, (CURRENT_DATE - i.payment_due_date))      AS days_overdue,
                CASE
                    WHEN i.is_paid THEN 'paid'
                    WHEN i.payment_due_date >= CURRENT_DATE THEN 'future'
                    WHEN (CURRENT_DATE - i.payment_due_date) BETWEEN 1 AND 30 THEN '0-30'
                    WHEN (CURRENT_DATE - i.payment_due_date) BETWEEN 31 AND 60 THEN '31-60'
                    WHEN (CURRENT_DATE - i.payment_due_date) BETWEEN 61 AND 90 THEN '61-90'
                    ELSE '90+'
                END AS aging_bucket
            FROM marts.fact_installment i
            JOIN marts.fact_commercial_contract c ON c.commercial_contract_id = i.commercial_contract_id
            LEFT JOIN marts.dim_user u ON u.user_id = i.tenant_id
            WHERE NOT c.is_deleted
        SQL;
        $bindings = [];
        if ($since) { $sql .= ' AND i.source_updated_at > ?'; $bindings[] = $since; }

        $total = 0; $buffer = [];
        foreach (DB::cursor($sql, $bindings) as $row) {
            $doc = (array) $row;
            $buffer[] = ['index' => ['_index' => $newIndex, '_id' => $doc['installment_id'] . '-' . $doc['payment_due_date']]];
            $buffer[] = $doc;
            $total++;
            if (count($buffer) >= $chunk * 2) { $this->im->bulk($buffer); $buffer = []; }
        }
        $this->im->bulk($buffer);
        if (!$since) $this->im->swapAlias(self::ENTITY, $newIndex);
        return ['index' => $newIndex, 'docs' => $total];
    }
}
