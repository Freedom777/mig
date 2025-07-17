<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\FaceDeleteRequest;
use App\Http\Requests\FaceSaveRequest;
use App\Models\Face;
use App\Models\Image;
use Illuminate\Http\Request;

class ApiFaceController extends Controller
{
    public function list(Request $request)
    {
        $imageId = $request->input('image_id');

        $faces = Face::where('image_id', $imageId)
            ->where('status', Image::STATUS_PROCESS)
            ->orderBy('face_index')
            ->get(['id', 'image_id', 'face_index', 'name']);

        return response()->json($faces);
    }

    public function save(FaceSaveRequest $request)
    {
        $validated = $request->validated();
        $updated = Face::updateOrCreate(
            [
                'image_id' => $request->image_id,
                'idx' => $request->face_index,
            ],
            [
                'name' => $request->name,
                'updated_at' => now(),
            ]
        );

        return response()->json(['success' => true]);
    }

    public function remove(FaceDeleteRequest $request)
    {
        $validated = $request->validated();

        Face::where('image_id', $request->image_id)
            ->where('face_index', $request->face_index)
            ->delete();

        return response()->json(['success' => true]);
    }
}
