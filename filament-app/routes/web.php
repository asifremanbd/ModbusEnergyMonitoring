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

// Polling system health check endpoints
Route::prefix('api/polling')->group(function () {
    Route::get('/health', [App\Http\Controllers\PollingHealthController::class, 'health']);
    Route::get('/status', [App\Http\Controllers\PollingHealthController::class, 'status']);
    Route::post('/audit', [App\Http\Controllers\PollingHealthController::class, 'audit']);
});
