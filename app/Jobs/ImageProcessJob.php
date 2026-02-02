<?php

namespace App\Jobs;

use App\Console\Commands\ImagesPhashes;
use App\Models\Image;
use App\Services\ImagePathService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Jenssegers\ImageHash\ImageHash;
use Jenssegers\ImageHash\Implementations\PerceptualHash;

class ImageProcessJob extends BaseProcessJob
{

    const FIELDS_NEEDED = [
        'image_id',
        // 'source_disk', 'source_path', 'source_filename',
    ];
    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        // Lock на основе image_id для предотвращения параллельной обработки
        $lockKey = 'image-processing:' . $this->taskData['image_id'];
        $lock = Cache::lock($lockKey, 10); // 10 секунд достаточно для изображения

        try {
            $lock->block(10, function () {
                $this->processImage();
            });
        } catch (\Illuminate\Contracts\Cache\LockTimeoutException $e) {
            Log::warning('Could not acquire lock for image processing', [
                'image_id' => $this->taskData['image_id']
            ]);
            // Повторить через 10 секунд
            $this->release(10);
        } catch (\Exception $e) {
            Log::error('Image processing failed', [
                'image_id' => $this->taskData['image_id'],
                'error' => $e->getMessage()
            ]);
            throw $e;
        } finally {
            $this->complete();
        }
    }

    protected function processImage() {
        $image = Image::findOrFail($this->taskData['image_id']);
        $filePath = ImagePathService::getImagePathByObj($image);

        try {
            $md5 = md5_file($filePath);

            // Сначала быстрая проверка по MD5
            $duplicateId = Image::where('hash', $md5)->value('id');

            // Perceptual hash нужен в любом случае для сохранения в БД
            $hasher = new ImageHash(new PerceptualHash());
            $phashCurrent = $hasher->hash($filePath);

            // Только если не нашли по MD5, ищем по perceptual hash
            if (!$duplicateId) {
                $phashCurrentHex = $phashCurrent->toHex();
                $duplicateId = Image::findSimilarImageId($phashCurrentHex, ImagesPhashes::PHASH_DISTANCE_THRESHOLD);
            }

            $imageData = getimagesize($filePath);

            $image->update([
                'parent_id' => $duplicateId,
                'width' => $imageData[0],
                'height' => $imageData[1],
                'hash' => $md5,
                'phash' => $phashCurrent,
            ]);
        } catch (\Exception $e) {
            Log::error('ImageProcessJob failed (' . 'image_id = ' . $this->taskData['image_id'] . '): ' . $e->getMessage());
        } finally {
            $this->complete();
        }
    }
}
