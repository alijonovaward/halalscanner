<?php

use App\Http\Controllers\Api\IngredientAnalysisController;
use App\Http\Controllers\Api\IngredientImageRecognitionController;
use App\Http\Controllers\Api\ProductVerdictController;
use Illuminate\Support\Facades\Route;

Route::post('/ingredient-image-recognitions', IngredientImageRecognitionController::class)
    ->middleware('throttle:10,1')
    ->name('api.ingredient-image-recognitions.store');

Route::post('/ingredient-analyses', IngredientAnalysisController::class)
    ->middleware('throttle:10,1')
    ->name('api.ingredient-analyses.store');

Route::get('/products/{barcode}/verdict', ProductVerdictController::class)
    ->where('barcode', '[0-9A-Za-z._-]+')
    ->name('api.products.verdict');
