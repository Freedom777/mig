<?php

namespace App\Contracts;

use App\Models\Image;

interface ImagePathServiceInterface
{
    /**
     * Получить полный путь к debug-изображению
     */
    public function getDebugImagePath(Image $image): ?string;

    /**
     * Получить поддиректорию для debug-изображений
     */
    public function getImageDebugSubdir(): string;

    /**
     * Получить полный путь к изображению по объекту Image
     */
    public function getImagePathByObj(Image $image): string;

    /**
     * Получить полный путь к изображению по параметрам
     */
    public function getImagePathByParams(string $disk, string $path, string $filename): string;

    /**
     * Получить поддиректорию для thumbnail заданного размера
     */
    public function getThumbnailSubdir(int $width, int $height): string;

    /**
     * Получить URL thumbnail
     */
    public function getThumbnailUrl(Image $image): string;

    /**
     * Получить URL оригинального изображения
     */
    public function getImageUrl(Image $image): string;

    /**
     * Сгенерировать имя файла для thumbnail
     */
    public function getThumbnailFilename(string $filename, string $method, int $width, int $height): string;

    /**
     * Получить полный путь к thumbnail по умолчанию
     */
    public function getDefaultThumbnailPath(Image $image): string;
}
