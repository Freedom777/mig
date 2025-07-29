<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Image;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Storage;

class ApiImageController extends Controller
{
    /** @noinspection PhpUnused */
    /**
     * Get nearby images with status = Image::STATUS_PROCESS in database
     * @used-by \App\Providers\RouteServiceProvider
     * @route GET /api/image/{id}/nearby
     */
    public function nearby($id) : JsonResponse
    {
        $prev = Image::previous($id, Image::STATUS_PROCESS);
        $next = Image::next($id, Image::STATUS_PROCESS);

        return response()->json([
            'prev' => $prev ? [
                'id' => $prev->id,
                'path' => Storage::disk($prev->disk)->exists($prev->path . '/debug/' . $prev->debug_filename),
            ] : null,
            'next' => $next ? [
                'id' => $next->id,
                'path' => Storage::disk($next->disk)->exists($next->path . '/debug/' . $next->debug_filename),
            ] : null,
        ]);
    }

    public function show($id) : BinaryFileResponse | JsonResponse
    {
        $image = Image::findOrFail($id);
        $debug_path = $image->path . '/debug/' . $image->debug_filename;

        if (!Storage::disk($image->disk)->exists($debug_path)) {
            return response()->json(['error' => 'File not found'], Response::HTTP_NOT_FOUND);
        }
        return response()->file(Storage::disk($image->disk)->path($debug_path));
    }

    public function status($id, Request $request) : JsonResponse {
        $image = Image::findOrFail($id);

        $status = $request->input('status');
        $image->status = $status;
        $image->save();

        return response()->json([
            'status' => Image::STATUS_OK
        ]);
    }

    /*public function getData(Request $request)
    {
        $image = Image::find((int) $request->input('image_id'));
    }*/

    public function complete($id) : JsonResponse {
        $image = Image::find($id);
        if ($image) {
            // Maybe move to event, or leave as it is in frontend, when face is in process and image set complete
            /*Face::where('image_id', $image->id)->where('status', Face::STATUS_PROCESS)
                ->update(['status' => Face::STATUS_OK, 'updated_at' => now()]);*/


            $image->status = Image::STATUS_OK;
            $image->save();
        }

        return response()->json([
            'status' => Image::STATUS_OK
        ]);
    }

    public function remove($id) : JsonResponse {
        $image = Image::findOrFail($id);
        if ($image) {
            $image->status = Image::STATUS_NOT_PHOTO;
            $image->save();
        }

        return response()->json([
            'status' => Image::STATUS_OK
        ]);
    }

}
