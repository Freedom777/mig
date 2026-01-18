<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\BaseProcessJob;
use App\Jobs\FaceProcessJob;
use App\Jobs\MetadataProcessJob;
use App\Jobs\ThumbnailProcessJob;
use App\Models\Image;
use App\Services\ApiClient;
use App\Services\ImagePathService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class ApiImageActionController extends Controller
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

    public function showDebugImage($id) : BinaryFileResponse | JsonResponse
    {
        $image = Image::findOrFail($id);
        $debug_path = $image->path . '/debug/' . $image->debug_filename;

        if (!Storage::disk($image->disk)->exists($debug_path)) {
            return response()->json(['error' => 'File not found'], Response::HTTP_NOT_FOUND);
        }
        return response()->file(Storage::disk($image->disk)->path($debug_path));
    }

    public function show($id) : BinaryFileResponse | JsonResponse
    {
        $image = Image::findOrFail($id);
        $path = $image->path . '/' . $image->filename;

        if (!Storage::disk($image->disk)->exists($path)) {
            return response()->json(['error' => 'File not found'], Response::HTTP_NOT_FOUND);
        }
        return response()->file(Storage::disk($image->disk)->path($path));
    }

    public function showThumbnail($id) : BinaryFileResponse | JsonResponse
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


    /**
     * New upload from ftp - image process needed
     */
    public function newUpload(Request $request): JsonResponse
    {
        $filename = $request->input('filename');

        if (!$filename) {
            return response()->json([
                'status' => 'error',
                'message' => 'Filename is required'
            ], 400);
        }

        Log::info('Processing new upload', ['filename' => $filename]);

        try {
            $path = config('image.paths.images');
            $preparedData = Image::prepareData(config('image.paths.disk'), $path, $filename);

            $image = Image::updateInsert($preparedData);

            if (!$image) {
                Log::error('Failed to insert image', ['filename' => $filename]);
                return response()->json([
                    'status' => 'error',
                    'message' => 'Failed to insert image'
                ], 500);
            }

            Log::info('Image inserted successfully', [
                'image_id' => $image->id,
                'filename' => $filename
            ]);

            // Запускаем обработку джобов
            $this->queueImageProcessing($image, $preparedData);

            Log::info('All jobs queued successfully', ['image_id' => $image->id]);

            return response()->json([
                'status' => 'success',
                'message' => 'Image uploaded and processing started',
                'image_id' => $image->id
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
            ], 500);
        }
    }

    /**
     * Ставит в очередь все джобы для обработки изображения
     */
    private function queueImageProcessing(Image $image, array $preparedData): void
    {
        $queueStatuses = [];

        // 1. Queue Thumbnail Processing
        try {
            $thumbWidth = config('images.thumbnails.width', 300);
            $thumbHeight = config('images.thumbnails.height', 200);
            $thumbMethod = config('images.thumbnails.method', 'cover');
            $thumbPath = ImagePathService::getThumbnailSubdir($thumbWidth, $thumbHeight);
            $thumbFilename = ImagePathService::getThumbnailFilename(
                $preparedData['source_filename'],
                $thumbMethod,
                $thumbWidth,
                $thumbHeight
            );

            $response = BaseProcessJob::pushToQueue(
                ThumbnailProcessJob::class,
                config('queue.name.thumbnails'),
                [
                    'image_id' => $image->id,
                    'disk' => $preparedData['source_disk'],
                    'source_path' => $preparedData['source_path'],
                    'source_filename' => $preparedData['source_filename'],
                    'thumbnail_path' => $thumbPath,
                    'thumbnail_filename' => $thumbFilename,
                    'thumbnail_method' => $thumbMethod,
                    'thumbnail_width' => $thumbWidth,
                    'thumbnail_height' => $thumbHeight,
                ]
            );

            $responseData = $response->getData();
            $queueStatuses['thumbnail'] = $responseData->status;

            if ($responseData->status === 'success') {
                Log::info('Thumbnail job queued', ['image_id' => $image->id]);
            } elseif ($responseData->status === 'exists') {
                Log::info('Thumbnail job already in queue', ['image_id' => $image->id]);
            }

        } catch (\Exception $e) {
            Log::error('Failed to queue thumbnail process', [
                'image_id' => $image->id,
                'error' => $e->getMessage()
            ]);
            $queueStatuses['thumbnail'] = 'error';
        }

        // 2. Queue Metadata Processing (Geolocation will chain automatically if GPS exists)
        try {
            $response = BaseProcessJob::pushToQueue(
                MetadataProcessJob::class,
                config('queue.name.metadatas'),
                [
                    'image_id' => $image->id,
                    'source_disk' => $preparedData['source_disk'],
                    'source_path' => $preparedData['source_path'],
                    'source_filename' => $preparedData['source_filename'],
                ]
            );

            $responseData = $response->getData();
            $queueStatuses['metadata'] = $responseData->status;

            if ($responseData->status === 'success') {
                Log::info('Metadata job queued (Geolocation will chain if GPS exists)', [
                    'image_id' => $image->id
                ]);
            } elseif ($responseData->status === 'exists') {
                Log::info('Metadata job already in queue', ['image_id' => $image->id]);
            }

        } catch (\Exception $e) {
            Log::error('Failed to queue metadata process', [
                'image_id' => $image->id,
                'error' => $e->getMessage()
            ]);
            $queueStatuses['metadata'] = 'error';
        }

        // 3. Queue Face Processing
        try {
            $response = BaseProcessJob::pushToQueue(
                FaceProcessJob::class,
                config('queue.name.faces'),
                [
                    'image_id' => $image->id,
                    /*'source_disk' => $preparedData['source_disk'],
                    'source_path' => $preparedData['source_path'],
                    'source_filename' => $preparedData['source_filename'],*/
                ]
            );

            $responseData = $response->getData();
            $queueStatuses['face'] = $responseData->status;

            if ($responseData->status === 'success') {
                Log::info('Face job queued', [
                    'image_id' => $image->id
                ]);
            } elseif ($responseData->status === 'exists') {
                Log::info('Face job already in queue', ['image_id' => $image->id]);
            }

        } catch (\Exception $e) {
            Log::error('Failed to queue face process', [
                'image_id' => $image->id,
                'error' => $e->getMessage()
            ]);
            $queueStatuses['face'] = 'error';
        }

        // Логируем общий результат постановки в очередь
        Log::info('Queue summary', [
            'image_id' => $image->id,
            'statuses' => $queueStatuses
        ]);
    }
}


// Queue geolocation processing (note: this requires metadata to be processed first)
/*\Log::info('Queue start ' . 'geolocation process');
try {
    // For geolocation, we need to pass the metadata as a string
    // Since metadata might not be available yet, we'll pass an empty string
    // The job will handle fetching the metadata from the database
    $response = $apiClient->geolocationProcess([
        'image_id' => $image->id,
        'metadata' => '', // Will be fetched by the job
    ]);

    if (!$response->successful()) {
        \Log::error('Geolocation process API error: ' . $response->body());
    }
} catch (\Exception $e) {
    \Log::error('Failed to queue geolocation process: ' . $e->getMessage());
}*/

/*
    \Log::info('Image inserted, queue started');
    \Log::info('Queue start ' . 'images:thumbnails');
    $exitCode = Artisan::call('images:thumbnails', [
        '--width' => 300, '--height' => 200
    ]);
    \Log::info('Queue start ' . 'images:metadatas');
    $exitCode = Artisan::call('images:metadatas');
    \Log::info('Queue start ' . 'images:geolocations');
    $exitCode = Artisan::call('images:geolocations');
    \Log::info('Queue end, status OK');
*/
