<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\IndicatorController;
use App\Http\Controllers\Admin\IndicatorValueController;

Route::get('/ping', function () {
    return response()->json(['message' => 'API Aktif']);
});

Route::apiResource('categories', CategoryController::class);
Route::apiResource('indicators', IndicatorController::class);
Route::apiResource('indicator-values', IndicatorValueController::class);
