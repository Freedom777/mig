<?php

namespace App\Jobs;

use App\Models\Geolocation;
use App\Models\Image;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

class MetadataProcessJob extends BaseProcessJob
{
    const FIELDS_NEEDED = [
        'image_id',
        'source_disk', 'source_path', 'source_filename',
    ];

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle() {
        // Lock на основе image_id для предотвращения параллельной обработки
        $lockKey = 'metadata-processing:' . $this->taskData['image_id'];
        $lock = Cache::lock($lockKey, 30); // 30 секунд достаточно для ExifTool

        try {
            $lock->block(30, function () {
                $this->processMetadata();
            });
        } catch (\Illuminate\Contracts\Cache\LockTimeoutException $e) {
            Log::warning('Could not acquire lock for metadata processing', [
                'image_id' => $this->taskData['image_id']
            ]);
            // Повторить через 10 секунд
            $this->release(10);
        } catch (\Exception $e) {
            Log::error('Metadata processing failed', [
                'image_id' => $this->taskData['image_id'],
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
    private function processMetadata(): void {
        $disk = Storage::disk($this->taskData['source_disk']);
        $sourcePath = $disk->path($this->taskData['source_path'] . '/' . $this->taskData['source_filename']);

        $process = new Process(['exiftool', '-json', '-n', $sourcePath]);
        $process->run();

        if (!$process->isSuccessful()) {
            Log::error('Extraction metadata process failed: (' . $sourcePath . '): ' . $process->getErrorOutput());
            throw new \Exception('ExifTool process failed');
        }

        $output = $process->getOutput();
        $data = json_decode($output, true);
        $metadata = $data[0] ?? null;
        $hasGps = $metadata ? Geolocation::hasGeodata($metadata) : false;

        // Сохраняем метадату в базу
        Image::where('id', $this->taskData['image_id'])
            ->update(['metadata' => $metadata]);

        Log::info('Metadata extracted successfully', [
            'image_id' => $this->taskData['image_id'],
            'has_gps' => $hasGps
        ]);

        // Запускаем GeolocationProcessJob с дедупликацией + атомарностью
        if ($hasGps) {
            [$this->taskData['latitude'], $this->taskData['longitude']] = Geolocation::extractCoordinates($metadata);
            if (
                !$this->taskData['latitude'] || !$this->taskData['longitude'] ||
                !is_float($this->taskData['latitude']) || !is_float($this->taskData['longitude'])
            ) {
                Log::info('Can\'t extract coordinates for image ' . $this->taskData['image_id']);
            } else {
                $this->queueGeolocationJob();
            }
        } else {
            Log::info('No GPS data found in metadata for image ' . $this->taskData['image_id']);
        }
    }

    /**
     * Ставит GeolocationProcessJob в очередь с дедупликацией
     *
     * @return void
     */
    private function queueGeolocationJob(): void {
        $jobData = [
            'image_id' => $this->taskData['image_id'],
            'latitude' => $this->taskData['latitude'],
            'longitude' => $this->taskData['longitude'],
        ];

        try {
            $response = BaseProcessJob::pushToQueue(
                GeolocationProcessJob::class,
                config('queue.name.geolocations'),
                $jobData
            );

            $responseData = $response->getData();
            if ($responseData->status === 'success') {
                Log::info('Geolocation job queued', [
                    'image_id' => $jobData['image_id'],
                ]);
            } elseif ($responseData->status === 'exists') {
                Log::info('Geolocation job already in queue', ['image_id' => $jobData['image_id']]);
            }

        } catch (\Exception $e) {
            Log::error('Failed to queue geolocation process', [
                'image_id' => $jobData['image_id'],
                'error' => $e->getMessage()
            ]);
        }
    }
}
