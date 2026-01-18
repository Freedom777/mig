<?php

namespace App\Jobs;

use App\Models\Face;
use App\Models\Image;
use Illuminate\Support\Facades\Cache;
use App\Services\ImagePathService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class FaceProcessJob extends BaseProcessJob
{
    private const FACE_RECOGNITION_THRESHOLD = 0.6;
    private const FACE_API_TIMEOUT = 300; // 5 минут для CPU

    public function handle()
    {
        // Lock для предотвращения параллельной обработки
        $lockKey = 'face-processing:' . $this->taskData['image_id'];
        $lock = Cache::lock($lockKey, 360); // 6 минут

        try {
            $lock->block(360, function () {
                $this->processFaces();
            });
        } catch (\Illuminate\Contracts\Cache\LockTimeoutException $e) {
            Log::warning('Could not acquire lock for face processing', [
                'image_id' => $this->taskData['image_id']
            ]);
            $this->release(60);
        } catch (\Exception $e) {
            Log::error('Face processing failed', [
                'image_id' => $this->taskData['image_id'],
                'error' => $e->getMessage()
            ]);
            throw $e;
        } finally {
            $this->complete();
        }
    }

    private function processFaces()
    {
        $image = Image::findOrFail($this->taskData['image_id']);
        $imagePath = ImagePathService::getImagePath($image);

        // Увеличенный timeout для CPU
        $response = Http::timeout(self::FACE_API_TIMEOUT)
            ->attach('image', file_get_contents($imagePath), $image->filename)
            ->post(config('app.face_api_url') . '/encode', [
                'original_disk' => $image->disk,
                'original_path' => $imagePath,
                'image_debug_subdir' => ImagePathService::getImageDebugSubdir()
            ]);

        if (!$response->successful()) {
            $image->last_error = 'HTTP ' . $response->status() . ': ' . Str::limit($response->body(), 200);
            $image->save();
            throw new \Exception('Face API failed: ' . $response->body());
        }

        $responseData = $response->json();

        if (isset($responseData['error'])) {
            $image->last_error = $responseData['error'];
            $image->save();
            return;
        }

        $image->last_error = null;
        $newEncodings = $responseData['encodings'] ?? [];

        // Оптимизация: сравниваем только с "мастер" записями (parent_id IS NULL, status='ok')
        $faces = Face::query()
            ->whereNotNull('encoding')
            ->whereNull('parent_id')  // только корневые лица
            ->where('status', 'ok')    // только подтвержденные
            ->get(['id', 'encoding']);

        foreach ($newEncodings as $idx => $newEncoding) {
            $newFace = new Face();
            $newFace->encoding = $newEncoding;
            $newFace->image_id = $image->id;
            $newFace->face_index = $idx;

            if ($faces->isNotEmpty()) {
                $parentId = $this->findMatchingFace($newEncoding, $faces);
                if ($parentId) {
                    $newFace->parent_id = $parentId;
                }
            }

            $newFace->save();
        }

        $debugPath = $responseData['debug_image_path'] ?? null;
        $image->debug_filename = $debugPath ? basename($debugPath) : null;
        $image->faces_checked = 1;
        $image->save();

        Log::info('Face processing completed', [
            'image_id' => $image->id,
            'faces_found' => count($newEncodings)
        ]);
    }

    private function findMatchingFace($newEncoding, $faces)
    {
        $knownEncodings = $faces->pluck('encoding')->toArray();

        $compareResponse = Http::timeout(60)
            ->post(config('app.face_api_url') . '/compare', [
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

        if ($minValue < self::FACE_RECOGNITION_THRESHOLD) {
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
