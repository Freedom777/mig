<?php

namespace App\Jobs;

use App\Contracts\ImagePathServiceInterface;
use App\Contracts\ImageRepositoryInterface;
use App\Models\Image;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Intervention\Image\Format;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Imagick\Driver;
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
        $lock = Cache::lock($lockKey, 60);

        try {
            $lock->block(60, function () use ($imageRepository, $pathService) {
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

        // КРИТИЧЕСКАЯ ПРОВЕРКА: Все предыдущие jobs должны быть выполнены!
        // Иначе не удаляем JPG - можем потерять возможность повторной обработки

        if (!$image->metadata) {
            throw new \Exception('Metadata not processed yet - cannot convert to WebP. JPG preserved.');
        }

        if (!$image->faces_checked) {
            throw new \Exception('Faces not checked yet - cannot convert to WebP. JPG preserved.');
        }

        if (!$image->thumbnail_filename) {
            throw new \Exception('Thumbnail not created yet - cannot convert to WebP. JPG preserved.');
        }

        $filePath = $pathService->getImagePathByObj($image);

        if (!file_exists($filePath)) {
            throw new \Exception('Image file not found: ' . $filePath);
        }

        $threshold = config('image.processing.phash_distance_threshold', 5);

        try {
            // ========================================
            // ШАГ 1: Вычисляем хэши из JPG
            // ========================================

            Log::info('Computing hashes from JPG', [
                'image_id' => $image->id,
                'filename' => $image->filename
            ]);

            // MD5 hash для быстрой проверки точных дубликатов
            $md5 = md5_file($filePath);

            // Сначала быстрая проверка по MD5
            $duplicateId = Image::where('hash', hex2bin($md5))->value('id');

            // Perceptual hash для поиска визуально похожих
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

            Log::info('Image hashes computed successfully', [
                'image_id' => $image->id,
                'has_duplicate' => $duplicateId !== null,
            ]);

            // ========================================
            // ШАГ 2: Конвертируем JPG → WebP
            // ========================================

            $this->convertToWebp($image, $pathService, $filePath);

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

    /**
     * Конвертирует JPG в WebP и удаляет JPG
     */
    private function convertToWebp(Image $image, ImagePathServiceInterface $pathService, string $jpgPath): void
    {
        // Проверяем что это JPG/JPEG
        $extension = strtolower(pathinfo($image->filename, PATHINFO_EXTENSION));
        if (!in_array($extension, ['jpg', 'jpeg'])) {
            Log::info('Image is not JPG/JPEG, skipping WebP conversion', [
                'image_id' => $image->id,
                'filename' => $image->filename,
                'extension' => $extension
            ]);
            return;
        }

        Log::info('Converting JPG to WebP', [
            'image_id' => $image->id,
            'filename' => $image->filename
        ]);

        // Генерируем WebP имя
        $webpFilename = pathinfo($image->filename, PATHINFO_FILENAME) . '.webp';

        // Получаем абсолютный путь для WebP
        $webpPath = $pathService->getImagePathByParams($image->disk, $image->path, $webpFilename);

        // Создаём ImageManager
        $manager = new ImageManager(new Driver());

        // Загружаем изображение (Intervention Image v4)
        $img = $manager->decodePath($jpgPath);

        // Конвертируем в WebP
        $quality = (int) config('image.webp.quality.image', 90);
        $webpEncoded = $img->encodeUsingFormat(Format::WEBP, quality: $quality);

        // Сохраняем WebP
        file_put_contents($webpPath, (string) $webpEncoded);

        // Обновляем filename в БД
        $image->filename = $webpFilename;
        $image->save();

        // Удаляем оригинальный JPG
        unlink($jpgPath);

        Log::info('Successfully converted to WebP and removed JPG', [
            'image_id' => $image->id,
            'webp_filename' => $webpFilename,
            'webp_size' => filesize($webpPath)
        ]);
    }
}
