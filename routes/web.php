<?php

use App\Http\Controllers\FaceController;
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

Route::get('/faces', [FaceController::class, 'index'])->name('faces.index');

/*Route::get('/faces', function () {
    return Inertia::render('Faces/FaceTable');

})->middleware(['auth', 'verified'])->name('faces.index');*/

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
