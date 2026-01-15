<?php

use App\Http\Controllers\Api\ApiFaceController;
use App\Http\Controllers\Api\ApiFilterController;
use App\Http\Controllers\Api\ApiImageActionController;
use App\Http\Controllers\Api\ApiPhotoController;
use App\Http\Controllers\Api\ApiWhatsAppController;
use App\Http\Controllers\Api\PushQueue\FaceQueuePushApiController;
use App\Http\Controllers\Api\PushQueue\GeolocationQueuePushApiController;
use App\Http\Controllers\Api\PushQueue\ImageQueuePushApiController;
use App\Http\Controllers\Api\PushQueue\MetadataQueuePushApiController;
use App\Http\Controllers\Api\PushQueue\ThumbnailQueuePushApiController;
use App\Models\Image;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');


Route::get('/photos', [ApiPhotoController::class, 'index']);
Route::post('/photos', [ApiPhotoController::class, 'index']);
Route::get('/filters', [ApiFilterController::class, 'index']);

Route::get('/thumbnail/{id}.jpg', [ApiImageActionController::class, 'showThumbnail']);

// Группа для API с префиксом и middleware (например, для авторизации)
// Route::middleware(['api', 'auth:sanctum'])->group(function () {
    // Queue objects push
    Route::post('/image/push', [ImageQueuePushApiController::class, 'process'])->name('image.push');
    Route::post('/thumbnail/push', [ThumbnailQueuePushApiController::class, 'process'])->name('thumbnail.push');;
    Route::post('/metadata/push', [MetadataQueuePushApiController::class, 'process'])->name('metadata.push');
    Route::post('/geolocation/push', [GeolocationQueuePushApiController::class, 'process'])->name('geolocation.push');
    Route::post('/face/push', [FaceQueuePushApiController::class, 'process'])->name('face.push');
// });

Route::controller(ApiFaceController::class)->prefix('face')->group(function () {
    Route::get('list', 'list');
    Route::post('save', 'save');
    Route::delete('remove', 'remove');
});

Route::controller(ApiImageActionController::class)->prefix('image')->group(function () {
    Route::get('{id}/nearby', 'nearby');
    Route::get('debug/{id}.jpg', 'showDebugImage');
    Route::get('{id}.jpg', 'show');
    Route::get('{id}/remove', 'remove');

    Route::patch('{id}/status', 'status');

    Route::post('new-upload', 'newUpload');
});

Route::get('/photos/year-counts', function () {
    $counts = Image::selectRaw('YEAR(updated_at_file) as year, count(*) as count')
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

Route::get('/whatsapp/msg', [ApiWhatsAppController::class, 'msg']);
