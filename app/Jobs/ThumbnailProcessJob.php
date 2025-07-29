<?php

namespace App\Jobs;

use App\Models\Image;
use App\Traits\QueueAbleTrait;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Imagick\Driver;
use Illuminate\Support\Facades\Log;


class ThumbnailProcessJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, QueueAbleTrait;

    protected $taskData;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(array $taskData)
    {
        $this->taskData = $taskData;
    }

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
            $this->removeFromQueue(self::class, $this->taskData);
        }
    }
}
