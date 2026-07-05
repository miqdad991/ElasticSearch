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

        [$query, $filters, $reportingDate] = $this->buildQuery($request);
        $options = DepreciationQuery::filterOptions();

        return view('dashboards.assets-depreciation', [
            'filters'       => $filters,
            'reportingDate' => $reportingDate,
            'kpis'          => $query->kpis(),
            'rows'          => $query->rows(),
            'options'       => $options,
            'serviceTypes'  => ['hard', 'soft'],
            'charts'        => [
                'by_property'     => $query->byProperty(),
                'remaining_life'  => $query->usefulLifeBuckets(),
                'by_supplier'     => $query->breakdown('supplier'),
                'by_category'     => $query->breakdown('category'),
                'by_method'       => $query->breakdown('method'),
                'by_service_type' => $query->breakdown('service_type'),
            ],
        ]);
    }

    /**
     * Export the depreciation detail table to an Excel-readable file, honouring the
     * active filters + reporting date. Dependency-free CSV with a UTF-8 BOM so Excel
     * renders the Arabic headers. Mirrors the on-screen table's 21 columns.
     */
    public function export(Request $request)
    {
        $this->requireAnyRole(config('dashboards.depreciation_roles', []));

        [$query, , $reportingDate] = $this->buildQuery($request);
        $rows = $query->rows(10000);

        $money = fn ($v) => $v === null ? '' : number_format((float) $v, 2, '.', '');
        $date  = fn ($v) => $v ? \Illuminate\Support\Carbon::parse($v)->format('Y-m-d') : '';

        $headers = [
            __('depreciation.col_property'), __('depreciation.col_zone'), __('depreciation.col_unit'),
            __('depreciation.col_asset_name'), __('depreciation.col_service_type'), __('depreciation.col_asset_number'),
            __('depreciation.col_asset_tag'), __('depreciation.col_supplier'), __('depreciation.col_purchase'),
            __('depreciation.col_install_date'), __('depreciation.col_dep_period'), __('depreciation.col_useful_life'),
            __('depreciation.col_method'), __('depreciation.col_rate'), __('depreciation.col_residual'),
            __('depreciation.col_annual'), __('depreciation.col_accum'), __('depreciation.col_nbv'),
            __('depreciation.col_last_maint'), __('depreciation.col_maint_ytd'), __('depreciation.col_wo_count'),
        ];

        $callback = function () use ($headers, $rows, $money, $date) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM for Excel
            fputcsv($out, $headers);
            foreach ($rows as $r) {
                fputcsv($out, [
                    $r->property_name ?? '',
                    $r->zone_name ?? '',
                    $r->unit_id ?? '',
                    $r->asset_name ?? '',
                    $r->service_type ?? '',
                    $r->asset_number ?? '',
                    $r->asset_tag ?? '',
                    $r->supplier_name ?? '',
                    $money($r->purchase_price),
                    $date($r->installation_date),
                    trim($date($r->depreciation_start_date) . ' → ' . $date($r->depreciation_end_date), ' →'),
                    $r->useful_life_years !== null ? rtrim(rtrim(number_format($r->useful_life_years, 2, '.', ''), '0'), '.') : '',
                    $r->depreciation_method ?? '',
                    $r->depreciation_rate !== null ? number_format($r->depreciation_rate, 2, '.', '') : '',
                    $money($r->residual_value),
                    $money($r->annual_dep),
                    $money($r->accumulated_dep),
                    $money($r->nbv),
                    '', '', '', // last maintenance / maint YTD / WO count — pending OS3-3963
                ]);
            }
            fclose($out);
        };

        $filename = 'assets-depreciation-' . $reportingDate . '.csv';

        return response()->streamDownload($callback, $filename, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /** Build the DepreciationQuery from request filters. Shared by index() and export(). */
    private function buildQuery(Request $request): array
    {
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

        return [$query, $filters, $reportingDate];
    }
}
