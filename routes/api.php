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
    });
});
