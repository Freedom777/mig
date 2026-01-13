<?php

namespace App\Jobs;

use App\Models\Image;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Imagick\Driver;
use Illuminate\Support\Facades\Log;

class ThumbnailProcessJob extends BaseProcessJob
{
    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $disk = Storage::disk($this->taskData['disk']);
        $shortPath = $this->taskData['source_path'] . '/' . $this->taskData['source_filename'];

        if (!$disk->exists($shortPath)) {
            throw new \RuntimeException('Source image not found');
        }

        $sourcePath = $disk->path($shortPath);
        $targetDir = $this->taskData['source_path'] . '/' . $this->taskData['thumbnail_path'];

        if (!$disk->exists($targetDir)) {
            $disk->makeDirectory($targetDir);
        }

        $targetPath = $disk->path(
            $targetDir . '/' . $this->taskData['thumbnail_filename']
        );

        $manager = new ImageManager(new Driver());
        $image = $manager->read($sourcePath);

        $method = $this->taskData['thumbnail_method'];
        $image->{$method}(
            $this->taskData['thumbnail_width'],
            $this->taskData['thumbnail_height']
        );

        $image->save($targetPath);

        Image::where('disk', $this->taskData['disk'])
            ->where('path', $this->taskData['source_path'])
            ->where('filename', $this->taskData['source_filename'])
            ->update([
                'thumbnail_path' => $this->taskData['thumbnail_path'],
                'thumbnail_filename' => $this->taskData['thumbnail_filename'],
                'thumbnail_method' => $method,
                'thumbnail_width' => $this->taskData['thumbnail_width'],
                'thumbnail_height' => $this->taskData['thumbnail_height'],
            ]);

        // ✅ OK
        $this->complete();
    }

    public function failed(\Throwable $e): void
    {
        Log::error('Thumbnail job failed', [
            'error' => $e->getMessage(),
            'data' => $this->taskData,
        ]);
    }
}
