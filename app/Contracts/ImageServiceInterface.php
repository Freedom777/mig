<?php

namespace App\Contracts;

use App\Models\Image;

interface ImageServiceInterface
{
    /**
     * Обработка нового загруженного изображения
     * Используется в Controller (newUpload) и Command (ImagesProcess)
     *
     * @param string $disk
     * @param string $path
     * @param string $filename
     * @param bool $skipIfExists Пропустить, если изображение уже существует в БД
     * @return array{success: bool, image: ?Image, message: string, queue_statuses?: array}
     */
    public function processNewUpload(
        string $disk,
        string $path,
        string $filename,
        bool $skipIfExists = false
    ): array;

    /**
     * Поставить изображение в очередь на обработку (только ImageProcessJob)
     * Для случаев когда нужно переобработать существующее изображение
     */
    public function queueForProcessing(Image $image): array;
}
