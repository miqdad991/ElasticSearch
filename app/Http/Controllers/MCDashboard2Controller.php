<?php

namespace App\Http\Controllers;

use App\Services\Dashboard\PropertyMapService;
use App\Services\Dashboard\WorkOrderTotalsService;
use Illuminate\Http\Request;

class MCDashboard2Controller extends Controller
{
    public function __construct(
        private PropertyMapService $propertyMap,
        private WorkOrderTotalsService $woTotals,
    ) {}

    public function index(Request $request)
    {
        $projectId = session('selected_project_id');
        $filters   = $request->only(['date_from', 'date_to', 'user_id', 'contract_id']);

        $propertyMapData    = $this->propertyMap->build($projectId);
        $buildingNames      = $this->propertyMap->buildingNames($projectId);
        $totals             = $this->woTotals->totals($projectId ? (int) $projectId : null, $filters);
        $expensesByCategory = $this->woTotals->expensesByCategory($projectId ? (int) $projectId : null, 5, $filters);
        $filterOptions      = $this->woTotals->filterOptions($projectId ? (int) $projectId : null);

        $expensesTotal = $expensesByCategory->sum('total');
        $expensesTotalFormatted = $expensesTotal >= 1_000_000
            ? number_format($expensesTotal / 1_000_000, 2) . ' M'
            : ($expensesTotal >= 1_000 ? number_format($expensesTotal / 1_000, 1) . ' K' : number_format($expensesTotal, 0));

        $lateExecution = $totals['late_execution'];
        $totalWOs      = $totals['total'];
        $latePct       = $totalWOs > 0 ? min(100, round(($lateExecution / $totalWOs) * 100)) : 0;
        $lateLabel     = number_format($lateExecution) . ' / ' . number_format($totalWOs);

        return view('dashboards.mc-dashboard2', array_merge(
            compact(
                'propertyMapData', 'totals', 'expensesByCategory', 'expensesTotal', 'expensesTotalFormatted',
                'latePct', 'lateLabel', 'filters', 'buildingNames'
            ),
            $filterOptions
        ));
    }
}
