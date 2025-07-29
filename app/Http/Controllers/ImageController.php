<?php

namespace App\Http\Controllers;

use App\Models\Image;
use Inertia\Inertia;

class ImageController extends Controller
{
    public function index()
    {
        $initialImage = Image::select('id')->whereNull('parent_id')->where('status', Image::STATUS_PROCESS)->orderBy('id')->first();

        return Inertia::render('Faces/FaceTable', [
            'initialImageId' => $initialImage?->id ?? null,
        ]);
    }
}
