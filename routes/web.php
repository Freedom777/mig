<?php

use App\Http\Controllers\ImageController;
use App\Http\Controllers\PhotoController;
use App\Http\Controllers\WelcomeController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');
*/

Route::get('/', [WelcomeController::class, 'index'])->name('home');

Route::get('dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::get('/images', [ImageController::class, 'index'])->middleware(['auth', 'verified'])->name('images.index');
Route::get('/photos', [PhotoController::class, 'index'])->middleware(['auth', 'verified'])->name('photos.index');


/*Route::get('/faces', function () {
    return Inertia::render('Faces/FaceTable');

})->middleware(['auth', 'verified'])->name('faces.index');*/

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
