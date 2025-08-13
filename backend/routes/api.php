<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\IndicatorController;
use App\Http\Controllers\Admin\IndicatorValueController;
use App\Http\Controllers\InsightController;

Route::get('/ping', function () {
    return response()->json(['message' => 'API Aktif']);
});

Route::apiResource('categories', CategoryController::class);
Route::apiResource('indicators', IndicatorController::class);
Route::apiResource('indicator-values', IndicatorValueController::class);
Route::post('/insight/{bps_var_id}', [InsightController::class, 'getInsight']);
