<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\FaceDeleteRequest;
use App\Http\Requests\FaceSaveRequest;
use App\Models\Face;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ApiFaceController extends Controller
{
    public function list(Request $request)
    {
        $imageId = $request->input('image_id');

        $faces = Face::where('image_id', $imageId)
            ->orderBy('face_index')
            ->get(['id', 'face_index', 'name', 'status']);

        return response()->json($faces);
    }

    public function save(FaceSaveRequest $request)
    {
        $validated = $request->validated();
        $face = Face::updateOrCreate(
            [
                'image_id' => $request->image_id,
                'face_index' => $request->face_index,
                // 'status' => Face::STATUS_PROCESS
            ],
            [
                'name' => $request->status == Face::STATUS_OK ? $request->name : null,
                'status' => $request->status,
                'updated_at' => now(),
            ]
        );

        // Move to event
        if ($face->parent_id) {
            $face_equal_id = $face->parent_id;
        } else {
            $face_equal_id = $face->id;
        }
        Face::where('parent_id', $face_equal_id)->where('status', Face::STATUS_PROCESS)
            ->update(['name' => $face->name, 'updated_at' => now()]);

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
