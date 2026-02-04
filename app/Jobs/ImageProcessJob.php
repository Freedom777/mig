<?php

namespace App\Jobs;

use App\Contracts\ImagePathServiceInterface;
use App\Contracts\ImageRepositoryInterface;
use App\Models\Image;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Jenssegers\ImageHash\ImageHash;
use Jenssegers\ImageHash\Implementations\PerceptualHash;

class ImageProcessJob extends BaseProcessJob
{
    /**
     * Execute the job.
     */
    public function handle(
        ImageRepositoryInterface $imageRepository,
        ImagePathServiceInterface $pathService
    ): void {
        $imageId = $this->taskData['image_id'];
        $lockKey = 'image-processing:' . $imageId;
        $lock = Cache::lock($lockKey, 10);

        try {
            $lock->block(10, function () use ($imageRepository, $pathService) {
                $this->processImage($imageRepository, $pathService);
            });
        } catch (\Illuminate\Contracts\Cache\LockTimeoutException $e) {
            Log::warning('Could not acquire lock for image processing', [
                'image_id' => $imageId
            ]);
            $this->release(10);
            return;
        } catch (\Exception $e) {
            Log::error('Image processing failed', [
                'image_id' => $imageId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        } finally {
            $this->complete();
        }
    }

    protected function processImage(
        ImageRepositoryInterface $imageRepository,
        ImagePathServiceInterface $pathService
    ): void {
        $image = Image::findOrFail($this->taskData['image_id']);
        $filePath = $pathService->getImagePathByObj($image);
        $threshold = config('image.processing.phash_distance_threshold', 5);

        try {
            // MD5 hash для быстрой проверки дубликатов
            $md5 = md5_file($filePath);

            // Сначала быстрая проверка по MD5
            $duplicateId = Image::where('hash', hex2bin($md5))->value('id');

            // Perceptual hash
            $hasher = new ImageHash(new PerceptualHash());
            $phashObject = $hasher->hash($filePath);
            $phashHex = $phashObject->toHex();

            // Если не нашли по MD5, ищем по perceptual hash
            if (!$duplicateId) {
                $duplicateId = $imageRepository->findSimilarByPhash($phashHex, $threshold);
            }

            // Получаем размеры изображения
            $imageData = getimagesize($filePath);

            $image->update([
                'parent_id' => $duplicateId,
                'width' => $imageData[0],
                'height' => $imageData[1],
                'hash' => $md5,
                'phash' => $phashHex,
            ]);

            Log::info('Image processed successfully', [
                'image_id' => $image->id,
                'has_duplicate' => $duplicateId !== null,
            ]);

        } catch (\Exception $e) {
            Log::error('ImageProcessJob failed', [
                'image_id' => $this->taskData['image_id'],
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $image->update(['last_error' => $e->getMessage()]);

            throw $e;
        }
    }
}
