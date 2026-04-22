<?php

namespace App\Http\Controllers;

use OpenSearch\Client;

class ProjectDashboardController extends Controller
{
    public function __construct(private Client $os) {}

    public function index()
    {
        $projectId = session('selected_project_id');
        if (!$projectId) return redirect('/select-project');

        $prefix = config('opensearch.index_prefix', 'osool_');

        // Project doc
        try {
            $project = $this->os->get(['index' => $prefix . 'projects', 'id' => $projectId])['_source'] ?? null;
        } catch (\Throwable) {
            session()->forget(['selected_project_id', 'selected_project_name']);
            return redirect('/select-project');
        }

        $count = fn (string $entity, array $filter) => $this->os->count([
            'index' => $prefix . $entity,
            'body'  => ['query' => ['bool' => ['must' => $filter]]],
        ])['count'] ?? 0;

        $byPidArr = [['term' => ['project_ids' => (int) $projectId]]];
        $byPid    = [['term' => ['project_id'  => (int) $projectId]]];

        $totalAssets    = $count('assets',    $byPidArr);
        $totalWOs       = $count('work_orders', $byPidArr);
        $totalContracts = $count('commercial_contracts', $byPid);
        $totalUsers     = $count('users', $byPidArr);

        // Financial sums from commercial contracts
        $fin = $this->os->search([
            'index' => $prefix . 'commercial_contracts',
            'body'  => [
                'size'  => 0,
                'query' => ['bool' => ['must' => $byPid]],
                'aggs'  => [
                    'total_value' => ['sum' => ['field' => 'amount']],
                    'due'         => ['sum' => ['field' => 'payment_due']],
                    'overdue'     => ['sum' => ['field' => 'payment_overdue']],
                    'security'    => ['sum' => ['field' => 'security_deposit_amount']],
                    'late'        => ['sum' => ['field' => 'late_fees_charge']],
                    'brokerage'   => ['sum' => ['field' => 'brokerage_fee']],
                    'retainer'    => ['sum' => ['field' => 'retainer_fee']],
                ],
            ],
        ]);
        $f = $fin['aggregations'] ?? [];

        $cards = [
            'overview' => [
                'Total Assets'      => $totalAssets,
                'Total Work Orders' => $totalWOs,
                'Total Contracts'   => $totalContracts,
                'Total Users'       => $totalUsers,
            ],
            'financial' => [
                'Contract Value'    => round($f['total_value']['value'] ?? 0, 2),
                'Security Deposits' => round($f['security']['value']    ?? 0, 2),
                'Payment Due'       => round($f['due']['value']         ?? 0, 2),
                'Payment Overdue'   => round($f['overdue']['value']     ?? 0, 2),
                'Late Fees'         => round($f['late']['value']        ?? 0, 2),
                'Brokerage Fees'    => round($f['brokerage']['value']   ?? 0, 2),
                'Retainer Fees'     => round($f['retainer']['value']    ?? 0, 2),
            ],
        ];

        return view('project-dashboard.index', [
            'project' => $project,
            'cards'   => $cards,
        ]);
    }
}
