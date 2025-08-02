<?php

namespace App\Services;

use App\Models\Image;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImagePathService
{
    public static function getDebugImagePath(Image $image) : ?string {
        if ($image->debug_filename) {
            return Storage::disk($image->disk)->path($image->path . '/' . self::getImageDebugSubdir() . '/' . $image->debug_filename);
        }
        return null;
    }

    public static function getImageDebugSubdir() {
        return env('IMAGE_DEBUG_SUBDIR');
    }

    public static function getImagePath(Image $image) {
        return Storage::disk($image->disk)->path($image->path . '/' . $image->filename);
    }

    public static function getThumbnailSubdir(int $width, int $height): string
    {
        return Str::of(config('image.thumbnails.dir_format'))
            ->replace('{width}', $width)
            ->replace('{height}', $height)
            ->__toString();
    }

    public static function getThumbnailUrl(Image $image): string {
        $path = implode('/', array_filter([
            $image->path,
            $image->thumbnail_path,
            $image->thumbnail_filename
        ]));
        $url = Storage::disk($image->disk)->url($path);

        return $url;
    }

    public static function getThumbnailFilename(string $filename, string $method, int $width, int $height): string
    {
        $postfix = Str::of(env('THUMBNAIL_POSTFIX'))
            ->replace('{method}', $method)
            ->replace('{width}', $width)
            ->replace('{height}', $height)
            ->__toString();
        $extension = pathinfo($filename, PATHINFO_EXTENSION);

        return pathinfo($filename, PATHINFO_FILENAME) . $postfix . '.' . $extension;
    }
///////////////////////////////////////////
///
    /*
    public static function getStorageBasePath(bool $isThumbnail = false): string
    {
        return $isThumbnail ? env('THUMBNAIL_STORAGE_PATH') : env('IMAGE_STORAGE_PATH');
    }

    public static function getFullPath(string $directory, string $filename, bool $isThumbnail = false, ?int $width = null, ?int $height = null): string
    {
        return storage_path('app/' . env('IMAGE_STORAGE_DISK') . '/' . self::getRelativeStoragePath($directory, $filename, $isThumbnail, $width, $height));
    }

    public static function getRelativeStoragePath(string $directory, string $filename, bool $isThumbnail = false, ?int $width = null, ?int $height = null): string
    {
        if ($isThumbnail) {
            $filename = self::getThumbnailFilename($filename, $width, $height);
            if ($width && $height) {
                $directory .= '/' . self::getThumbnailSubdir($width, $height);
            }
        }

        return implode('/', array_filter([
            self::getStorageBasePath($isThumbnail),
            $directory,
            $filename
        ]));
    }

    public static function getWebPath(string $directory, string $filename, bool $isThumbnail = false, ?int $width = null, ?int $height = null): string
    {
        $subDir = '';
        if ($isThumbnail) {
            $filename = self::getThumbnailFilename($filename, $width, $height);
            if ($width && $height) {
                $subDir .= self::getThumbnailSubdir($width, $height);
            }
        }

        $path = implode('/', array_filter([
            self::getStorageBasePath($isThumbnail),
            $directory,
            $subDir,
            $filename
        ]));

        return Storage::disk(env('IMAGE_STORAGE_DISK'))->url($path);
    }
    */
}
