<?php

namespace App\Services;

use App\Contracts\ImagePathServiceInterface;
use App\Models\Image;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImagePathService implements ImagePathServiceInterface
{
    /**
     * Получить полный путь к debug-изображению
     */
    public function getDebugImagePath(Image $image): ?string
    {
        if ($image->debug_filename) {
            return Storage::disk($image->disk)->path(
                $image->path . '/' . $this->getImageDebugSubdir() . '/' . $image->debug_filename
            );
        }
        return null;
    }

    /**
     * Получить поддиректорию для debug-изображений
     */
    public function getImageDebugSubdir(): string
    {
        return config('image.paths.debug_subdir', 'debug');
    }

    /**
     * Получить полный путь к изображению по объекту Image
     */
    public function getImagePathByObj(Image $image): string
    {
        return Storage::disk($image->disk)->path($image->path . '/' . $image->filename);
    }

    /**
     * Получить полный путь к изображению по параметрам
     */
    public function getImagePathByParams(string $disk, string $path, string $filename): string
    {
        return Storage::disk($disk)->path($path . '/' . $filename);
    }

    /**
     * Получить поддиректорию для thumbnail заданного размера
     */
    public function getThumbnailSubdir(int $width, int $height): string
    {
        $format = config('image.thumbnails.dir_format', '{width}x{height}');

        return Str::of($format)
            ->replace('{width}', $width)
            ->replace('{height}', $height)
            ->toString();
    }

    /**
     * Получить URL thumbnail
     */
    public function getThumbnailUrl(Image $image): string
    {
        return config('app.image_api_url') . '/thumbnail/' . $image->id . '.jpg';
    }

    /**
     * Получить URL оригинального изображения
     */
    public function getImageUrl(Image $image): string
    {
        return config('app.image_api_url') . '/image/' . $image->id . '.jpg';
    }

    /**
     * Сгенерировать имя файла для thumbnail
     */
    public function getThumbnailFilename(string $filename, string $method, int $width, int $height): string
    {
        $postfixFormat = config('image.thumbnails.postfix', '_{method}_{width}x{height}');

        $postfix = Str::of($postfixFormat)
            ->replace('{method}', $method)
            ->replace('{width}', $width)
            ->replace('{height}', $height)
            ->toString();

        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $basename = pathinfo($filename, PATHINFO_FILENAME);

        return $basename . $postfix . '.' . $extension;
    }

    /**
     * Получить полный путь к thumbnail по умолчанию
     */
    public function getDefaultThumbnailPath(Image $image): string
    {
        $width = config('image.thumbnails.width');
        $height = config('image.thumbnails.height');
        $method = config('image.thumbnails.method');

        return Storage::disk($image->disk)->path(
            $image->path . '/' .
            $this->getThumbnailSubdir($width, $height) . '/' .
            $this->getThumbnailFilename($image->filename, $method, $width, $height)
        );
    }

    /**
     * Получить путь к существующему thumbnail (из полей модели)
     * Возвращает null если thumbnail не сгенерирован
     */
    public function getExistingThumbnailPath(Image $image): ?string
    {
        if (!$image->thumbnail_path || !$image->thumbnail_filename) {
            return null;
        }

        return Storage::disk($image->disk)->path(
            $image->path . '/' . $image->thumbnail_path . '/' . $image->thumbnail_filename
        );
    }
}
