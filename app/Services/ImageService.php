<?php

namespace App\Services;

use App\Contracts\ImagePathServiceInterface;
use App\Contracts\ImageQueueDispatcherInterface;
use App\Contracts\ImageRepositoryInterface;
use App\Contracts\ImageServiceInterface;
use App\Models\Image;
use Illuminate\Support\Facades\Log;

class ImageService implements ImageServiceInterface
{
    public function __construct(
        protected ImageRepositoryInterface $imageRepository,
        protected ImageQueueDispatcherInterface $queueDispatcher,
        protected ImagePathServiceInterface $pathService
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

        // Проверяем формат - только JPG/JPEG
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if (!in_array($extension, ['jpg', 'jpeg'])) {
            Log::warning('Invalid image format - only JPG/JPEG allowed', [
                'filename' => $filename,
                'extension' => $extension
            ]);

            return [
                'success' => false,
                'image' => null,
                'message' => 'Invalid format: ' . $extension . '. Only JPG/JPEG allowed.',
            ];
        }

        // Генерируем WebP filename для проверки существования
        $webpFilename = pathinfo($filename, PATHINFO_FILENAME) . '.webp';

        // Проверяем существование WebP в БД (если нужно пропустить)
        if ($skipIfExists && $this->imageRepository->exists($disk, $path, $webpFilename)) {
            Log::info('Image already exists (as WebP), skipping', [
                'disk' => $disk,
                'path' => $path,
                'original' => $filename,
                'webp' => $webpFilename
            ]);

            return [
                'success' => false,
                'image' => null,
                'message' => 'Image already exists: ' . $webpFilename,
            ];
        }

        // 1. Подготавливаем данные (читаем EXIF из оригинального JPG)
        $preparedData = $this->imageRepository->prepareImageData($disk, $path, $filename);

        // 2. Создаём/обновляем запись в БД (сохраняем как JPG!)
        $image = $this->imageRepository->updateOrCreate($preparedData);

        if (!$image) {
            Log::error('Failed to insert image', [
                'disk' => $disk,
                'path' => $path,
                'filename' => $filename
            ]);

            return [
                'success' => false,
                'image' => null,
                'message' => 'Failed to insert image',
            ];
        }

        Log::info('Image inserted successfully', [
            'image_id' => $image->id,
            'filename' => $filename,
            'has_geolocation' => (bool)$image->image_geolocation_point_id,
            'has_taken_at' => (bool)$image->taken_at
        ]);

        // 3. Если есть GPS point → запускаем GeolocationProcessJob СРАЗУ
        if ($image->image_geolocation_point_id) {
            $geoStatus = $this->queueDispatcher->dispatchGeolocation($image);

            Log::info('Geolocation job dispatched from ImageService', [
                'image_id' => $image->id,
                'status' => $geoStatus
            ]);
        }

        // 4. Ставим в очередь все остальные джобы (включая ConvertToWebPJob в конце)
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
