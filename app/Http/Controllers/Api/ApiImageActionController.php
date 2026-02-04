<?php

namespace App\Http\Controllers\Api;

use App\Contracts\ImageServiceInterface;
use App\Http\Controllers\Controller;
use App\Models\Image;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class ApiImageActionController extends Controller
{
    public function __construct(
        protected ImageServiceInterface $imageService
    ) {}

    /**
     * Get nearby images with status = Image::STATUS_PROCESS in database
     *
     * @route GET /api/image/{id}/nearby
     */
    public function nearby(int $id): JsonResponse
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

    /**
     * Show debug image
     */
    public function showDebugImage(int $id): BinaryFileResponse|JsonResponse
    {
        $image = Image::findOrFail($id);
        $debugPath = $image->path . '/debug/' . $image->debug_filename;

        if (!Storage::disk($image->disk)->exists($debugPath)) {
            return response()->json(['error' => 'File not found'], Response::HTTP_NOT_FOUND);
        }

        return response()->file(Storage::disk($image->disk)->path($debugPath));
    }

    /**
     * Show original image
     */
    public function show(int $id): BinaryFileResponse|JsonResponse
    {
        $image = Image::findOrFail($id);
        $path = $image->path . '/' . $image->filename;

        if (!Storage::disk($image->disk)->exists($path)) {
            return response()->json(['error' => 'File not found'], Response::HTTP_NOT_FOUND);
        }

        return response()->file(Storage::disk($image->disk)->path($path));
    }

    /**
     * Show thumbnail
     */
    public function showThumbnail(int $id): BinaryFileResponse|JsonResponse
    {
        $image = Image::findOrFail($id);
        $thumbnailPath = implode('/', array_filter([
            $image->path,
            $image->thumbnail_path,
            $image->thumbnail_filename
        ]));

        if (!Storage::disk($image->disk)->exists($thumbnailPath)) {
            return response()->json(['error' => 'File not found'], Response::HTTP_NOT_FOUND);
        }

        return response()->file(Storage::disk($image->disk)->path($thumbnailPath));
    }

    /**
     * Update image status
     */
    public function status(int $id, Request $request): JsonResponse
    {
        $image = Image::findOrFail($id);
        $image->status = $request->input('status');
        $image->save();

        return response()->json(['status' => Image::STATUS_OK]);
    }

    /**
     * Mark image as complete
     */
    public function complete(int $id): JsonResponse
    {
        $image = Image::find($id);

        if ($image) {
            $image->status = Image::STATUS_OK;
            $image->save();
        }

        return response()->json(['status' => Image::STATUS_OK]);
    }

    /**
     * Mark image as not a photo
     */
    public function remove(int $id): JsonResponse
    {
        $image = Image::findOrFail($id);
        $image->status = Image::STATUS_NOT_PHOTO;
        $image->save();

        return response()->json(['status' => Image::STATUS_OK]);
    }

    /**
     * New upload from FTP - image process needed
     */
    public function newUpload(Request $request): JsonResponse
    {
        $filename = $request->input('filename');

        if (!$filename) {
            return response()->json([
                'status' => 'error',
                'message' => 'Filename is required'
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $result = $this->imageService->processNewUpload(
                disk: config('image.paths.disk'),
                path: config('image.paths.images'),
                filename: $filename,
                skipIfExists: false  // Controller всегда обновляет
            );

            if (!$result['success']) {
                return response()->json([
                    'status' => 'error',
                    'message' => $result['message']
                ], Response::HTTP_INTERNAL_SERVER_ERROR);
            }

            return response()->json([
                'status' => 'success',
                'message' => $result['message'],
                'image_id' => $result['image']->id
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to process new upload', [
                'filename' => $filename,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to process upload: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
