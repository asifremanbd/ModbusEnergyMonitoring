<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return redirect('/admin');
});

// User Management Routes (bypasses Filament)
Route::middleware(['web'])->group(function () {
    Route::get('/user-management', [App\Http\Controllers\UserManagementController::class, 'index']);
    Route::post('/user-management', [App\Http\Controllers\UserManagementController::class, 'store']);
    Route::delete('/user-management/{user}', [App\Http\Controllers\UserManagementController::class, 'destroy']);
});

// Polling system health check endpoints
Route::prefix('api/polling')->group(function () {
    Route::get('/health', [App\Http\Controllers\PollingHealthController::class, 'health']);
    Route::get('/status', [App\Http\Controllers\PollingHealthController::class, 'status']);
    Route::post('/audit', [App\Http\Controllers\PollingHealthController::class, 'audit']);
});
