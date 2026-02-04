<?php

namespace App\Services;

use App\Contracts\ImageQueueDispatcherInterface;
use App\Contracts\ImageRepositoryInterface;
use App\Contracts\ImageServiceInterface;
use App\Models\Image;
use Illuminate\Support\Facades\Log;

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

        // Подготавливаем данные
        $preparedData = $this->imageRepository->prepareImageData($disk, $path, $filename);

        // Создаём/обновляем запись в БД
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
            'filename' => $filename
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
