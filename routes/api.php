<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// API Health Check
Route::get('/health', function () {
    return response()->json([
        'status' => 'success',
        'message' => 'Hawk-i API is running',
        'timestamp' => now()->toISOString()
    ]);
});

// API Version 1 Routes
Route::prefix('v1')->group(function () {
    // Add your API routes here
    Route::get('/', function () {
        return response()->json([
            'message' => 'Hawk-i API v1',
            'version' => '1.0.0'
        ]);
    });

    // ADO Sync Routes
    Route::prefix('ado-sync')->group(function () {
        Route::get('/status', [App\Http\Controllers\ADOSyncController::class, 'status']);
        Route::post('/sync-all', [App\Http\Controllers\ADOSyncController::class, 'syncAll']);
        Route::post('/sync-projects', [App\Http\Controllers\ADOSyncController::class, 'syncProjects']);
        Route::post('/sync-users', [App\Http\Controllers\ADOSyncController::class, 'syncUsers']);
        Route::post('/sync-teams', [App\Http\Controllers\ADOSyncController::class, 'syncTeams']);
        Route::post('/sync-iterations', [App\Http\Controllers\ADOSyncController::class, 'syncIterations']);
        Route::post('/sync-team-iterations', [App\Http\Controllers\ADOSyncController::class, 'syncTeamIterations']);
        Route::post('/sync-work-items', [App\Http\Controllers\ADOSyncController::class, 'syncWorkItems']);
        Route::post('/clear-cache', [App\Http\Controllers\ADOSyncController::class, 'clearCache']);
        Route::get('/test-connection', [App\Http\Controllers\ADOSyncController::class, 'testConnection']);
        Route::get('/projects', [App\Http\Controllers\ADOSyncController::class, 'getProjects']);
        Route::get('/users', [App\Http\Controllers\ADOSyncController::class, 'getUsers']);
        Route::get('/iterations', [App\Http\Controllers\ADOSyncController::class, 'getIterations']);
        Route::get('/team-iterations', [App\Http\Controllers\ADOSyncController::class, 'getTeamIterations']);
        Route::get('/work-items', [App\Http\Controllers\ADOSyncController::class, 'getWorkItems']);
        Route::get('/iterations/db', [App\Http\Controllers\ADOSyncController::class, 'getIterationsFromDB']);
        Route::get('/team-iterations/db', [App\Http\Controllers\ADOSyncController::class, 'getTeamIterationsFromDB']);
        Route::get('/sync-history', [App\Http\Controllers\ADOSyncController::class, 'getSyncHistory']);
        Route::get('/teams', [App\Http\Controllers\ADOSyncController::class, 'getTeams']);
        Route::post('/toggle-team-status', [App\Http\Controllers\ADOSyncController::class, 'toggleTeamStatus']);
        Route::post('/toggle-iteration-status', [App\Http\Controllers\ADOSyncController::class, 'toggleIterationStatus']);
        Route::post('/toggle-team-iteration-status', [App\Http\Controllers\ADOSyncController::class, 'toggleTeamIterationStatus']);
    });

    // Sprint Metrics Routes
    Route::prefix('sprint-metrics')->group(function () {
        Route::get('/projects', [App\Http\Controllers\SprintMetricsController::class, 'getProjects']);
        Route::get('/teams', [App\Http\Controllers\SprintMetricsController::class, 'getTeams']);
        Route::get('/sprints', [App\Http\Controllers\SprintMetricsController::class, 'getSprints']);
        Route::get('/metrics', [App\Http\Controllers\SprintMetricsController::class, 'getSprintMetrics']);
        Route::get('/work-items', [App\Http\Controllers\SprintMetricsController::class, 'getWorkItems']);
        Route::get('/daily-progress', [App\Http\Controllers\SprintMetricsController::class, 'getDailyProgress']);
        Route::get('/debug-project', [App\Http\Controllers\SprintMetricsController::class, 'debugProject']);
    });

      // BambooHR Routes
  Route::prefix('bamboohr')->group(function () {
      Route::get('/status', [App\Http\Controllers\BambooHRController::class, 'status']);
      Route::get('/test-connection', [App\Http\Controllers\BambooHRController::class, 'testConnection']);
      Route::get('/debug-api', [App\Http\Controllers\BambooHRController::class, 'debugApiResponse']);
        Route::post('/sync-all', [App\Http\Controllers\BambooHRController::class, 'syncAll']);
        Route::post('/sync-employees', [App\Http\Controllers\BambooHRController::class, 'syncEmployees']);
        Route::post('/sync-employees-directory', [App\Http\Controllers\BambooHRController::class, 'syncEmployeesDirectory']);
        Route::post('/sync-employees-detailed', [App\Http\Controllers\BambooHRController::class, 'syncEmployeesDetailed']);
        Route::post('/sync-departments', [App\Http\Controllers\BambooHRController::class, 'syncDepartments']);
        Route::post('/sync-job-titles', [App\Http\Controllers\BambooHRController::class, 'syncJobTitles']);
        Route::post('/sync-time-off', [App\Http\Controllers\BambooHRController::class, 'syncTimeOff']);
        Route::post('/clear-cache', [App\Http\Controllers\BambooHRController::class, 'clearCache']);
        Route::get('/employees', [App\Http\Controllers\BambooHRController::class, 'getEmployees']);
        Route::get('/departments', [App\Http\Controllers\BambooHRController::class, 'getDepartments']);
        Route::get('/job-titles', [App\Http\Controllers\BambooHRController::class, 'getJobTitles']);
        Route::get('/time-off', [App\Http\Controllers\BambooHRController::class, 'getTimeOff']);
        Route::get('/sync-progress', [App\Http\Controllers\BambooHRController::class, 'getSyncProgress']);
        Route::get('/sync-progress/{service}/{operation}', [App\Http\Controllers\BambooHRController::class, 'getSyncProgressByService']);
        Route::get('/sync-history', [App\Http\Controllers\BambooHRController::class, 'getSyncHistory']);
        Route::get('/employees/{id}', [App\Http\Controllers\BambooHRController::class, 'getEmployee']);
        Route::get('/departments/{id}', [App\Http\Controllers\BambooHRController::class, 'getDepartment']);
        Route::get('/time-off/{id}', [App\Http\Controllers\BambooHRController::class, 'getTimeOffRequest']);
        Route::post('/sync-selected', [App\Http\Controllers\BambooHRController::class, 'syncSelected']);
        Route::get('/websocket-progress/{service}/{operation}', [App\Http\Controllers\BambooHRController::class, 'getWebSocketProgress']);
        Route::get('/websocket-logs/{service}/{operation}', [App\Http\Controllers\BambooHRController::class, 'getWebSocketLogs']);
        Route::delete('/websocket-channels/{service}/{operation}', [App\Http\Controllers\BambooHRController::class, 'clearWebSocketChannels']);
    });
});
