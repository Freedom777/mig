<?php

namespace App\Jobs;

use App\Contracts\ImagePathServiceInterface;
use App\Contracts\ImageQueueDispatcherInterface;
use App\Events\ImageJobCompleted;
use App\Models\Image;
use App\Models\ImageGeolocationPoint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use MatanYadaev\EloquentSpatial\Objects\Point;
use Symfony\Component\Process\Process;

class MetadataProcessJob extends BaseProcessJob
{
    /**
     * Execute the job.
     */
    public function handle(ImagePathServiceInterface $pathService): void
    {
        $imageId = $this->taskData['image_id'];
        $lockKey = 'metadata-processing:' . $imageId;
        $lock = Cache::lock($lockKey, 30);

        try {
            $lock->block(30, function () use ($pathService) {
                $this->processMetadata($pathService);
            });

            event(new ImageJobCompleted($imageId, 'metadata'));

        } catch (\Illuminate\Contracts\Cache\LockTimeoutException $e) {
            Log::warning('Could not acquire lock for metadata processing', [
                'image_id' => $imageId
            ]);
            $this->release(10);
            return;
        } catch (\Exception $e) {
            Log::error('Metadata processing failed', [
                'image_id' => $imageId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        } finally {
            $this->complete();
        }
    }

    /**
     * Обрабатывает метадату изображения
     */
    private function processMetadata(ImagePathServiceInterface $pathService): void
    {
        $image = Image::findOrFail($this->taskData['image_id']);

        // Получаем путь к изображению
        $imagePath = $pathService->getImagePathByObj($image);

        if (!file_exists($imagePath)) {
            Log::error('Image file not found for metadata processing', [
                'image_id' => $image->id,
                'path' => $imagePath
            ]);
            return;
        }

        Log::info('Running exiftool for metadata extraction', [
            'image_id' => $image->id,
            'filename' => $image->filename
        ]);

        // ВСЕГДА запускаем exiftool для полной metadata
        $process = new Process(['exiftool', '-json', '-n', $imagePath]);
        $process->run();

        if (!$process->isSuccessful()) {
            Log::error('exiftool failed', [
                'image_id' => $image->id,
                'error' => $process->getErrorOutput()
            ]);
            return;
        }

        $output = $process->getOutput();
        $exifData = json_decode($output, true);

        // Берём [0] элемент массива!
        $metadata = $exifData[0] ?? null;

        if (!$metadata) {
            Log::warning('exiftool returned empty metadata', [
                'image_id' => $image->id
            ]);
            return;
        }

        // Сохраняем metadata (Laravel автоматически закодирует в JSON)
        $image->metadata = $metadata;

        // Проверяем point - если нет, пробуем создать из exiftool
        if (!$image->image_geolocation_point_id) {
            if (isset($metadata['GPSLatitude']) && isset($metadata['GPSLongitude'])) {
                $point = $this->createGpsPoint($metadata['GPSLatitude'], $metadata['GPSLongitude']);

                if ($point) {
                    $image->image_geolocation_point_id = $point->id;

                    Log::info('GPS point created from exiftool', [
                        'image_id' => $image->id,
                        'point_id' => $point->id,
                        'coordinates' => [$metadata['GPSLatitude'], $metadata['GPSLongitude']]
                    ]);

                    // Запускаем GeolocationProcessJob
                    $this->queueGeolocationJob($image);
                }
            }
        }

        // Проверяем taken_at - если нет, пробуем взять из exiftool
        if (!$image->taken_at) {
            $takenAt = $this->extractTakenAt($metadata);
            if ($takenAt) {
                $image->taken_at = $takenAt;

                Log::info('taken_at extracted from exiftool', [
                    'image_id' => $image->id,
                    'taken_at' => $takenAt
                ]);
            }
        }

        // Сохраняем изменения
        $image->save();

        Log::info('Metadata processing completed', [
            'image_id' => $image->id,
            'has_metadata' => true,
            'has_point' => (bool)$image->image_geolocation_point_id,
            'has_taken_at' => (bool)$image->taken_at
        ]);
    }

    /**
     * Создать GPS point из exiftool координат
     */
    private function createGpsPoint(float $latitude, float $longitude): ?ImageGeolocationPoint
    {
        try {
            $pointLatLon = new Point($latitude, $longitude);

            // Находим или создаём point
            $point = ImageGeolocationPoint::firstOrCreate([
                'coordinates' => $pointLatLon
            ]);

            return $point;

        } catch (\Exception $e) {
            Log::error('Failed to create GPS point from exiftool', [
                'error' => $e->getMessage(),
                'latitude' => $latitude,
                'longitude' => $longitude
            ]);
            return null;
        }
    }

    /**
     * Извлечь дату съёмки из exiftool metadata
     */
    private function extractTakenAt(array $metadata): ?string
    {
        // Приоритет: DateTimeOriginal → CreateDate → DateTime
        if (isset($metadata['DateTimeOriginal'])) {
            return $this->convertExiftoolDateTime($metadata['DateTimeOriginal']);
        }

        if (isset($metadata['CreateDate'])) {
            return $this->convertExiftoolDateTime($metadata['CreateDate']);
        }

        if (isset($metadata['DateTime'])) {
            return $this->convertExiftoolDateTime($metadata['DateTime']);
        }

        return null;
    }

    /**
     * Конвертировать exiftool дату в MySQL формат
     */
    private function convertExiftoolDateTime(string $exifDate): ?string
    {
        try {
            // exiftool формат: "2026:04:13 22:39:07"
            $timestamp = strtotime($exifDate);
            if ($timestamp === false) {
                return null;
            }
            return date('Y-m-d H:i:s', $timestamp);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Ставит GeolocationProcessJob в очередь
     */
    private function queueGeolocationJob(Image $image): void
    {
        try {
            /** @var ImageQueueDispatcherInterface $dispatcher */
            $dispatcher = app(ImageQueueDispatcherInterface::class);
            $status = $dispatcher->dispatchGeolocation($image);

            Log::info('Geolocation job dispatched from MetadataProcessJob', [
                'image_id' => $image->id,
                'status' => $status,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to dispatch geolocation job', [
                'image_id' => $image->id,
                'error' => $e->getMessage()
            ]);
        }
    }
}
