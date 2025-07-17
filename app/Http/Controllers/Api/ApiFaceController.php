<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\FaceDeleteRequest;
use App\Http\Requests\FaceSaveRequest;
use App\Models\Face;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ApiFaceController extends Controller
{
    public function list(Request $request)
    {
        $imageId = $request->input('image_id');

        $faces = Face::where('image_id', $imageId)
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
