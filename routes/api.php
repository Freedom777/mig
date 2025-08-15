<?php

use App\Http\Controllers\Api\ApiFaceController;
use App\Http\Controllers\Api\ApiFaceProcessController;
use App\Http\Controllers\Api\ApiFilterController;
use App\Http\Controllers\Api\ApiGeolocationProcessController;
use App\Http\Controllers\Api\ApiImageController;
use App\Http\Controllers\Api\ApiMetadataProcessController;
use App\Http\Controllers\Api\ApiPhotoController;
use App\Models\Image;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ApiImageProcessController;
use App\Http\Controllers\Api\ApiThumbnailProcessController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');


Route::get('/photos', [ApiPhotoController::class, 'index']);
Route::post('/photos', [ApiPhotoController::class, 'index']);
Route::get('/filters', [ApiFilterController::class, 'index']);

Route::get('/thumbnail/{id}.jpg', [ApiImageController::class, 'showThumbnail']);

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
    Route::get('{id}/nearby', 'nearby');
    Route::get('debug/{id}.jpg', 'showDebugImage');
    Route::get('{id}.jpg', 'show');
    Route::get('{id}/remove', 'remove');

    Route::patch('{id}/status', 'status');
});

Route::get('/photos/year-counts', function () {
    $counts = Image::selectRaw("YEAR(updated_at_file) as year, count(*) as count")
        ->groupBy(DB::raw('YEAR(updated_at_file)'))
        ->orderBy('year')
        ->pluck('count', 'year')
        ->toArray();

    return response()->json(array_map(fn($y) => ['year' => (int)$y, 'count' => $counts[$y]], array_keys($counts)));
});

Route::get('/photos/date-available', function () {
    return Image::selectRaw("DATE_FORMAT(updated_at_file, '%Y-%m') as ym, COUNT(*) as count")
        ->groupBy('ym')
        ->orderBy('ym')
        ->get()
        ->map(fn($row) => ['date' => $row->ym, 'count' => $row->count]);
});
