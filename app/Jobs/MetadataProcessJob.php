<?php

namespace App\Jobs;

use App\Contracts\ImageQueueDispatcherInterface;
use App\Models\Geolocation;
use App\Models\Image;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class MetadataProcessJob extends BaseProcessJob
{
    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $imageId = $this->taskData['image_id'];
        $lockKey = 'metadata-processing:' . $imageId;
        $lock = Cache::lock($lockKey, 30);

        try {
            $lock->block(30, function () {
                $this->processMetadata();
            });
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
    private function processMetadata(): void
    {
        $image = Image::findOrFail($this->taskData['image_id']);

        // Проверяем есть ли уже metadata в БД
        if (empty($image->metadata)) {
            Log::warning('No metadata found in database', [
                'image_id' => $image->id
            ]);
            return;
        }

        // Парсим metadata из JSON
        $metadata = is_string($image->metadata)
            ? json_decode($image->metadata, true)
            : $image->metadata;

        if (!$metadata) {
            Log::error('Failed to decode metadata JSON', [
                'image_id' => $image->id
            ]);
            return;
        }

        // Используем Geolocation::hasGeodata для проверки GPS (поддерживает оба формата)
        $hasGps = Geolocation::hasGeodata($metadata);

        Log::info('Metadata extracted from database', [
            'image_id' => $image->id,
            'has_gps' => $hasGps
        ]);

        // Запускаем GeolocationProcessJob если есть GPS данные
        if ($hasGps) {
            $this->queueGeolocationJob($image);
        } else {
            Log::info('No GPS data found in metadata', ['image_id' => $image->id]);
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

            Log::info('Geolocation job dispatched', [
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
