<?php

namespace App\Http\Controllers;

use App\Enums\FaceStatusEnum;
use App\Enums\ImageStatusEnum;
use App\Models\Image;
use Inertia\Inertia;

class ImageController extends Controller
{
    public function index()
    {
        $initialImage = Image::select('id')->whereNull('parent_id')->where('status', ImageStatusEnum::Process->value)->orderBy('id')->first();

        return Inertia::render('Faces/FaceTable', [
            'initialImageId' => $initialImage?->id ?? null,
            'FaceStatus' => FaceStatusEnum::toObject(),
        ]);
    }
}
