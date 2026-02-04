<?php

use App\Http\Controllers\Api\ApiFaceController;
use App\Http\Controllers\Api\ApiFilterController;
use App\Http\Controllers\Api\ApiImageActionController;
use App\Http\Controllers\Api\ApiPhotoController;
use App\Http\Controllers\Api\ApiWhatsAppController;
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
    Route::get('upload', 'newUpload');
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
