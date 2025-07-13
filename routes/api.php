<?php

use App\Http\Controllers\Api\FaceProcessController;
use App\Http\Controllers\Api\GeolocationProcessController;
use App\Http\Controllers\Api\MetadataProcessController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ImageProcessController;
use App\Http\Controllers\Api\ThumbnailProcessController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Группа для API с префиксом и middleware (например, для авторизации)
// Route::middleware(['api', 'auth:sanctum'])->group(function () {
    // Обработка изображений
    Route::post('/image/process', [ImageProcessController::class, 'process']);
    Route::post('/thumbnail/process', [ThumbnailProcessController::class, 'process']);
    Route::post('/metadata/process', [MetadataProcessController::class, 'process']);
    Route::post('/geolocation/process', [GeolocationProcessController::class, 'process']);
    Route::post('/face/process', [FaceProcessController::class, 'process']);

    // Можно добавить другие методы:
    // Route::get('/images/status', [ImageProcessingController::class, 'status']);
// });
