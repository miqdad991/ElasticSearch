<?php

use App\Http\Controllers\AssetsDashboardController;
use App\Http\Controllers\BillingDashboardController;
use App\Http\Controllers\ContractsDashboardController;
use App\Http\Controllers\OverviewDashboardController;
use App\Http\Controllers\ProjectDashboardController;
use App\Http\Controllers\ProjectSelectController;
use App\Http\Controllers\PropertiesDashboardController;
use App\Http\Controllers\SyncStatusController;
use App\Http\Controllers\UsersDashboardController;
use App\Http\Controllers\MCDashboard2Controller;
use App\Http\Controllers\MCFollowingDashboardController;
use App\Http\Controllers\MCWorkordersDashboardController;
use App\Http\Controllers\WorkOrdersDashboardController;
use Illuminate\Support\Facades\Route;
use OpenSearch\Client;

Route::get('/',          [OverviewDashboardController::class,  'index']);
Route::get('/overview',  [OverviewDashboardController::class,  'index']);

Route::get('/select-project',               [ProjectSelectController::class, 'index']);
Route::post('/select-project/{projectId}',  [ProjectSelectController::class, 'select'])->whereNumber('projectId');
Route::post('/exit-project',                [ProjectSelectController::class, 'exit']);
Route::get('/project-dashboard',            [ProjectDashboardController::class, 'index']);

Route::get('/admin/sync-status',       [SyncStatusController::class, 'index']);
Route::post('/admin/sync-status/run',  function () {
    \Illuminate\Support\Facades\Artisan::queue('sync:cycle');
    return redirect('/admin/sync-status')->with('status', 'sync:cycle queued');
});

Route::get('/work-orders', [WorkOrdersDashboardController::class, 'index']);
Route::get('/properties', [PropertiesDashboardController::class, 'index']);
Route::get('/assets',     [AssetsDashboardController::class,    'index']);
Route::get('/users',      [UsersDashboardController::class,     'index']);
Route::get('/billing',    [BillingDashboardController::class,   'index']);
Route::get('/contracts',  [ContractsDashboardController::class, 'index']);

Route::get('/mc-workorders', [MCWorkordersDashboardController::class, 'index'])->name('mc-workorders.dashboard');
Route::get('/mc-following',  [MCFollowingDashboardController::class,  'index'])->name('mc-following.dashboard');
Route::get('/mc-dashboard2', [MCDashboard2Controller::class,          'index'])->name('mc-dashboard2');

Route::get('/opensearch/ping', function (Client $os) {
    return response()->json([
        'ping' => $os->ping(),
        'info' => $os->info(),
    ]);
});
