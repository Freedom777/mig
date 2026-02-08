<?php

namespace App\Jobs;

use App\Contracts\ImagePathServiceInterface;
use App\Models\Face;
use App\Models\Image;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class FaceProcessJob extends BaseProcessJob
{
    private const FACE_API_TIMEOUT = 300; // 5 минут для CPU

    /**
     * Execute the job.
     */
    public function handle(ImagePathServiceInterface $pathService): void
    {
        $imageId = $this->taskData['image_id'];
        $lockKey = 'face-processing:' . $imageId;
        $lock = Cache::lock($lockKey, 360);

        try {
            $lock->block(360, function () use ($pathService) {
                $this->processFaces($pathService);
            });
        } catch (\Illuminate\Contracts\Cache\LockTimeoutException $e) {
            Log::warning('Could not acquire lock for face processing', [
                'image_id' => $imageId
            ]);
            $this->release(60);
            return;
        } catch (\Exception $e) {
            Log::error('Face processing failed', [
                'image_id' => $imageId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        } finally {
            $this->complete();
        }
    }

    private function processFaces(ImagePathServiceInterface $pathService): void
    {
        $image = Image::findOrFail($this->taskData['image_id']);
        $imagePath = $pathService->getImagePathByObj($image);

        Log::info('original_path: ' . $imagePath);
        Log::info('image_debug_subdir: ' . $pathService->getImageDebugSubdir());
        $response = Http::timeout(self::FACE_API_TIMEOUT)
            ->attach('image', file_get_contents($imagePath), $image->filename)
            ->post(config('image.face_api.url') . '/encode', [
                'original_path' => $imagePath,
                'image_debug_subdir' => $pathService->getImageDebugSubdir()
            ]);

        if (!$response->successful()) {
            $image->update([
                'last_error' => 'HTTP ' . $response->status() . ': ' . Str::limit($response->body(), 200)
            ]);
            throw new \Exception('Face API failed: ' . $response->body());
        }

        $responseData = $response->json();

        if (isset($responseData['error'])) {
            $image->update(['last_error' => $responseData['error']]);
            return;
        }

        $image->update(['last_error' => null]);
        $newEncodings = $responseData['encodings'] ?? [];
        $qualities = $responseData['qualities'] ?? [];

        // Оптимизация: сравниваем только с "мастер" записями
        $faces = Face::query()
            ->whereNotNull('encoding')
            ->whereNull('parent_id')
            ->where('status', Face::STATUS_OK)
            ->get(['id', 'encoding']);

        $threshold = config('image.face_api.threshold', 0.6);

        foreach ($newEncodings as $idx => $newEncoding) {
            $quality = $qualities[$idx] ?? null;

            $newFace = new Face();
            $newFace->encoding = $newEncoding;
            $newFace->image_id = $image->id;
            $newFace->face_index = $idx;
            $newFace->quality_score = $quality['total'] ?? null;
            $newFace->quality_details = $quality['details'] ?? null;

            if ($faces->isNotEmpty()) {
                $parentId = $this->findMatchingFace($newEncoding, $faces, $threshold);
                if ($parentId) {
                    $newFace->parent_id = $parentId;
                }
            }

            $newFace->save();
        }

        $debugPath = $responseData['debug_image_path'] ?? null;
        $image->update([
            'debug_filename' => $debugPath ? basename($debugPath) : null,
            'faces_checked' => 1,
        ]);

        Log::info('Face processing completed', [
            'image_id' => $image->id,
            'faces_found' => count($newEncodings)
        ]);
    }

    private function findMatchingFace(mixed $newEncoding, $faces, float $threshold): ?int
    {
        $knownEncodings = $faces->pluck('encoding')->toArray();

        $compareResponse = Http::timeout(60)
            ->post(config('image.face_api.url') . '/compare', [
                'encoding' => $newEncoding,
                'candidates' => $knownEncodings,
            ]);

        if (!$compareResponse->successful()) {
            Log::warning('Face compare failed', ['error' => $compareResponse->body()]);
            return null;
        }

        $distances = $compareResponse->json()['distances'] ?? [];

        if (empty($distances)) {
            return null;
        }

        $minValue = min($distances);

        if ($minValue < $threshold) {
            $minIndex = array_search($minValue, $distances);
            $matchedFace = $faces[$minIndex];

            Log::info('Face match found', [
                'matched_id' => $matchedFace->id,
                'distance' => $minValue
            ]);

            return $matchedFace->id;
        }

        return null;
    }
}
