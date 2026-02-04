<?php

namespace App\Contracts;

use App\Models\Image;

interface ImageQueueDispatcherInterface
{
    /**
     * Получить текущий режим обработки
     *
     * @return string queue|sync|disabled
     */
    public function getMode(): string;

    /**
     * Установить режим обработки (override конфига)
     */
    public function setMode(string $mode): self;

    /**
     * Проверить, включён ли dry-run
     */
    public function isDryRun(): bool;

    /**
     * Установить dry-run режим
     */
    public function setDryRun(bool $dryRun): self;

    /**
     * Проверить, включена ли отладка
     */
    public function isDebug(): bool;

    /**
     * Установить режим отладки
     */
    public function setDebug(bool $debug): self;

    /**
     * Поставить в очередь все джобы для обработки изображения
     *
     * @param Image $image
     * @return array Статусы ['job_name' => 'success|exists|error|skipped|completed|dry-run']
     */
    public function dispatchAll(Image $image): array;

    /**
     * Поставить в очередь ImageProcessJob
     */
    public function dispatchImageProcess(Image $image): string;

    /**
     * Поставить в очередь ThumbnailProcessJob
     */
    public function dispatchThumbnail(Image $image): string;

    /**
     * Поставить в очередь MetadataProcessJob
     */
    public function dispatchMetadata(Image $image): string;

    /**
     * Поставить в очередь GeolocationProcessJob
     */
    public function dispatchGeolocation(Image $image): string;

    /**
     * Поставить в очередь FaceProcessJob
     */
    public function dispatchFace(Image $image): string;
}
