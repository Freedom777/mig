<?php

namespace App\Repositories;

use App\Contracts\ImageRepositoryInterface;
use App\Models\Image;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ImageRepository implements ImageRepositoryInterface
{
    /**
     * Подготовка данных для создания/обновления изображения
     */
    public function prepareImageData(string $disk, string $path, string $filename): array
    {
        $diskInstance = Storage::disk($disk);
        $filePath = $diskInstance->path($path) . '/' . $filename;

        return [
            'source_disk' => $disk,
            'source_path' => $path,
            'source_filename' => $filename,
            'size' => filesize($filePath),
            'created_at_file' => date('Y-m-d H:i:s', filectime($filePath)),
            'updated_at_file' => date('Y-m-d H:i:s', filemtime($filePath)),
        ];
    }

    /**
     * Проверка существования изображения в БД
     */
    public function exists(string $disk, string $path, string $filename): bool
    {
        return Image::where([
            'disk' => $disk,
            'path' => $path,
            'filename' => $filename,
        ])->exists();
    }

    /**
     * Создать или обновить запись изображения
     */
    public function updateOrCreate(array $imageData): ?Image
    {
        $imagePath = $imageData['source_path'] . '/' . $imageData['source_filename'];

        try {
            $image = Image::updateOrCreate(
                [
                    'disk' => $imageData['source_disk'],
                    'path' => $imageData['source_path'],
                    'filename' => $imageData['source_filename']
                ],
                [
                    'size' => $imageData['size'],
                    'created_at_file' => $imageData['created_at_file'],
                    'updated_at_file' => $imageData['updated_at_file'],
                ]
            );

            Log::info('Image processed', ['path' => $imagePath, 'id' => $image->id]);

            return $image;
        } catch (\Exception $e) {
            Log::error('Failed to process image', [
                'path' => $imagePath,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Найти изображение по ID
     */
    public function find(int $id): ?Image
    {
        return Image::find($id);
    }

    /**
     * Найти изображение по ID или выбросить исключение
     */
    public function findOrFail(int $id): Image
    {
        return Image::findOrFail($id);
    }

    /**
     * Найти похожее изображение по perceptual hash
     */
    public function findSimilarByPhash(string $hexHash, int $maxDistance = 5): ?int
    {
        return Image::query()
            ->whereRaw('BIT_COUNT(phash ^ UNHEX(?)) < ?', [$hexHash, $maxDistance])
            ->orderByRaw('BIT_COUNT(phash ^ UNHEX(?)) ASC', [$hexHash])
            ->limit(1)
            ->value('id');
    }
}
