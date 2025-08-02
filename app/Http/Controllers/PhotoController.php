<?php


namespace App\Http\Controllers;

use Inertia\Inertia;

class PhotoController extends Controller
{
    public function index()
    {
        return Inertia::render('PhotoGallery/PhotoGallery', []);
    }
}
