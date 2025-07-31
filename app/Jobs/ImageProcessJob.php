<?php

namespace App\Jobs;

use App\Models\Image;
use App\Traits\QueueAbleTrait;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Log;

class ImageProcessJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, QueueAbleTrait;

    protected array $taskData;

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
        $imagePath = $this->taskData['source_path'] . '/' . $this->taskData['source_filename'];

        try {
            Image::updateOrCreate(
                [
                    'disk' => $this->taskData['source_disk'],
                    'path' => $this->taskData['source_path'],
                    'filename' => $this->taskData['source_filename']
                ],
                [
                    'parent_id' => $this->taskData['parent_id'],
                    'width' => $this->taskData['width'],
                    'height' => $this->taskData['height'],
                    'size' => $this->taskData['size'],
                    'hash' => $this->taskData['hash'],
                    'created_at_file' => $this->taskData['created_at_file'],
                    'updated_at_file' => $this->taskData['updated_at_file'],
                ]
            );

            Log::info('Processed: ' . $imagePath);
        } catch (\Exception $e) {
            Log::error('Failed to process image ' . $imagePath . ': ' . $e->getMessage());
        } finally {
            $this->removeFromQueue(self::class, $this->taskData);
        }
    }
}
