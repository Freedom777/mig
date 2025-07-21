<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Image;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Storage;

class ApiImageController extends Controller
{
    public function nearby(Request $request)
    {
        $id = (int) $request->input('id');

        $prev = Image::previous($id, Image::STATUS_PROCESS);
        $next = Image::next($id, Image::STATUS_PROCESS);
        $hasPrev = false;
        $hasNext = false;

        if ($prev) {
            $hasPrev = Storage::disk($prev->disk)->exists($prev->path . '/debug/' . $prev->debug_filename);
        }
        if ($next) {
            $hasNext = Storage::disk($next->disk)->exists($next->path . '/debug/' . $next->debug_filename);
        }

        return response()->json([
            'prev' => $prev ? [
                'id' => $prev->id,
                'path' => Storage::disk($prev->disk)->exists($prev->path . '/debug/' . $prev->debug_filename),
            ] : null,
            'next' => $next ? [
                'id' => $next->id,
                'path' => Storage::disk($next->disk)->exists($next->path . '/debug/' . $next->debug_filename),
            ] : null,
            /*
            'hasPrev' => $hasPrev,
            'hasNext' => $hasNext,
            */
        ]);
    }

    public function show($id)
    {
        $image = Image::findOrFail($id);
        $debug_path = $image->path . '/debug/' . $image->debug_filename;

        if (!Storage::disk($image->disk)->exists($debug_path)) {
            return response()->json(['error' => 'File not found'], Response::HTTP_NOT_FOUND);
        }
        return response()->file(Storage::disk($image->disk)->path($debug_path));
    }

    /*public function getData(Request $request)
    {
        $image = Image::find((int) $request->input('image_id'));
    }*/

    public function complete(Request $request){
        $image = Image::find((int) $request->input('image_id'));
        if ($image) {
            $image->status = Image::STATUS_OK;
            $image->save();
        }

        return response()->json([
            'status' => Image::STATUS_OK
        ]);
    }

    public function remove(Request $request){
        $image = Image::find((int) $request->input('image_id'));
        if ($image) {
            $image->status = Image::STATUS_NOT_PHOTO;
            $image->save();
        }

        return response()->json([
            'status' => Image::STATUS_OK
        ]);
    }

}
