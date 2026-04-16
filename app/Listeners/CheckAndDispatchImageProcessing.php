<?php

namespace App\Listeners;

use App\Contracts\ImagePathServiceInterface;
use App\Events\ImageJobCompleted;
use App\Models\Image;
use Illuminate\Support\Facades\Log;

class CheckAndDispatchImageProcessing
{
    public function __construct(
        protected ImagePathServiceInterface $pathService
    ) {}

    /**
     * Handle the event.
     */
    public function handle(ImageJobCompleted $event): void
    {
        $image = Image::find($event->imageId);

        if (!$image) {
            Log::warning('Image not found for completed job', [
                'image_id' => $event->imageId,
                'job_type' => $event->jobType
            ]);
            return;
        }

        Log::debug('Job completed, checking readiness', [
            'image_id' => $event->imageId,
            'job_type' => $event->jobType,
            'metadata' => $image->metadata ? 'ready' : 'pending',
            'faces_checked' => $image->faces_checked ? 'ready' : 'pending',
            'thumbnail_filename' => $image->thumbnail_filename ? 'ready' : 'pending',
            'webp_converted' => str_ends_with($image->filename, '.webp') ? 'ready' : 'pending',
        ]);

        // Проверяем что ВСЕ jobs завершены (включая ImageProcessJob)
        if ($this->isReadyForCleanup($image)) {
            Log::info('All jobs completed, deleting JPG', [
                'image_id' => $event->imageId
            ]);

            // Удаляем JPG файл
            $this->deleteJpgFile($image);
        } else {
            Log::debug('Not all jobs completed yet', [
                'image_id' => $event->imageId
            ]);
        }
    }

    /**
     * Проверяет готовность к удалению JPG
     */
    private function isReadyForCleanup(Image $image): bool
    {
        // Все 4 jobs должны быть завершены:
        return $image->metadata !== null                      // MetadataProcessJob
            && $image->faces_checked === true                 // FaceProcessJob
            && $image->thumbnail_filename !== null            // ThumbnailProcessJob
            && str_ends_with($image->filename, '.webp');      // ImageProcessJob (hash + WebP)
    }

    /**
     * Удаляет JPG файл после успешной WebP конвертации
     */
    private function deleteJpgFile(Image $image): void
    {
        // Ищем соответствующий JPG файл
        $jpgFilename = pathinfo($image->filename, PATHINFO_FILENAME) . '.jpg';
        $jpgPath = $this->pathService->getImagePathByParams(
            $image->disk,
            $image->path,
            $jpgFilename
        );

        // Проверяем что JPG существует
        if (!file_exists($jpgPath)) {
            Log::debug('JPG file not found, already deleted or never existed', [
                'image_id' => $image->id,
                'jpg_path' => $jpgPath
            ]);
            return;
        }

        // Удаляем JPG
        try {
            unlink($jpgPath);

            Log::info('JPG deleted after all jobs completed', [
                'image_id' => $image->id,
                'jpg_filename' => $jpgFilename,
                'webp_filename' => $image->filename
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to delete JPG file', [
                'image_id' => $image->id,
                'jpg_path' => $jpgPath,
                'error' => $e->getMessage()
            ]);
        }
    }
}
