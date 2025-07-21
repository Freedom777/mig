<?php

use App\Http\Controllers\Api\ApiFaceController;
use App\Http\Controllers\Api\ApiFaceProcessController;
use App\Http\Controllers\Api\ApiGeolocationProcessController;
use App\Http\Controllers\Api\ApiImageController;
use App\Http\Controllers\Api\ApiMetadataProcessController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ApiImageProcessController;
use App\Http\Controllers\Api\ApiThumbnailProcessController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Группа для API с префиксом и middleware (например, для авторизации)
// Route::middleware(['api', 'auth:sanctum'])->group(function () {
    // Обработка изображений
    Route::post('/image/process', [ApiImageProcessController::class, 'process']);
    Route::post('/thumbnail/process', [ApiThumbnailProcessController::class, 'process']);
    Route::post('/metadata/process', [ApiMetadataProcessController::class, 'process']);
    Route::post('/geolocation/process', [ApiGeolocationProcessController::class, 'process']);
    Route::post('/face/process', [ApiFaceProcessController::class, 'process']);
// });

Route::controller(ApiFaceController::class)->prefix('face')->group(function () {
    Route::get('list', 'list');
    Route::post('save', 'save');
    Route::delete('remove', 'remove');
});

Route::controller(ApiImageController::class)->prefix('image')->group(function () {
    Route::get('nearby', 'nearby');
    Route::get('{id}.jpg', 'show');
    Route::post('complete', 'complete');
    Route::post('remove', 'remove');
});
