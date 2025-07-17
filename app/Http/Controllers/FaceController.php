<?php

namespace App\Http\Controllers;

use App\Models\Image;
use Inertia\Inertia;

class FaceController extends Controller
{
    public function index()
    {
        $initialImage = Image::orderBy('id')->first();

        return Inertia::render('Faces/FaceTable', [
            'initialImageId' => $initialImage?->id ?? null,
        ]);
    }
}
