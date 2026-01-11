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
        try {
            $disk = Storage::disk($this->taskData['disk']);
            $sourcePath = $disk->path($this->taskData['source_path'] . '/' . $this->taskData['source_filename']);
            $targetDir = $this->taskData['source_path'] . '/' . $this->taskData['thumbnail_path'];
            $targetPath = $disk->path($targetDir . '/' . $this->taskData['thumbnail_filename']);


            // $disk->setVisibility($targetDir, 'public');
            $manager = new ImageManager(new Driver());
            $image = $manager->read($sourcePath);
            $image->{$this->taskData['thumbnail_method']}($this->taskData['thumbnail_width'], $this->taskData['thumbnail_height']);

            // Save file with Storage class
            // $disk->put($targetDir . '/' . $this->taskData['thumbnail_filename'], $image);
            $image->save($targetPath);


            Image::where('disk', $this->taskData['disk'])
                ->where('path', $this->taskData['source_path'])
                ->where('filename', $this->taskData['source_filename'])
                ->update([
                    'thumbnail_path' => $this->taskData['thumbnail_path'],
                    'thumbnail_filename' => $this->taskData['thumbnail_filename'],
                    'thumbnail_method' => $this->taskData['thumbnail_method'],
                    'thumbnail_width' => $this->taskData['thumbnail_width'],
                    'thumbnail_height' => $this->taskData['thumbnail_height'],
                ]);

        } catch (\Exception $e) {
            Log::error('Failed to process thumbnail: ' . $e->getMessage());
        } finally {
            $this->complete();
        }
    }
}
