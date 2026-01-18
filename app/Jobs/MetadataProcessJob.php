<?php

namespace App\Jobs;

use App\Models\Image;
use App\Traits\QueueAbleTrait;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

class MetadataProcessJob extends BaseProcessJob
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, QueueAbleTrait;

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

        $output = $process->getOutput();

        if (!$process->isSuccessful()) {
            Log::error('Extraction metadata process failed: (' . $sourcePath . '): ' . $process->getErrorOutput());
            throw new \Exception('ExifTool process failed');
        }

        $data = json_decode($output, true);
        $metadata = (is_array($data) && isset($data[0])) ? $data[0] : null;

        // Сохраняем метадату в базу
        Image::where('id', $this->taskData['image_id'])
            ->update(['metadata' => $metadata]);

        Log::info('Metadata extracted successfully', [
            'image_id' => $this->taskData['image_id'],
            'has_gps' => $metadata ? $this->hasGeodata($metadata) : false
        ]);

        // Запускаем GeolocationProcessJob с дедупликацией + атомарностью
        if ($metadata && $this->hasGeodata($metadata)) {
            $this->queueGeolocationJob($metadata);
        } else {
            Log::info('No GPS data found in metadata for image ' . $this->taskData['image_id']);
        }
    }

    /**
     * Ставит GeolocationProcessJob в очередь с дедупликацией
     *
     * @param array $metadata
     * @return void
     */
    private function queueGeolocationJob(array $metadata): void {
        $jobData = [
            'image_id' => $this->taskData['image_id'],
            'metadata' => json_encode($metadata),
            'source_disk' => $this->taskData['source_disk'],
            'source_path' => $this->taskData['source_path'],
            'source_filename' => $this->taskData['source_filename'],
        ];

        // Создаем уникальный ключ для дедупликации
        $queueKey = md5(json_encode([
                'class' => GeolocationProcessJob::class
            ] + $jobData));

        try {
            // Пытаемся создать запись для дедупликации
            \App\Models\Queue::create(['queue_key' => $queueKey]);

            // Если успешно - запускаем через chain для атомарности
            Bus::chain([
                new GeolocationProcessJob($jobData)
            ])->onQueue(config('queue.name.metadatas'))->dispatch();

            Log::info('Geolocation job queued for image ' . $this->taskData['image_id']);

        } catch (\Illuminate\Database\QueryException $e) {
            // Джоб уже в очереди - пропускаем
            Log::info('Geolocation job already queued for image ' . $this->taskData['image_id']);
        } catch (\Exception $e) {
            // Другая ошибка - логируем
            Log::error('Failed to queue geolocation job', [
                'image_id' => $this->taskData['image_id'],
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Проверяет наличие геоданных в метадате
     *
     * @param array $metadata
     * @return bool
     */
    private function hasGeodata(array $metadata): bool {
        return (isset($metadata['GPSLatitude']) && isset($metadata['GPSLongitude']))
            || isset($metadata['GPSPosition']);
    }
}
