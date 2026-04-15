<?php

namespace App\Listeners;

use App\Events\ImageJobCompleted;
use App\Jobs\ImageProcessJob;
use App\Models\Image;
use Illuminate\Support\Facades\Log;

class CheckAndDispatchImageProcessing
{
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
        ]);

        // Проверяем что ВСЕ jobs завершены
        if ($this->isReadyForProcessing($image)) {
            Log::info('All jobs completed, dispatching ImageProcessJob', [
                'image_id' => $event->imageId
            ]);

            // Dispatch ImageProcessJob для финальной обработки
            ImageProcessJob::dispatch([
                'image_id' => $event->imageId
            ])->onQueue('images');
        } else {
            Log::debug('Not all jobs completed yet', [
                'image_id' => $event->imageId
            ]);
        }
    }

    /**
     * Проверяет готовность изображения к финальной обработке
     */
    private function isReadyForProcessing(Image $image): bool
    {
        // Проверяем что ImageProcessJob ещё не запускалась
        // (чтобы не запустить дважды если несколько events придут одновременно)
        if ($image->hash || $image->phash) {
            Log::debug('ImageProcessJob already completed', [
                'image_id' => $image->id
            ]);
            return false;
        }

        // Все обязательные jobs должны быть завершены
        return $image->metadata !== null
            && $image->faces_checked === true
            && $image->thumbnail_filename !== null;
    }
}
