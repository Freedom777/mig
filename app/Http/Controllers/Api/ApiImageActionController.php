<?php

namespace App\Http\Controllers\Api;

use App\Contracts\ImagePathServiceInterface;
use App\Contracts\ImageServiceInterface;
use App\Enums\ImageStatusEnum;
use App\Http\Controllers\Controller;
use App\Models\Image;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Enum;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class ApiImageActionController extends Controller
{
    public function __construct(
        protected ImageServiceInterface $imageService,
        protected ImagePathServiceInterface $pathService
    ) {}

    /**
     * Get nearby images with status = Image::STATUS_PROCESS in database
     *
     * @route GET /api/image/{id}/nearby
     */
    public function nearby(Image $image): JsonResponse
    {
        $prev = Image::previous($image->id, ImageStatusEnum::Process->value);
        $next = Image::next($image->id, ImageStatusEnum::Process->value);

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
    public function debug(Image $image): BinaryFileResponse|JsonResponse
    {
        $debugPath = $image->path . '/debug/' . $image->debug_filename;

        if (!Storage::disk($image->disk)->exists($debugPath)) {
            return response()->json(['error' => 'File not found'], Response::HTTP_NOT_FOUND);
        }

        return response()->file(Storage::disk($image->disk)->path($debugPath));
    }

    /**
     * Show original image
     */
    public function show(Image $image): BinaryFileResponse|JsonResponse
    {
        $path = $image->path . '/' . $image->filename;

        if (!Storage::disk($image->disk)->exists($path)) {
            return response()->json(['error' => 'File not found'], Response::HTTP_NOT_FOUND);
        }

        return response()->file(Storage::disk($image->disk)->path($path));
    }

    /**
     * Show thumbnail
     */
    public function showThumbnail(Image $image): BinaryFileResponse|JsonResponse
    {
        $thumbnailPath = $this->pathService->getExistingThumbnailPath($image);

        if (!$thumbnailPath) {
            return response()->json(['error' => 'File not found'], Response::HTTP_NOT_FOUND);
        }

        return response()->file($thumbnailPath);
    }

    /**
     * Update image status
     */
    public function status(Image $image, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', new Enum(ImageStatusEnum::class)],
        ]);
        $image->update(['status' => $validated['status']]);

        return response()->json(['status' => $image->status]);
    }

    /**
     * New upload from FTP - image process needed
     */
    public function newUpload(Request $request): JsonResponse
    {
        // dd(config('image.processing.debug'));

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
