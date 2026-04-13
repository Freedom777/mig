<?php

namespace App\Jobs;

use App\Contracts\ImagePathServiceInterface;
use App\Enums\FaceStatusEnum;
use App\Models\Face;
use App\Models\Image;
use App\Models\Person;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Imagick\Driver;

class FaceProcessJob extends BaseProcessJob
{
    private const FACE_API_TIMEOUT = 300; // 5 минут для CPU
    private const EMBEDDING_SIZE = 128;

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

        // Проверка существования файла
        if (!file_exists($imagePath)) {
            throw new \Exception("Image file not found: {$imagePath}");
        }

        Log::info('Processing faces for image', [
            'image_id' => $image->id,
            'path' => $imagePath
        ]);

        try {
            $response = Http::connectTimeout(10)
                ->timeout(self::FACE_API_TIMEOUT)
                ->attach('image', fopen($imagePath, 'r'), $image->filename)
                ->post(config('image.face_api.url') . '/encode', [
                    'original_path' => $imagePath,
                    'image_debug_subdir' => $pathService->getImageDebugSubdir()
                ]);
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('Cannot connect to Face API', [
                'image_id' => $image->id,
                'error' => $e->getMessage()
            ]);
            throw new \Exception('Face API connection failed: ' . $e->getMessage());
        }

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

        // Получаем всех Person с centroid (один запрос)
        $persons = Person::whereNotNull('centroid_embedding')
            ->where('embeddings_count', '>', 0)
            ->get(['id', 'name', 'centroid_embedding']);

        $threshold = config('image.face_api.threshold', 0.6);
        $affectedPersonIds = collect();

        foreach ($newEncodings as $idx => $newEncoding) {
            $quality = $qualities[$idx] ?? null;

            $newFace = new Face();
            $newFace->encoding = $newEncoding;
            $newFace->image_id = $image->id;
            $newFace->face_index = $idx;
            $newFace->quality_score = $quality['total'] ?? null;
            $newFace->quality_details = $quality['details'] ?? null;
            $newFace->status = FaceStatusEnum::Process->value;

            // Ищем подходящего Person (сравнение в памяти)
            if ($persons->isNotEmpty()) {
                $matchingPerson = $this->findBestMatchingPerson($newEncoding, $persons, $threshold);

                if ($matchingPerson) {
                    $newFace->person_id = $matchingPerson->id;
                    $newFace->status = FaceStatusEnum::Suggested->value;
                    $affectedPersonIds->push($matchingPerson->id);

                    Log::info('Auto-matched face to person', [
                        'image_id' => $image->id,
                        'face_index' => $idx,
                        'person_id' => $matchingPerson->id,
                        'person_name' => $matchingPerson->name,
                    ]);
                }
            }

            $newFace->save();
        }

        $debugPath = $responseData['debug_image_path'] ?? null;
        $debugFilename = null;

        // НОВОЕ: Конвертировать debug JPG → WebP
        if ($debugPath && file_exists($debugPath)) {
            try {
                $debugFilename = $this->convertDebugToWebP($debugPath, $pathService);
            } catch (\Exception $e) {
                Log::warning('Failed to convert debug image to WebP', [
                    'image_id' => $image->id,
                    'error' => $e->getMessage()
                ]);
                // Оставляем оригинальное имя если конвертация не удалась
                $debugFilename = basename($debugPath);
            }
        }

        $image->update([
            'debug_filename' => $debugFilename,
            'faces_checked' => 1,
        ]);

        // Пересчитываем centroid для затронутых Person (если были Suggested)
        // НЕ пересчитываем - centroid обновится только после подтверждения админом
        // Suggested лица не участвуют в centroid до подтверждения

        Log::info('Face processing completed', [
            'image_id' => $image->id,
            'faces_found' => count($newEncodings),
            'suggested' => $affectedPersonIds->count(),
        ]);
    }

    /**
     * Найти наиболее подходящего Person для encoding
     * Сравнение в памяти (оптимизация)
     *
     * Если количество person будет больше 100 - лучше использовать server.py для сравнения
     */
    private function findBestMatchingPerson(array $encoding, $persons, float $threshold): ?Person
    {
        $bestMatch = null;
        $bestDistance = PHP_FLOAT_MAX;

        foreach ($persons as $person) {
            $centroid = $person->centroid_embedding;
            if (!$centroid || !is_array($centroid)) {
                continue;
            }

            $distance = $this->euclideanDistance($encoding, $centroid);

            if ($distance < $threshold && $distance < $bestDistance) {
                $bestMatch = $person;
                $bestDistance = $distance;
            }
        }

        return $bestMatch;
    }

    /**
     * Евклидово расстояние между двумя векторами
     */
    private function euclideanDistance(array $a, array $b): float
    {
        $sum = 0;
        for ($i = 0; $i < self::EMBEDDING_SIZE; $i++) {
            $sum += pow(($a[$i] ?? 0) - ($b[$i] ?? 0), 2);
        }
        return sqrt($sum);
    }

    /**
     * Конвертировать debug изображение JPG → WebP
     */
    private function convertDebugToWebP(string $debugPath, ImagePathServiceInterface $pathService): string
    {
        $extension = strtolower(pathinfo($debugPath, PATHINFO_EXTENSION));

        // Если уже WebP - возвращаем имя
        if ($extension === 'webp') {
            return basename($debugPath);
        }

        // Если не JPG - не конвертируем
        if (!in_array($extension, ['jpg', 'jpeg'])) {
            return basename($debugPath);
        }

        // Генерируем WebP имя
        $webpFilename = pathinfo($debugPath, PATHINFO_FILENAME) . '.webp';
        $webpPath = dirname($debugPath) . '/' . $webpFilename;

        // Создаём ImageManager
        $manager = new ImageManager(new Driver());

        // Загружаем изображение
        $img = $manager->read($debugPath);

        // Конвертируем в WebP (quality из конфига)
        $quality = (int) config('image.webp.quality.debug', 80);
        $webpData = $img->toWebp(quality: $quality);

        // Сохраняем WebP
        file_put_contents($webpPath, (string) $webpData);

        // Удаляем оригинальный JPG
        @unlink($debugPath);

        Log::info('Converted debug image to WebP', [
            'original' => basename($debugPath),
            'webp' => $webpFilename
        ]);

        return $webpFilename;
    }
}
