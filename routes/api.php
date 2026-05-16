<?php

use App\Http\Controllers\Api\V1\MonitorController;
use Illuminate\Support\Facades\Route;

Route::prefix('monitors')->group(function () {
    Route::get('/', [MonitorController::class, 'index']);
    Route::post('/', [MonitorController::class, 'store']);
    Route::get('{id}/history', [MonitorController::class, 'history']);
});
