<?php

namespace App\Jobs;

use App\Contracts\ImageQueueDispatcherInterface;
use App\Models\Geolocation;
use App\Models\Image;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

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

        $disk = Storage::disk($image->disk);
        $sourcePath = $disk->path($image->path . '/' . $image->filename);

        $process = new Process(['exiftool', '-json', '-n', $sourcePath]);
        $process->run();

        if (!$process->isSuccessful()) {
            Log::error('ExifTool process failed', [
                'image_id' => $image->id,
                'path' => $sourcePath,
                'error' => $process->getErrorOutput()
            ]);
            throw new \Exception('ExifTool process failed');
        }

        $output = $process->getOutput();
        $data = json_decode($output, true);
        $metadata = $data[0] ?? null;
        $hasGps = $metadata ? Geolocation::hasGeodata($metadata) : false;

        // Сохраняем метадату в базу
        $image->update(['metadata' => $metadata]);

        Log::info('Metadata extracted successfully', [
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
