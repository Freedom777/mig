<?php

namespace App\Jobs;

use App\Contracts\ImagePathServiceInterface;
use App\Models\Image;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Intervention\Image\Format;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Imagick\Driver;

class ConvertToWebpJob extends BaseProcessJob
{
    /**
     * Execute the job.
     */
    public function handle(ImagePathServiceInterface $pathService): void
    {
        $imageId = $this->taskData['image_id'];
        $lockKey = 'webp-conversion:' . $imageId;
        $lock = Cache::lock($lockKey, 30);

        try {
            $lock->block(30, function () use ($pathService) {
                $this->convertToWebp($pathService);
            });
        } catch (\Illuminate\Contracts\Cache\LockTimeoutException $e) {
            Log::warning('Could not acquire lock for WebP conversion', [
                'image_id' => $imageId
            ]);
            $this->release(10);
            return;
        } catch (\Exception $e) {
            Log::error('WebP conversion failed', [
                'image_id' => $imageId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        } finally {
            $this->complete();
        }
    }

    private function convertToWebp(ImagePathServiceInterface $pathService): void
    {
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

        try {
            // Получаем абсолютный путь к JPG
            $jpgPath = $pathService->getImagePathByObj($image);

            // Проверяем что файл существует
            if (!file_exists($jpgPath)) {
                Log::error('JPG file not found for WebP conversion', [
                    'image_id' => $image->id,
                    'path' => $jpgPath
                ]);
                return;
            }

            // Генерируем WebP имя
            $webpFilename = pathinfo($image->filename, PATHINFO_FILENAME) . '.webp';

            // Получаем абсолютный путь для WebP
            $webpPath = $pathService->getImagePathByParams($image->disk, $image->path, $webpFilename);

            // Создаём ImageManager
            $manager = new ImageManager(new Driver());

            // Загружаем изображение (Intervention Image v4 - decodePath)
            $img = $manager->decodePath($jpgPath);

            // Конвертируем в WebP (v4 - encodeUsingFileExtension)
            $quality = (int) config('image.webp.quality.image', 90);
            $webpEncoded = $img->encodeUsingFormat(Format::WEBP, quality: $quality);

            // Сохраняем WebP
            file_put_contents($webpPath, (string) $webpEncoded);

            // Обновляем filename в БД
            $image->filename = $webpFilename;
            $image->save();

            // Удаляем оригинальный JPG
            unlink($jpgPath);

            Log::info('Successfully converted to WebP', [
                'image_id' => $image->id,
                'original_filename' => pathinfo($image->filename, PATHINFO_FILENAME) . '.jpg',
                'webp_filename' => $webpFilename,
                'webp_size' => filesize($webpPath)
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to convert to WebP', [
                'image_id' => $image->id,
                'filename' => $image->filename,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
}
