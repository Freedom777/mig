<?php

namespace App\Http\Controllers\Traits;

use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

trait CacheImageTrait
{
    /**
     * Отдать файл с кеш-заголовками и поддержкой 304 Not Modified
     */
    protected function cachedFileResponse(string $path, string $mimeType = 'image/jpeg'): Response|BinaryFileResponse
    {
        if (!file_exists($path)) {
            abort(404, 'File not found');
        }

        $lastModified = filemtime($path);
        $etag = md5_file($path);

        // Проверка If-Modified-Since
        $ifModifiedSince = request()->header('If-Modified-Since');
        if ($ifModifiedSince && strtotime($ifModifiedSince) >= $lastModified) {
            return response('', 304)
                ->header('Last-Modified', gmdate('D, d M Y H:i:s', $lastModified) . ' GMT')
                ->header('ETag', $etag);
        }

        // Проверка If-None-Match (ETag)
        $ifNoneMatch = request()->header('If-None-Match');
        if ($ifNoneMatch && $ifNoneMatch === $etag) {
            return response('', 304)
                ->header('Last-Modified', gmdate('D, d M Y H:i:s', $lastModified) . ' GMT')
                ->header('ETag', $etag);
        }

        // Отдаём файл с заголовками кеширования
        return response()->file($path, [
            'Content-Type' => $mimeType,
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'Last-Modified' => gmdate('D, d M Y H:i:s', $lastModified) . ' GMT',
            'ETag' => $etag,
            'Expires' => gmdate('D, d M Y H:i:s', time() + 31536000) . ' GMT',
        ]);
    }

    /**
     * Определить MIME тип по расширению
     */
    protected function getMimeType(string $path): string
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return match ($extension) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            default => 'application/octet-stream',
        };
    }
}
