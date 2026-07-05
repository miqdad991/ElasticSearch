<?php

use App\Http\Controllers\AssetsDashboardController;
use App\Http\Controllers\AssetsDepreciationDashboardController;
use App\Http\Controllers\BillingDashboardController;
use App\Http\Controllers\ContractsDashboardController;
use App\Http\Controllers\DashboardBuilderController;
use App\Http\Controllers\OverviewDashboardController;
use App\Http\Controllers\ProjectDashboardController;
use App\Http\Controllers\PropertiesDashboardController;
use App\Http\Controllers\SsoController;
use App\Http\Controllers\SyncStatusController;
use App\Http\Controllers\UsersDashboardController;
use App\Http\Controllers\MCDashboard2Controller;
use App\Http\Controllers\MCFollowing2Controller;
use App\Http\Controllers\MCFollowingDashboardController;
use App\Http\Controllers\MCWorkordersDashboardController;
use App\Http\Controllers\WorkOrdersDashboardController;
use Illuminate\Support\Facades\Route;
use OpenSearch\Client;

// SSO endpoints — must NOT be gated by require.sso, they establish/clear it.
Route::get('/sso/consume',  [SsoController::class, 'consume']);
Route::post('/sso/logout',  [SsoController::class, 'logout']);   // back-channel from Osool
Route::get('/logout',       [SsoController::class, 'selfLogout']); // user-initiated

// Everything below requires an SSO session.
Route::middleware('require.sso')->group(function () {
    Route::get('/',          [OverviewDashboardController::class,  'index']);
    Route::get('/overview',  [OverviewDashboardController::class,  'index']);

    Route::get('/project-dashboard',            [ProjectDashboardController::class, 'index']);

    Route::get('/admin/sync-status',       [SyncStatusController::class, 'index']);
    Route::post('/admin/sync-status/run',  function () {
        \Illuminate\Support\Facades\Artisan::queue('sync:cycle');
        return redirect('/admin/sync-status')->with('status', 'sync:cycle queued');
    });

    Route::get('/work-orders', [WorkOrdersDashboardController::class, 'index']);
    Route::get('/properties', [PropertiesDashboardController::class, 'index']);
    Route::get('/assets',     [AssetsDashboardController::class,    'index']);
    Route::get('/assets/availability/export', [AssetsDashboardController::class, 'exportAvailability'])->name('assets.availability.export');
    Route::get('/assets-depreciation', [AssetsDepreciationDashboardController::class, 'index'])->name('assets.depreciation');
    Route::get('/assets-depreciation/export', [AssetsDepreciationDashboardController::class, 'export'])->name('assets.depreciation.export');
    Route::get('/users',      [UsersDashboardController::class,     'index']);
    Route::get('/billing',    [BillingDashboardController::class,   'index']);
    Route::get('/contracts',  [ContractsDashboardController::class, 'index']);

    Route::get('/mc-workorders', [MCWorkordersDashboardController::class, 'index'])->name('mc-workorders.dashboard');
    Route::get('/mc-following',  [MCFollowingDashboardController::class,  'index'])->name('mc-following.dashboard');
    Route::get('/mc-dashboard2', [MCDashboard2Controller::class,          'index'])->name('mc-dashboard2');
    Route::get('/mc-following2', [MCFollowing2Controller::class,          'index'])->name('mc-following2');

    Route::get('/dashboard-builder',          [DashboardBuilderController::class, 'select'])->name('dashboard.builder.select');
    Route::get('/dashboard-builder/{type}',   [DashboardBuilderController::class, 'index'])->name('dashboard.builder')->where('type', 'work-orders|properties|billing|users|assets|contracts|overview');
    Route::post('/dashboard-builder/{type}',  [DashboardBuilderController::class, 'save'])->name('dashboard.builder.save')->where('type', 'work-orders|properties|billing|users|assets|contracts|overview');
    Route::get('/dashboard-preview/{type}',   [DashboardBuilderController::class, 'preview'])->name('dashboard.preview')->where('type', 'work-orders|properties|billing|users|assets|contracts|overview');

    Route::get('/lang/{locale}', function (string $locale) {
        if (in_array($locale, ['en', 'ar'])) {
            session(['locale' => $locale]);
        }
        return redirect()->back();
    })->name('lang.switch');

    Route::get('/opensearch/ping', function (Client $os) {
        return response()->json([
            'ping' => $os->ping(),
            'info' => $os->info(),
        ]);
    });
});
