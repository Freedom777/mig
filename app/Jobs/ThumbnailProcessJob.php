<?php

namespace App\Jobs;

use App\Contracts\ImagePathServiceInterface;
use App\Enums\ThumbMethodEnum;
use App\Models\Image;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Format;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Imagick\Driver;
use Illuminate\Support\Facades\Log;

class ThumbnailProcessJob extends BaseProcessJob
{
    /**
     * Execute the job.
     */
    public function handle(ImagePathServiceInterface $pathService): void
    {
        $imageId = $this->taskData['image_id'];
        $lockKey = 'thumbnail-processing:' . $imageId;
        $lock = Cache::lock($lockKey, 60);

        try {
            $lock->block(60, function () use ($pathService) {
                $this->processThumbnail($pathService);
            });
        } catch (\Illuminate\Contracts\Cache\LockTimeoutException $e) {
            Log::warning('Could not acquire lock for thumbnail processing', [
                'image_id' => $imageId
            ]);
            $this->release(30);
            return;
        } catch (\Exception $e) {
            Log::error('Thumbnail processing failed', [
                'image_id' => $imageId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        } finally {
            $this->complete();
        }
    }

    /**
     * Обрабатывает создание thumbnail
     */
    private function processThumbnail(ImagePathServiceInterface $pathService): void
    {
        $image = Image::findOrFail($this->taskData['image_id']);

        // Получаем конфигурацию thumbnail
        $thumbWidth = config('image.thumbnails.width', 300);
        $thumbHeight = config('image.thumbnails.height', 200);
        $thumbMethod = config('image.thumbnails.method', 'cover');

        // Генерируем пути через PathService
        $thumbPath = $pathService->getThumbnailSubdir($thumbWidth, $thumbHeight);
        $thumbFilename = $pathService->getThumbnailFilename(
            $image->filename,
            $thumbMethod,
            $thumbWidth,
            $thumbHeight
        );

        $sourcePath = $pathService->getImagePathByObj($image);

        if (!file_exists($sourcePath)) {
            throw new \RuntimeException('Source image not found: ' . $sourcePath);
        }

        $disk = Storage::disk($image->disk);
        $targetDir = $image->path . '/' . $thumbPath;

        // Создаем директорию для thumbnails если её нет
        if (!$disk->exists($targetDir)) {
            $disk->makeDirectory($targetDir);
            chmod($disk->path($targetDir), 0755);

            Log::info('Created thumbnail directory', [
                'path' => $targetDir,
            ]);
        }

        $targetPath = $disk->path($targetDir . '/' . $thumbFilename);

        // Проверяем, не существует ли уже thumbnail
        if (file_exists($targetPath)) {
            // Обновляем БД даже если файл существует, так как могут отсутствовать данные в БД
            $image->update([
                'thumbnail_path' => $thumbPath,
                'thumbnail_filename' => $thumbFilename,
                'thumbnail_method' => $thumbMethod,
                'thumbnail_width' => $thumbWidth,
                'thumbnail_height' => $thumbHeight,
            ]);

            Log::info('Thumbnail already exists, skipping', [
                'image_id' => $image->id,
                'target_path' => $targetPath
            ]);
            return;
        }

        try {
            $manager = new ImageManager(new Driver());

            $img = $manager->decodePath($sourcePath);

            if (!in_array($thumbMethod, ThumbMethodEnum::values())) {
                throw new \InvalidArgumentException('Invalid thumbnail method: ' . $thumbMethod);
            }

            // Применяем метод ресайза
            $img->{$thumbMethod}($thumbWidth, $thumbHeight);

            // Конвертируем в WebP (v4 API)
            $quality = (int) config('image.webp.quality.thumbnail', 85);
            $webpEncoded = $img->encodeUsingFormat(Format::WEBP, quality: $quality);

            // Сохраняем WebP
            file_put_contents($targetPath, (string) $webpEncoded);

            if (!file_exists($targetPath)) {
                throw new \RuntimeException('Thumbnail file was not created: ' . $targetPath);
            }

            chmod($targetPath, 0644);

            Log::info('Thumbnail created successfully', [
                'image_id' => $image->id,
                'target' => $targetPath,
                'dimensions' => "{$thumbWidth}x{$thumbHeight}",
                'method' => $thumbMethod,
                'format' => 'webp',
                'quality' => $quality,
            ]);

        } catch (\Exception $e) {
            if (file_exists($targetPath)) {
                @unlink($targetPath);
            }
            throw new \Exception('Failed to create thumbnail: ' . $e->getMessage());
        }

        // Обновляем запись в БД
        $image->update([
            'thumbnail_path' => $thumbPath,
            'thumbnail_filename' => $thumbFilename,
            'thumbnail_method' => $thumbMethod,
            'thumbnail_width' => $thumbWidth,
            'thumbnail_height' => $thumbHeight,
        ]);
    }

    /**
     * Обработка failed джоба
     */
    public function failed(\Throwable $e): void
    {
        Log::error('Thumbnail job failed', [
            'image_id' => $this->taskData['image_id'] ?? null,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);

        if (isset($this->taskData['image_id'])) {
            Image::where('id', $this->taskData['image_id'])
                ->update(['last_error' => $e->getMessage()]);
        }
    }
}
