<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ChecksRoles;
use App\Services\Depreciation\DepreciationQuery;
use Illuminate\Http\Request;

class AssetsDepreciationDashboardController extends Controller
{
    use ChecksRoles;

    public function index(Request $request)
    {
        // Finance/POA/BMA/Admin only — no-op until config('dashboards.enforce_roles') is on.
        $this->requireAnyRole(config('dashboards.depreciation_roles', []));

        $filters = array_filter([
            'property_id'         => $request->query('property_id'),
            'region_id'           => $request->query('region_id'),
            'unit_id'             => $request->query('unit_id'),
            'supplier'            => $request->query('supplier'),
            'service_type'        => $request->query('service_type'),
            'depreciation_method' => $request->query('depreciation_method'),
            'asset_status_id'     => $request->query('asset_status_id'),
        ], fn ($v) => $v !== null && $v !== '');

        $reportingDate = $request->query('date') ?: date('Y-m-d');

        $query = new DepreciationQuery($filters + [
            'reporting_date' => $reportingDate,
            'project_id'     => session('selected_project_id'),
        ]);

        $options = DepreciationQuery::filterOptions();

        return view('dashboards.assets-depreciation', [
            'filters'       => $filters,
            'reportingDate' => $reportingDate,
            'kpis'          => $query->kpis(),
            'rows'          => $query->rows(),
            'options'       => $options,
            'serviceTypes'  => ['hard', 'soft'],
            'charts'        => [
                'by_property'    => $query->byProperty(),
                'remaining_life' => $query->usefulLifeBuckets(),
            ],
        ]);
    }
}
