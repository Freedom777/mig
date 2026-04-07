<?php

namespace App\Http\Controllers\Api;

use App\Contracts\ImagePathServiceInterface;
use App\Contracts\ImageServiceInterface;
use App\Enums\ImageStatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\CacheImageTrait;
use App\Models\Image;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rules\Enum;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class ApiImageActionController extends Controller
{
    use CacheImageTrait;

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
                'path' => (bool) $this->pathService->getDebugImagePath($prev),
            ] : null,
            'next' => $next ? [
                'id' => $next->id,
                'path' => (bool) $this->pathService->getDebugImagePath($next),
            ] : null,
        ]);
    }

    /**
     * Show debug image с кешированием
     */
    public function debug(Image $image): BinaryFileResponse|JsonResponse|Response
    {
        $debugPath = $this->pathService->getDebugImagePath($image);

        if (!$debugPath || !file_exists($debugPath)) {
            return response()->json(['error' => 'Debug image not found'], Response::HTTP_NOT_FOUND);
        }

        return $this->cachedFileResponse($debugPath, 'image/jpeg');
    }

    /**
     * Show original image с кешированием
     */
    public function show(Image $image): BinaryFileResponse|JsonResponse|Response
    {
        $fullPath = $this->pathService->getImagePathByObj($image);

        if (!file_exists($fullPath)) {
            return response()->json(['error' => 'Image not found'], Response::HTTP_NOT_FOUND);
        }

        $mimeType = $this->getMimeType($fullPath);

        return $this->cachedFileResponse($fullPath, $mimeType);
    }

    /**
     * Show thumbnail с кешированием
     */
    public function showThumbnail(Image $image): BinaryFileResponse|JsonResponse|Response
    {
        $thumbnailPath = $this->pathService->getExistingThumbnailPath($image);

        if (!$thumbnailPath || !file_exists($thumbnailPath)) {
            return response()->json(['error' => 'Thumbnail not found'], Response::HTTP_NOT_FOUND);
        }

        return $this->cachedFileResponse($thumbnailPath, 'image/jpeg');
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
