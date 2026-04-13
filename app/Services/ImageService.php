<?php

namespace App\Services;

use App\Contracts\ImageQueueDispatcherInterface;
use App\Contracts\ImageRepositoryInterface;
use App\Contracts\ImageServiceInterface;
use App\Models\Image;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class ImageService implements ImageServiceInterface
{
    public function __construct(
        protected ImageRepositoryInterface $imageRepository,
        protected ImageQueueDispatcherInterface $queueDispatcher
    ) {}

    /**
     * Обработка нового загруженного изображения
     */
    public function processNewUpload(
        string $disk,
        string $path,
        string $filename,
        bool $skipIfExists = false
    ): array {
        Log::info('Processing new upload', [
            'disk' => $disk,
            'path' => $path,
            'filename' => $filename
        ]);

        // Проверяем существование (если нужно пропустить)
        if ($skipIfExists && $this->imageRepository->exists($disk, $path, $filename)) {
            Log::info('Image already exists, skipping', [
                'disk' => $disk,
                'path' => $path,
                'filename' => $filename
            ]);

            return [
                'success' => false,
                'image' => null,
                'message' => 'Image already exists: ' . $filename,
            ];
        }

        // НОВОЕ: Конвертировать JPG → WebP если нужно
        $finalFilename = $this->convertToWebPIfNeeded($disk, $path, $filename);

        // Подготавливаем данные (с WebP filename)
        $preparedData = $this->imageRepository->prepareImageData($disk, $path, $finalFilename);

        // Создаём/обновляем запись в БД
        $image = $this->imageRepository->updateOrCreate($preparedData);

        if (!$image) {
            Log::error('Failed to insert image', [
                'disk' => $disk,
                'path' => $path,
                'filename' => $finalFilename
            ]);

            return [
                'success' => false,
                'image' => null,
                'message' => 'Failed to insert image',
            ];
        }

        Log::info('Image inserted successfully', [
            'image_id' => $image->id,
            'filename' => $finalFilename
        ]);

        // Ставим в очередь все джобы
        $queueStatuses = $this->queueDispatcher->dispatchAll($image);

        Log::info('All jobs queued', ['image_id' => $image->id]);

        return [
            'success' => true,
            'image' => $image,
            'message' => 'Image uploaded and processing started',
            'queue_statuses' => $queueStatuses,
        ];
    }

    /**
     * Конвертировать JPG → WebP если это JPG файл
     * Возвращает финальное имя файла (WebP)
     */
    private function convertToWebPIfNeeded(string $disk, string $path, string $filename): string
    {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        // Если уже WebP - ничего не делаем
        if ($extension === 'webp') {
            return $filename;
        }

        // Если не JPG/JPEG - тоже ничего не делаем (на будущее для PNG)
        if (!in_array($extension, ['jpg', 'jpeg'])) {
            return $filename;
        }

        Log::info('Converting JPG to WebP', [
            'disk' => $disk,
            'path' => $path,
            'filename' => $filename
        ]);

        try {
            $storage = Storage::disk($disk);
            $relativePath = $path . '/' . $filename;
            $absolutePath = $storage->path($relativePath);

            // Проверяем что файл существует
            if (!file_exists($absolutePath)) {
                Log::error('File not found for WebP conversion', [
                    'path' => $absolutePath
                ]);
                return $filename; // Возвращаем оригинальное имя
            }

            // Генерируем WebP имя
            $webpFilename = pathinfo($filename, PATHINFO_FILENAME) . '.webp';
            $webpRelativePath = $path . '/' . $webpFilename;

            // Создаём ImageManager
            $manager = new ImageManager(new Driver());

            // Загружаем изображение
            $img = $manager->read($absolutePath);

            // Конвертируем в WebP (quality из конфига)
            $quality = config('image.webp.quality.image', 90);
            $webpData = $img->toWebp(quality: $quality);

            // Сохраняем WebP
            $storage->put($webpRelativePath, (string) $webpData);

            // Удаляем оригинальный JPG
            $storage->delete($relativePath);

            Log::info('Successfully converted to WebP', [
                'original' => $filename,
                'webp' => $webpFilename,
                'original_size' => filesize($absolutePath),
                'webp_size' => $storage->size($webpRelativePath)
            ]);

            return $webpFilename;

        } catch (\Exception $e) {
            Log::error('Failed to convert to WebP', [
                'filename' => $filename,
                'error' => $e->getMessage()
            ]);

            // В случае ошибки возвращаем оригинальное имя
            return $filename;
        }
    }

    /**
     * Поставить существующее изображение в очередь на обработку
     */
    public function queueForProcessing(Image $image): array
    {
        return [
            'image_id' => $image->id,
            'status' => $this->queueDispatcher->dispatchImageProcess($image),
        ];
    }
}
