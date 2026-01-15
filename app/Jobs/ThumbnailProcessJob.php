<?php

namespace App\Jobs;

use App\Models\Image;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Imagick\Driver;
use Illuminate\Support\Facades\Log;

class ThumbnailProcessJob extends BaseProcessJob
{
    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        // Lock на основе image_id для единообразия с другими джобами
        $lockKey = 'thumbnail-processing:' . $this->taskData['image_id'];
        $lock = Cache::lock($lockKey, 60); // 60 секунд для обработки изображения

        try {
            $lock->block(60, function () {
                $this->processThumbnail();
            });
        } catch (\Illuminate\Contracts\Cache\LockTimeoutException $e) {
            Log::warning('Could not acquire lock for thumbnail processing', [
                'image_id' => $this->taskData['image_id']
            ]);
            // Повторить через 30 секунд
            $this->release(30);
        } catch (\Exception $e) {
            Log::error('Thumbnail processing failed', [
                'image_id' => $this->taskData['image_id'],
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
    private function processThumbnail(): void
    {
        $disk = Storage::disk($this->taskData['disk']);
        $shortPath = $this->taskData['source_path'] . '/' . $this->taskData['source_filename'];

        // Проверяем существование исходного файла
        if (!$disk->exists($shortPath)) {
            throw new \RuntimeException('Source image not found: ' . $shortPath);
        }

        $sourcePath = $disk->path($shortPath);
        $targetDir = $this->taskData['source_path'] . '/' . $this->taskData['thumbnail_path'];

        // Создаем директорию для thumbnails если её нет
        if (!$disk->exists($targetDir)) {
            $disk->makeDirectory($targetDir);

            // Устанавливаем права 755 (rwxr-xr-x)
            $fullTargetDirPath = $disk->path($targetDir);
            chmod($fullTargetDirPath, 0755);

            Log::info('Created thumbnail directory with proper permissions', [
                'path' => $targetDir,
                'full_path' => $fullTargetDirPath
            ]);
        }

        $targetPath = $disk->path(
            $targetDir . '/' . $this->taskData['thumbnail_filename']
        );

        // Проверяем, не существует ли уже thumbnail
        if (file_exists($targetPath)) {
            Log::info('Thumbnail already exists, skipping', [
                'image_id' => $this->taskData['image_id'],
                'target_path' => $targetPath
            ]);
            return;
        }

        try {
            // Создаем thumbnail
            $manager = new ImageManager(new Driver());
            $image = $manager->read($sourcePath);

            $method = $this->taskData['thumbnail_method'];

            // Валидация метода
            if (!in_array($method, ['cover', 'scale', 'resize', 'contain'])) {
                throw new \InvalidArgumentException('Invalid thumbnail method: ' . $method);
            }

            $image->{$method}(
                $this->taskData['thumbnail_width'],
                $this->taskData['thumbnail_height']
            );

            $image->save($targetPath);

            // Проверяем, что файл действительно создан
            if (!file_exists($targetPath)) {
                throw new \RuntimeException('Thumbnail file was not created: ' . $targetPath);
            }

            // Устанавливаем права на файл
            chmod($targetPath, 0644);

            Log::info('Thumbnail created successfully', [
                'image_id' => $this->taskData['image_id'],
                'source' => $shortPath,
                'target' => $targetPath,
                'method' => $method,
                'dimensions' => $this->taskData['thumbnail_width'] . 'x' . $this->taskData['thumbnail_height']
            ]);

        } catch (\Exception $e) {
            // Удаляем частично созданный файл если что-то пошло не так
            if (file_exists($targetPath)) {
                @unlink($targetPath);
            }
            throw new \Exception('Failed to create thumbnail: ' . $e->getMessage());
        }

        // Обновляем запись в БД
        $this->updateImageRecord();
    }

    /**
     * Обновляет запись изображения в БД
     */
    private function updateImageRecord(): void
    {
        $updated = Image::where('id', $this->taskData['image_id'])
            ->update([
                'thumbnail_path' => $this->taskData['thumbnail_path'],
                'thumbnail_filename' => $this->taskData['thumbnail_filename'],
                'thumbnail_method' => $this->taskData['thumbnail_method'],
                'thumbnail_width' => $this->taskData['thumbnail_width'],
                'thumbnail_height' => $this->taskData['thumbnail_height'],
            ]);

        if (!$updated) {
            Log::warning('Failed to update image record', [
                'image_id' => $this->taskData['image_id']
            ]);
        }
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
            'data' => $this->taskData,
        ]);

        // Опционально: можно отметить в БД что thumbnail не удалось создать
        if (isset($this->taskData['image_id'])) {
            Image::where('id', $this->taskData['image_id'])
                ->update([
                    'last_error' => $e->getMessage(),
                ]);
        }
    }
}
