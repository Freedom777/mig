<?php

namespace App\Contracts;

use App\Models\Image;

interface ImageRepositoryInterface
{
    /**
     * Подготовка данных для создания/обновления изображения
     *
     * @param string $disk
     * @param string $path
     * @param string $filename
     * @return array Данные для создания записи
     */
    public function prepareImageData(string $disk, string $path, string $filename): array;

    /**
     * Проверка существования изображения
     */
    public function exists(string $disk, string $path, string $filename): bool;

    /**
     * Создать или обновить запись изображения
     */
    public function updateOrCreate(array $imageData): ?Image;

    /**
     * Найти изображение по ID
     */
    public function find(int $id): ?Image;

    /**
     * Найти изображение по ID или выбросить исключение
     */
    public function findOrFail(int $id): Image;

    /**
     * Найти похожее изображение по perceptual hash
     */
    public function findSimilarByPhash(string $hexHash, int $maxDistance = 5): ?int;
}
